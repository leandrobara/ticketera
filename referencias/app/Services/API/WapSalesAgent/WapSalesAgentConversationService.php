<?php

namespace App\Services\API\WapSalesAgent;

use DateTime;
use Exception;
use DateTimeZone;
use App\Models\Lead;
use App\Models\Note;
use App\Models\User;
use App\Models\Task;
use App\Models\Tag;
use App\Models\Client;
use App\Models\Status;
use Illuminate\Support\Str;
use App\Helpers\RedisHelper;
use App\Services\API\NoteService;
use App\Services\API\LeadService;
use App\Services\API\TaskService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use App\Services\API\StatusService;
use App\Services\API\LeadSaleService;
use App\Services\API\LeadContactService;
use App\DTO\WapSalesAgent\LeadCandidateDTO;
use App\DTO\WapSalesAgent\TaskCandidateDTO;
use App\Services\API\LeadContactEmailService;
use App\Services\API\LeadContactPhoneService;
use App\Repositories\LeadContactEmailRepository;
use App\Repositories\LeadContactPhoneRepository;
use App\Services\API\Views\LeadService as LeadServiceView;
use App\Services\API\Views\TaskService as TaskServiceView;
use App\Services\API\Actions\LeadService as ActionsLeadService;


/**
 * WapSalesAgentConversationService
 *
 * Servicio principal del agente de ventas de WhatsApp.
 * Consolida toda la lógica de negocio:
 * - Estado de sesión (Redis)
 * - Historial de OpenAI
 * - Búsqueda y actualización de leads
 * - Ejecución de acciones
 *
 * FLUJO DE CONVERSACIÓN:
 *   READY                    → Usuario libre, mensaje pasa por Router → Validators → Workflow
 *   AWAITING_SELECTION       → Usuario debe elegir de candidatos (leads o tasks)
 *   AWAITING_CONFIRMATION    → Usuario debe confirmar/rechazar actualización de lead
 *   AWAITING_VALIDATOR_INFO  → Usuario debe completar información faltante
 *   LEAD_SCOPE               → Usuario parado sobre un prospecto activo
 *   LEAD_NOTES_FLOW          → Usuario navega notas del prospecto activo
 *   TASK_SCOPE               → Usuario navega tareas (listar, ver, crear)
 *   AWAITING_LEAD_SALE_INFO  → Usuario confirma/completa datos de venta del prospecto
 */
class WapSalesAgentConversationService
{

    private const OPEN_AI_HISTORY_LIMIT = 25;
    private const SESSION_TTL_SECONDS = 43200; // 12 horas
    private const SESSION_KEY_PREFIX = 'wap_sales_agent_session';

    
    public const STATUS_READY = 'READY';
    public const STATUS_VIEWING_STATUSES = 'VIEWING_STATUSES';
    public const STATUS_AWAITING_SELECTION = 'AWAITING_SELECTION';
    public const STATUS_AWAITING_CONFIRMATION = 'AWAITING_CONFIRMATION';
    public const STATUS_AWAITING_VALIDATOR_INFO = 'AWAITING_VALIDATOR_INFO';
    public const STATUS_AWAITING_STATUS_SELECTION = 'AWAITING_STATUS_SELECTION';
    
    public const STATUS_LEAD_SCOPE = 'LEAD_SCOPE';
    
    public const STATUS_LEAD_NOTES_FLOW = 'LEAD_NOTES_FLOW';
    public const NOTES_SUB_LISTING = 'listing';
    public const NOTES_SUB_VIEWING = 'viewing';
    public const NOTES_SUB_AWAITING_CONTENT = 'awaiting_content';
    public const NOTES_SUB_POST_CREATE = 'post_create';
    
    public const STATUS_TASK_SCOPE = 'TASK_SCOPE';
    public const TASK_SUB_LISTING = 'listing';
    public const TASK_SUB_VIEWING = 'viewing';
    public const TASK_SUB_AWAITING_CONTENT = 'awaiting_content';
    public const TASK_SUB_POST_CREATE = 'post_create';
    
    public const STATUS_AWAITING_LEAD_SALE_INFO = 'AWAITING_LEAD_SALE_INFO';
    public const SALE_SUB_CONFIRMING = 'confirming';
    public const SALE_SUB_COLLECTING = 'collecting';

    public function __construct(
        private NoteService $noteService,
        private LeadService $leadService,
        private TaskService $taskService,
        private StatusService $statusService,
        private TaskServiceView $taskServiceView,
        private LeadServiceView $leadServiceView,
        private LeadContactService $leadContactService,
        private ActionsLeadService $actionsLeadService,
        private LeadContactEmailService $leadContactEmailService,
        private LeadContactPhoneService $leadContactPhoneService,
    ) {
    }

    // =========================================================================
    // SESSION CORE
    // Redis base para toda la conversación.
    // =========================================================================

    /**
     * Resetea toda la sesión del usuario
     */
    public function resetSession(int $clientId, string $customerPhone, ?string $botPhoneId = null): void
    {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $listKey = $this->buildHistoryListKey($customerPhone, $botPhoneId);

        $redis->del($redis->getScopedKey($hashKey));
        $redis->del($redis->getScopedKey($listKey));
    }


    // =========================================================================
    // LEAD CONTEXT
    // Estado del lead activo, candidatos pendientes y pending update.
    // =========================================================================

    /**
     * Obtiene el ID del lead activo
     */
    public function getActiveLeadId(int $clientId, string $customerPhone, ?string $botPhoneId = null): ?int
    {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return null;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $value = $redis->hget($redis->getScopedKey($hashKey), 'activeLeadId');

        if (!is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        return (int) $value;
    }


    /**
     * Establece el lead activo (limpia candidatos pendientes)
     */
    public function setActiveLead(int $clientId, string $customerPhone, ?int $leadId, ?string $botPhoneId = null): void
    {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $scopedHashKey = $redis->getScopedKey($hashKey);

        $redis->hset($scopedHashKey, 'activeLeadId', $leadId ?? '');
        $this->setPendingCandidateLeadIds($clientId, $customerPhone, [], $botPhoneId);
        $this->refreshSessionTTL($redis, $customerPhone, $botPhoneId);
    }


    /**
     * Obtiene los IDs de candidatos pendientes de selección
     */
    public function getPendingCandidateLeadIds(
        int $clientId,
        string $customerPhone,
        ?string $botPhoneId = null
    ): array {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return [];
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $value = $redis->hget($redis->getScopedKey($hashKey), 'pendingCandidateLeadIds');

        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }


    /**
     * Establece los candidatos pendientes
     */
    public function setPendingCandidateLeadIds(
        int $clientId,
        string $customerPhone,
        array $candidateLeadIds,
        ?string $botPhoneId = null
    ): void {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $value = !empty($candidateLeadIds) ? json_encode($candidateLeadIds, JSON_UNESCAPED_UNICODE) : '';
        $redis->hset($redis->getScopedKey($hashKey), 'pendingCandidateLeadIds', $value);
        $this->refreshSessionTTL($redis, $customerPhone, $botPhoneId);
    }


    /**
     * Obtiene el update pendiente
     */
    public function getPendingUpdate(int $clientId, string $customerPhone, ?string $botPhoneId = null): ?array
    {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return null;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $scopedKey = $redis->getScopedKey($hashKey);
        $field = $redis->hget($scopedKey, 'pendingUpdateField');
        $value = $redis->hget($scopedKey, 'pendingUpdateValue');
        if (empty($field) || $value === null || $value === '') {
            return null;
        }
        return ['field' => $field, 'value' => $value];
    }


    /**
     * Establece el update pendiente
     */
    public function setPendingUpdate(
        int $clientId,
        string $customerPhone,
        ?string $field,
        ?string $value,
        ?string $botPhoneId = null
    ): void {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $scopedKey = $redis->getScopedKey($hashKey);

        $redis->hset($scopedKey, 'pendingUpdateField', $field ?? '');
        $redis->hset($scopedKey, 'pendingUpdateValue', $value ?? '');
        $this->refreshSessionTTL($redis, $customerPhone, $botPhoneId);
    }


    /**
     * Limpia el update pendiente
     */
    public function clearPendingUpdate(int $clientId, string $customerPhone, ?string $botPhoneId = null): void
    {
        $this->setPendingUpdate($clientId, $customerPhone, null, null, $botPhoneId);
    }


    /**
     * Verifica si hay un update pendiente
     */
    public function hasPendingUpdate(int $clientId, string $customerPhone, ?string $botPhoneId = null): bool
    {
        return $this->getPendingUpdate($clientId, $customerPhone, $botPhoneId) !== null;
    }


    // =========================================================================
    // STATUS SUGGESTIONS CONTEXT
    // Redis: pending_status_suggestions (array de IDs de status sugeridos)
    // =========================================================================

    public function getPendingStatusSuggestions(
        int $clientId,
        string $customerPhone,
        ?string $botPhoneId = null
    ): array {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return [];
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $value = $redis->hget($redis->getScopedKey($hashKey), 'pending_status_suggestions');

        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }


    public function setPendingStatusSuggestions(
        int $clientId,
        string $customerPhone,
        array $statusIds,
        ?string $botPhoneId = null
    ): void {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $value = !empty($statusIds) ? json_encode($statusIds, JSON_UNESCAPED_UNICODE) : '';
        $redis->hset($redis->getScopedKey($hashKey), 'pending_status_suggestions', $value);
        $this->refreshSessionTTL($redis, $customerPhone, $botPhoneId);
    }


    public function clearPendingStatusSuggestions(
        int $clientId,
        string $customerPhone,
        ?string $botPhoneId = null
    ): void {
        $this->setPendingStatusSuggestions($clientId, $customerPhone, [], $botPhoneId);
    }


    // =========================================================================
    // NOTES CONTEXT
    // Redis: notes_subState, notes_pendingIds, notes_selectedNoteId
    // =========================================================================

    public function getNotesSubState(int $clientId, string $customerPhone, ?string $botPhoneId = null): string
    {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return self::NOTES_SUB_LISTING;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $value = $redis->hget($redis->getScopedKey($hashKey), 'notes_subState');

        return $value !== null && $value !== '' ? $value : self::NOTES_SUB_LISTING;
    }

    public function setNotesSubState(
        int $clientId,
        string $customerPhone,
        string $subState,
        ?string $botPhoneId = null
    ): void {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $redis->hset($redis->getScopedKey($hashKey), 'notes_subState', $subState);
        $this->refreshSessionTTL($redis, $customerPhone, $botPhoneId);
    }

    /**
     * Obtiene los IDs de notas mostradas (para selección por índice)
     */
    public function getNotesPendingIds(int $clientId, string $customerPhone, ?string $botPhoneId = null): array
    {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return [];
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $value = $redis->hget($redis->getScopedKey($hashKey), 'notes_pendingIds');

        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Establece los IDs de notas mostradas
     */
    public function setNotesPendingIds(
        int $clientId,
        string $customerPhone,
        array $noteIds,
        ?string $botPhoneId = null
    ): void {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $value = !empty($noteIds) ? json_encode($noteIds, JSON_UNESCAPED_UNICODE) : '';
        $redis->hset($redis->getScopedKey($hashKey), 'notes_pendingIds', $value);
        $this->refreshSessionTTL($redis, $customerPhone, $botPhoneId);
    }

    /**
     * Obtiene el ID de la nota seleccionada (cuando se está viendo una nota completa)
     */
    public function getNotesSelectedNoteId(int $clientId, string $customerPhone, ?string $botPhoneId = null): ?int
    {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return null;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $value = $redis->hget($redis->getScopedKey($hashKey), 'notes_selectedNoteId');

        return $value !== null && $value !== '' ? (int) $value : null;
    }

    /**
     * Establece la nota seleccionada (viendo nota completa)
     */
    public function setNotesSelectedNoteId(
        int $clientId,
        string $customerPhone,
        ?int $noteId,
        ?string $botPhoneId = null
    ): void {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $redis->hset($redis->getScopedKey($hashKey), 'notes_selectedNoteId', $noteId ?? '');
        $this->refreshSessionTTL($redis, $customerPhone, $botPhoneId);
    }

    /**
     * Limpia el contexto de notas y vuelve a READY
     */
    public function clearNotesContext(int $clientId, string $customerPhone, ?string $botPhoneId = null): void
    {
        $this->setNotesPendingIds($clientId, $customerPhone, [], $botPhoneId);
        $this->setNotesSelectedNoteId($clientId, $customerPhone, null, $botPhoneId);
        $this->setNotesSubState($clientId, $customerPhone, self::NOTES_SUB_LISTING, $botPhoneId);
        $this->setConversationStatus($clientId, $customerPhone, self::STATUS_READY, $botPhoneId);
    }

    // =========================================================================
    // SALE CONTEXT
    // Redis: sale_subState, sale_leadId
    // =========================================================================

    public function getSaleSubState(int $clientId, string $customerPhone, ?string $botPhoneId = null): string
    {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return self::SALE_SUB_CONFIRMING;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $value = $redis->hget($redis->getScopedKey($hashKey), 'sale_subState');

        return $value !== null && $value !== '' ? $value : self::SALE_SUB_CONFIRMING;
    }

    public function setSaleSubState(
        int $clientId,
        string $customerPhone,
        string $subState,
        ?string $botPhoneId = null
    ): void {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $redis->hset($redis->getScopedKey($hashKey), 'sale_subState', $subState);
        $this->refreshSessionTTL($redis, $customerPhone, $botPhoneId);
    }

    public function getSaleLeadId(int $clientId, string $customerPhone, ?string $botPhoneId = null): ?int
    {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return null;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $value = $redis->hget($redis->getScopedKey($hashKey), 'sale_leadId');

        return $value !== null && $value !== '' ? (int) $value : null;
    }

    public function setSaleLeadId(
        int $clientId,
        string $customerPhone,
        ?int $leadId,
        ?string $botPhoneId = null
    ): void {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $redis->hset($redis->getScopedKey($hashKey), 'sale_leadId', $leadId ?? '');
        $this->refreshSessionTTL($redis, $customerPhone, $botPhoneId);
    }

    public function clearSaleContext(int $clientId, string $customerPhone, ?string $botPhoneId = null): void
    {
        $this->setSaleLeadId($clientId, $customerPhone, null, $botPhoneId);
        $this->setSaleSubState($clientId, $customerPhone, self::SALE_SUB_CONFIRMING, $botPhoneId);
        $this->setConversationStatus($clientId, $customerPhone, self::STATUS_READY, $botPhoneId);
    }


    // =========================================================================
    // TASK CONTEXT
    // Redis: pendingTaskIds, activeTaskId, task_subState
    // =========================================================================

    /**
     * Obtiene los IDs de tareas pendientes de selección
     */
    public function getPendingCandidateTaskIds(
        int $clientId,
        string $customerPhone,
        ?string $botPhoneId = null
    ): array {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return [];
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $value = $redis->hget($redis->getScopedKey($hashKey), 'pendingTaskIds');

        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }


    /**
     * Establece los IDs de tareas pendientes
     */
    public function setPendingTaskIds(
        int $clientId,
        string $customerPhone,
        array $taskIds,
        ?string $botPhoneId = null
    ): void {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $value = !empty($taskIds) ? json_encode($taskIds, JSON_UNESCAPED_UNICODE) : '';
        $redis->hset($redis->getScopedKey($hashKey), 'pendingTaskIds', $value);
        $this->refreshSessionTTL($redis, $customerPhone, $botPhoneId);
    }


    /**
     * Obtiene el ID de la tarea activa
     */
    public function getActiveTaskId(int $clientId, string $customerPhone, ?string $botPhoneId = null): ?int
    {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return null;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $value = $redis->hget($redis->getScopedKey($hashKey), 'activeTaskId');

        return $value !== null && $value !== '' ? (int) $value : null;
    }


    /**
     * Establece la tarea activa (limpia tareas pendientes de selección)
     */
    public function setActiveTaskId(
        int $clientId,
        string $customerPhone,
        ?int $taskId,
        ?string $botPhoneId = null
    ): void {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $scopedHashKey = $redis->getScopedKey($hashKey);

        $redis->hset($scopedHashKey, 'activeTaskId', $taskId ?? '');
        $this->setPendingTaskIds($clientId, $customerPhone, [], $botPhoneId);
        $this->refreshSessionTTL($redis, $customerPhone, $botPhoneId);
    }

    public function getTaskSubState(int $clientId, string $customerPhone, ?string $botPhoneId = null): string
    {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return self::TASK_SUB_LISTING;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $value = $redis->hget($redis->getScopedKey($hashKey), 'task_subState');

        return $value !== null && $value !== '' ? $value : self::TASK_SUB_LISTING;
    }

    public function setTaskSubState(
        int $clientId,
        string $customerPhone,
        string $subState,
        ?string $botPhoneId = null
    ): void {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $redis->hset($redis->getScopedKey($hashKey), 'task_subState', $subState);
        $this->refreshSessionTTL($redis, $customerPhone, $botPhoneId);
    }

    public function clearTaskContext(int $clientId, string $customerPhone, ?string $botPhoneId = null): void
    {
        $this->setPendingTaskIds($clientId, $customerPhone, [], $botPhoneId);
        $this->setActiveTaskId($clientId, $customerPhone, null, $botPhoneId);
        $this->setTaskSubState($clientId, $customerPhone, self::TASK_SUB_LISTING, $botPhoneId);
        $this->setConversationStatus($clientId, $customerPhone, self::STATUS_READY, $botPhoneId);
    }

    // =========================================================================
    // SESSION CORE / CONVERSATION STATUS, WORKFLOW AND CANCELATION
    // =========================================================================

    /**
     * Obtiene el estado de la conversación (READY, AWAITING_SELECTION, AWAITING_CONFIRMATION)
     */
    public function getConversationStatus(int $clientId, string $customerPhone, string $botPhoneId): string
    {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return self::STATUS_READY;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $value = $redis->hget($redis->getScopedKey($hashKey), 'conversationStatus');

        return $value ?: self::STATUS_READY;
    }


    /**
     * Establece el estado de la conversación
     */
    public function setConversationStatus(
        int $clientId,
        string $customerPhone,
        string $status,
        ?string $botPhoneId = null
    ): void {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $redis->hset($redis->getScopedKey($hashKey), 'conversationStatus', $status);
        $this->refreshSessionTTL($redis, $customerPhone, $botPhoneId);
    }


    /**
     * Obtiene el workflow actual (steps + next_step_index)
     */
    public function getWorkflow(int $clientId, string $customerPhone, ?string $botPhoneId = null): ?array
    {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return null;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $value = $redis->hget($redis->getScopedKey($hashKey), 'workflow');

        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }


    /**
     * Establece el workflow completo
     */
    public function setWorkflow(
        int $clientId,
        string $customerPhone,
        array $workflow,
        ?string $botPhoneId = null
    ): void {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $value = json_encode($workflow, JSON_UNESCAPED_UNICODE);
        $redis->hset($redis->getScopedKey($hashKey), 'workflow', $value);
        $this->refreshSessionTTL($redis, $customerPhone, $botPhoneId);
    }


    /**
     * Actualiza el índice del próximo step a ejecutar
     */
    public function setWorkflowNextStepIndex(
        int $clientId,
        string $customerPhone,
        int $nextStepIndex,
        ?string $botPhoneId = null
    ): void {
        $workflow = $this->getWorkflow($clientId, $customerPhone, $botPhoneId);
        if ($workflow) {
            $workflow['next_step_index'] = $nextStepIndex;
            $this->setWorkflow($clientId, $customerPhone, $workflow, $botPhoneId);
        }
    }


    /**
     * Actualiza los params de un step específico del workflow.
     */
    public function updateWorkflowStepParams(
        int $clientId,
        string $customerPhone,
        int $stepIndex,
        array $newParams,
        ?string $botPhoneId = null
    ): void {
        $workflow = $this->getWorkflow($clientId, $customerPhone, $botPhoneId);
        if (!$workflow || !isset($workflow['steps'][$stepIndex])) {
            return;
        }

        $workflow['steps'][$stepIndex]['params'] = array_merge(
            $workflow['steps'][$stepIndex]['params'] ?? [],
            $newParams
        );
        $this->setWorkflow($clientId, $customerPhone, $workflow, $botPhoneId);
    }


    /**
     * Limpia el workflow
     */
    public function clearWorkflow(int $clientId, string $customerPhone, ?string $botPhoneId = null): void
    {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $redis->hset($redis->getScopedKey($hashKey), 'workflow', '');
        $this->refreshSessionTTL($redis, $customerPhone, $botPhoneId);
    }

    /**
     * Cancela la selección en curso (AWAITING_SELECTION) y limpia el estado.
     * Retorna el mensaje a mostrar al usuario.
     */
    public function cancelSelection(
        int $clientId,
        string $customerPhone,
        ?string $botPhoneId = null
    ): string {
        $this->setPendingCandidateLeadIds($clientId, $customerPhone, [], $botPhoneId);
        $this->clearTaskContext($clientId, $customerPhone, $botPhoneId);
        $this->clearWorkflow($clientId, $customerPhone, $botPhoneId);
        $this->setConversationStatus($clientId, $customerPhone, self::STATUS_READY, $botPhoneId);

        return $this->getCancelledMessage();
    }

    // =========================================================================
    // USER-FACING MESSAGES
    // Helpers de texto compartidos entre Job y Service.
    // =========================================================================

    /**
     * Mensaje cuando se ejecuta el comando /clear o /limpiar.
     */
    public function getClearedConversationMessage(): string
    {
        return '✅ Se limpió la conversación. El contexto ha sido reseteado. Puedes comenzar nuevamente.';
    }

    /**
     * Mensaje para pedir al usuario que seleccione con un número (1, 2, 3, etc.)
     */
    public function getSelectByNumberOrCancelMessage(): string
    {
        return '🤖 Respondé con el número (1, 2, 3, etc.) para seleccionar o escribí "cancelar" para salir.';
    }

    public function getTaskListHelpMessage(): string
    {
        return <<<'TEXT'
            🤖 ¿Qué querés hacer?

            • Ver tarea: escribí su número ej: "2"
            • Crear tarea: "crear tarea [texto]" ej: "crear tarea llamar el lunes que venza en 1 mes"
            • Volver: escribí "volver"
            TEXT;
    }

    /**
     * Mensaje cuando el usuario selecciona un índice inválido de la lista.
     */
    public function getInvalidSelectionMessage(): string
    {
        return '❌ Selección inválida. Por favor elegí un número válido de la lista.';
    }

    /**
     * Mensaje para pedir al usuario que confirme o cancele con "sí" o "no".
     */
    public function getPromptConfirmationMessage(): string
    {
        return '🤖 Por favor respondé "si" para confirmar o "no" para cancelar.';
    }

    /**
     * Mensaje cuando el usuario cancela una operación pendiente de confirmación.
     */
    public function getCancelledMessage(): string
    {
        return '🤖 Operación cancelada. ¿En qué puedo ayudarte?';
    }

    /**
     * Mensaje para solicitar los datos de la venta (monto + descripción opcional).
     */
    public function getSaleCollectingMessage(): string
    {
        return <<<'TEXT'
            💰 Para registrar la venta, necesito el monto.

            📌 Monto (obligatorio)
            📝 Descripción (opcional)

            Podés enviar solo el monto o el monto seguido de una descripción.
            Ejemplo: "15000" o "15000 Venta de servicio premium"

            Escribí "cancelar" para omitir la venta.
        TEXT;
    }

    /**
     * Formatea el mensaje de info faltante para enviar al usuario.
     *
     * @param  string[]  $missing  Lista de mensajes descriptivos de info faltante
     */
    public function formatMissingInfoMessage(array $missing): string
    {
        $message = "🤖 Necesito un poco más de info:\n\n";

        foreach ($missing as $item) {
            $message .= "• {$item}\n";
        }

        // $message .= "\nEscribí \"cancelar\" para volver a empezar.";

        return $message;
    }

    /**
     * Mensaje para rutas no operacionales: help, smalltalk, unknown.
     *
     * @param  string  $route  'help' | 'smalltalk' | 'unknown' (o cualquier otro para default)
     */
    public function getNonOperationalMessage(string $route): string
    {
        return match ($route) {
            'help' => <<<TEXT
                🤖 Soy tu asistente de Clienty CRM. Puedo ayudarte a:

                • Buscar prospectos por nombre, email, teléfono o ID
                • Actualizar datos de prospectos (nombre, apellido, estado)
                • Ver y gestionar tareas
                • Crear nuevas tareas

                ¿Qué te gustaría hacer?
            TEXT,
            'smalltalk' => <<<TEXT
                🤖 Hola! Decime qué necesitás hacer y te ayudo.
                👉 Podés buscar prospectos, actualizar sus datos o gestionar tareas.
            TEXT,
            default =>
            $msg = <<<TEXT
                🤖 No entendí tu mensaje. ¿Podés ser más específico?
                👉 Puedo buscar prospectos, actualizar datos o gestionar tareas.
            TEXT,
        };
    }

    /**
     * Mensaje cuando el lead seleccionado no se encuentra en la base de datos.
     */
    public function getLeadNotFoundMessage(): string
    {
        return '❌ Prospecto seleccionado pero no encontrado.';
    }

    /**
     * Mensaje cuando la tarea seleccionada no se encuentra.
     */
    public function getTaskNotFoundMessage(): string
    {
        return '❌ Tarea seleccionada pero no encontrada.';
    }

    /**
     * Mensaje cuando se selecciona una tarea correctamente (incluye el detalle formateado).
     */
    public function getTaskSelectedMessage(string $taskInfo): string
    {
        return "📄 Detalle de la tarea:\n\n" . $taskInfo;
    }

    /**
     * Agrega la lista de candidatos al mensaje y el pie de selección.
     * @param  array<LeadCandidateDTO|TaskCandidateDTO>|null  $candidatesDTO
     */
    public function addCandidatesToMessage(string $message, ?array $candidatesDTO): string
    {
        if (empty($candidatesDTO)) {
            return $message;
        }

        $firstCandidate = $candidatesDTO[0] ?? null;

        if ($firstCandidate instanceof TaskCandidateDTO) {
            return $this->addTaskCandidatesToMessage($message, $candidatesDTO);
        }

        return $this->addLeadCandidatesToMessage($message, $candidatesDTO);
    }

    /**
     * @param  array<LeadCandidateDTO>  $candidatesDTO
     */
    private function addLeadCandidatesToMessage(string $message, array $candidatesDTO): string
    {
        $responseText = $message . "\n\n";

        foreach ($candidatesDTO as $index => $candidate) {
            $numberPos = $index + 1;
            $leadId = $candidate->id;
            $email = $candidate->email ?? '[email vacío]';
            $phone = $candidate->phone ?? '[teléfono vacío]';
            $statusName = $candidate->statusName ?? '[estado vacío]';
            $fullName = $candidate->fullName ?? '[nombre vacío]';

            $responseText .= <<<TEXT
                {$numberPos}. {$fullName} (ID: {$leadId})
                📧 {$email}  
                📱 {$phone}  
                📌 Estado: {$statusName}\n\n
            TEXT;
        }

        $responseText .= $this->getSelectByNumberOrCancelMessage();

        return $responseText;
    }

    /**
     * @param  array<TaskCandidateDTO>  $candidatesDTO
     */
    private function addTaskCandidatesToMessage(string $message, array $candidatesDTO): string
    {
        $responseText = $message . "\n\n";

        foreach ($candidatesDTO as $index => $candidate) {
            $numberPos = $index + 1;
            $taskId = $candidate->id;
            $client = $candidate->client;
            $userId = $candidate->userId;
            $leadId = $candidate->leadId;
            $title = $candidate->title ?? '-';
            $userName = $candidate->userName ?? '-';
            $leadName = $candidate->leadName ?? '-';
            $importantLegend = $candidate->isImportant ? '(Es importante ⭐)' : '';
            $statusLegend = $candidate->status === 'completed' ? 'Completada ✅' : 'Pendiente 🟡';

            $clientTz = new DateTimeZone($client->timezone);
            $limitDate = (new DateTime($candidate->limitDate))->setTimezone($clientTz);
            $limitDateStr = $candidate->limitDate->format('d/m/Y H:i') . ' hs.';
            $isExpired = $limitDate < new DateTime('now', $clientTz);
            $expirationLegend = $isExpired ? "Venció: {$limitDateStr}" : "Vence: {$limitDateStr}";

            $responseText .= <<<TEXT
                {$numberPos}. {$title} (ID: {$taskId}) {$importantLegend}
                🗓  {$expirationLegend}
                📌 Estado: {$statusLegend}
                👤 Usuario: {$userName} (ID: {$userId})
                👤 Prospecto: {$leadName} (ID: {$leadId})\n\n
            TEXT;
        }

        $responseText .= $this->getTaskListHelpMessage();

        return $responseText;
    }

    public function areTaskCandidates(?array $candidatesDTO): bool
    {
        if (empty($candidatesDTO)) {
            return false;
        }

        return ($candidatesDTO[0] ?? null) instanceof TaskCandidateDTO;
    }

    public function areLeadCandidates(?array $candidatesDTO): bool
    {
        if (empty($candidatesDTO)) {
            return false;
        }

        return ($candidatesDTO[0] ?? null) instanceof LeadCandidateDTO;
    }

    // =========================================================================
    // SESSION CORE / VALIDATOR CONTEXT
    // =========================================================================

    /**
     * Obtiene el contexto del validador (domains + conversation history)
     *
     * @return array{domains: string[], conversation: array<array{role: string, content: string}>}|null
     */
    public function getValidatorContext(int $clientId, string $customerPhone, ?string $botPhoneId = null): ?array
    {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return null;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $value = $redis->hget($redis->getScopedKey($hashKey), 'validatorContext');

        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }


    /**
     * Almacena el contexto del validador (domains + conversation history)
     */
    public function setValidatorContext(
        int $clientId,
        string $customerPhone,
        array $context,
        ?string $botPhoneId = null
    ): void {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $value = json_encode($context, JSON_UNESCAPED_UNICODE);
        $redis->hset($redis->getScopedKey($hashKey), 'validatorContext', $value);
        $this->refreshSessionTTL($redis, $customerPhone, $botPhoneId);
    }


    /**
     * Limpia el contexto del validador
     */
    public function clearValidatorContext(int $clientId, string $customerPhone, ?string $botPhoneId = null): void
    {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $redis->hset($redis->getScopedKey($hashKey), 'validatorContext', '');
        $this->refreshSessionTTL($redis, $customerPhone, $botPhoneId);
    }


    // =========================================================================
    // SESSION CORE / OPENAI HISTORY
    // =========================================================================

    /**
     * Obtiene el historial de OpenAI
     */
    public function getOpenAIHistory(int $clientId, string $customerPhone, ?string $botPhoneId = null): array
    {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return [];
        }

        $listKey = $this->buildHistoryListKey($customerPhone, $botPhoneId);
        $messages = $redis->lrange($redis->getScopedKey($listKey), 0, -1);

        $history = [];
        foreach ($messages as $messageJson) {
            $decoded = json_decode($messageJson, true);
            if (is_array($decoded)) {
                $history[] = $decoded;
            }
        }

        return $history;
    }

    /**
     * Agrega un mensaje del usuario al historial
     */
    public function addUserMessage(
        int $clientId,
        string $customerPhone,
        string $message,
        string $messageType = 'text',
        array $context = [],
        ?string $botPhoneId = null
    ): void {
        $this->addHistoryEntry($clientId, $customerPhone, [
            'role' => 'user',
            'message' => $message,
            'context' => $context,
            'message_type' => $messageType,
        ], $botPhoneId);
    }

    /**
     * Agrega un mensaje del asistente al historial
     */
    public function addAssistantMessage(
        int $clientId,
        string $customerPhone,
        string $message,
        string $messageType = 'assistant_text',
        array $context = [],
        ?array $resultData = null,
        ?string $botPhoneId = null
    ): void {
        $entry = [
            'role' => 'assistant',
            'message' => $message,
            'context' => $context,
            'message_type' => $messageType,
        ];

        if ($resultData) {
            $entry['lead'] = $resultData['lead'] ?? null;
            $entry['candidates'] = $resultData['candidates'] ?? null;
            $entry['isError'] = $resultData['isError'] ?? false;
        }

        $this->addHistoryEntry($clientId, $customerPhone, $entry, $botPhoneId);
    }

    /**
     * Agrega la respuesta JSON del asistente al historial
     */
    public function addAssistantJsonResponse(
        int $clientId,
        string $customerPhone,
        array $payload,
        ?string $botPhoneId = null
    ): void {
        $this->addHistoryEntry($clientId, $customerPhone, [
            'role' => 'assistant',
            'message_type' => 'assistant_json',
            'message' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'context' => null,
        ], $botPhoneId);
    }

    // =========================================================================
    // LEAD CONTEXT / BUSINESS AND FORMATTERS
    // =========================================================================

    /**
     * Busca leads por diferentes criterios
     */
    public function searchLeads(Client $client, string $type, string $value): Collection
    {
        return match ($type) {
            'id' => $this->searchLeadById($client, (int) $value),
            'tag' => $this->searchLeadsByTag($client, $value),
            'name' => $this->searchLeadsByName($client, $value),
            'email' => $this->searchLeadsByEmail($client, $value),
            'phone' => $this->searchLeadsByPhone($client, $value),
            'status' => $this->searchLeadsByStatus($client, $value),
            'lastname' => $this->searchLeadsByName($client, $value),
            default => collect([]),
        };
    }

    /**
     * Actualiza un lead
     */
    public function updateLead(Client $client, Lead $lead, string $field, string $value): Lead
    {
        if ($lead->client_id !== $client->id) {
            throw new Exception('El prospecto no pertenece a este cliente');
        }

        return match ($field) {
            'name' => $this->updateLeadName($lead, $value),
            'lastname' => $this->updateLeadLastname($lead, $value),
            'email' => $this->updateLeadEmail($lead, $value),
            'phone' => $this->updateLeadPhone($lead, $value),
            'status' => $this->updateLeadStatus($client, $lead, $value),
            default => throw new Exception("Campo no permitido: {$field}"),
        };
    }

    /**
     * Cuerpo común: datos del lead + menú de acciones
     */
    private function formatLeadInfoBody(Lead $lead): string
    {
        $timezone = $lead->client->timezone;
        $email = $lead->main_email ?? '[email vacío]';
        $phone = $lead->main_phone ?? '[teléfono vacío]';
        $status = $lead->status->name ?? '-';
        $fullName = $lead->mainFullName ?? '-';
        $notesCount = $lead->notes_count ?? $lead->notes()->count();
        $tasksCount = $lead->tasks()
            ->where('status', 'pending')
            ->where('limit_date', '>=', now($timezone))
            ->count()
        ;

        return <<<TEXT
            {$fullName} (ID: {$lead->id})
            📧 {$email}
            📱 {$phone}
            📌 Estado: {$status}
            📝 Notas ({$notesCount})
            📋 Tareas ({$tasksCount})

            🤖 ¿Qué querés hacer?

            1. Cambiar nombre
            2. Cambiar apellido
            3. Cambiar email
            4. Cambiar teléfono
            5. Cambiar estado
            6. Ver estados
            7. Ver notas
            8. Crear nota
            9. Ver tareas
            10. Crear tarea
            11. Salir
        TEXT;
    }

    /**
     * Formatea la información de un lead para mostrar al usuario (búsqueda/selección)
     */
    public function formatLeadInfo(Lead $lead): string
    {
        return "👤 Prospecto encontrado:\n\n" . $this->formatLeadInfoBody($lead);
    }

    /**
     * Formatea solo el detalle del prospecto activo y su menú de acciones.
     */
    public function formatLeadActionMenu(Lead $lead): string
    {
        return $this->formatLeadInfoBody($lead);
    }

    /**
     * Formatea la información de un lead actualizado (tras confirmar cambio)
     */
    public function formatUpdatedLeadInfo(Lead $lead): string
    {
        return "👤✅ Prospecto actualizado\n\n" . $this->formatLeadInfoBody($lead);
    }

    /**
     * Formatea la información de un lead actualizado sin incluir el menú de acciones.
     * Útil cuando el siguiente estado conversacional no acepta las opciones numeradas.
     */
    public function formatUpdatedLeadSummary(Lead $lead): string
    {
        $email = $lead->main_email ?? '[email vacío]';
        $phone = $lead->main_phone ?? '[teléfono vacío]';
        $status = $lead->status->name ?? '-';
        $fullName = $lead->mainFullName ?? '-';

        return <<<TEXT
            👤✅ Prospecto actualizado

            {$fullName} (ID: {$lead->id})
            📧 {$email}
            📱 {$phone}
            📌 Estado: {$status}
        TEXT;
    }

    /**
     * Formatea los datos de un lead como array
     */
    public function formatLeadData(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'email' => $lead->main_email ?? null,
            'phone' => $lead->main_phone ?? null,
            'status' => $lead->status->name ?? null,
            'name' => $lead->mainLeadContact->name ?? null,
            'lastname' => $lead->mainLeadContact->last_name ?? null,
        ];
    }

    /**
     * Construye el preview de una actualización pendiente
     */
    public function buildUpdatePreview(Lead $lead, string $field, string $newValue): string
    {
        $fieldLabel = match ($field) {
            'name' => 'Nombre',
            'lastname' => 'Apellido',
            'email' => 'Email',
            'phone' => 'Teléfono',
            'status' => 'Estado',
            default => ucfirst($field),
        };

        $currentValue = match ($field) {
            'name' => $lead->mainLeadContact->name ?? '[nombre vacío]',
            'lastname' => $lead->mainLeadContact->last_name ?? '[apellido vacío]',
            'email' => $lead->main_email ?? '[email vacío]',
            'phone' => $lead->main_phone ?? '[teléfono vacío]',
            'status' => $lead->status->name ?? '[estado vacío]',
            default => 'N/A',
        };

        return <<<TEXT
            📋 Confirmación de cambio

            👤 Prospecto: {$lead->mainFullName} (ID: {$lead->id})
            
            Campo: {$fieldLabel}
            Valor actual: {$currentValue}
            Nuevo valor: {$newValue}

            ❗¿Confirmás este cambio?
            Respondé "si" para confirmar o "no" para cancelar.
        TEXT;
    }

    /**
     * Formatea candidatos como DTOs para mostrar en lista de selección
     *
     * @return LeadCandidateDTO[]
     */
    public function formatLeadCandidates(Collection $leads): array
    {
        return $leads->map(fn($lead) => LeadCandidateDTO::build([
            'id' => $lead->id,
            'email' => $lead->main_email ?? null,
            'phone' => $lead->main_phone ?? null,
            'fullName' => $lead->mainFullName ?? null,
            'statusName' => $lead->status->name ?? null,
            'name' => $lead->mainLeadContact->name ?? null,
            'lastName' => $lead->mainLeadContact->last_name ?? null,
        ]))->values()->all();
    }

    /**
     * Formatea candidatos de tareas como DTOs para mostrar en lista de selección
     *
     * @return TaskCandidateDTO[]
     */
    public function formattedTaskList(Collection $tasks): array
    {
        return $tasks->map(fn($task) => TaskCandidateDTO::build([
            'id' => $task->id,
            'taskId' => $task->id,
            'title' => $task->title,
            'client' => $task->client,
            'status' => $task->status,
            'leadId' => $task->lead_id,
            'userId' => $task->user_id,
            'limitDate' => $task->limit_date,
            'userName' => $task->user->fullName,
            'isImportant' => $task->is_important,
            'leadName' => $task->lead->mainFullName,
        ]))->values()->all();
    }

    // =========================================================================
    // ACTION DISPATCHER
    // Punto de entrada común para ejecutar acciones del agente.
    // =========================================================================

    /**
     * Ejecuta una acción del agente de ventas.
     */
    public function executeAction(
        Client $client,
        User $user,
        string $customerPhone,
        string $actionType,
        ?string $botPhoneId = null,
        string $message = '',
        array $params = []
    ): array {
        if ($actionType === '') {
            // Sin acción (saludos, ayuda, etc.) - solo devolver el mensaje
            return [
                'lead' => null,
                'candidates' => null,
                'message' => $message ?: '🤖 No entiendo qué quieres hacer.',
            ];
        }

        try {
            return match ($actionType) {
                'lead_search' => $this->handleSearch(
                    $client, $params, $message, $customerPhone, $botPhoneId
                ),
                'lead_select' => $this->handleSelect(
                    $client, $params, $message, $customerPhone, $botPhoneId
                ),
                'preview_lead_update' => $this->handlePreviewUpdate(
                    $client, $params, $message, $customerPhone, $botPhoneId
                ),
                'confirm_lead_update' => $this->handleConfirmUpdate(
                    $client, $params, $message, $customerPhone, $botPhoneId
                ),
                'reject_lead_update' => $this->handleRejectUpdate(
                    $client, $params, $message, $customerPhone, $botPhoneId
                ),
                'list_user_tasks' => $this->handleListUserTasks(
                    $client, $user, $params, $message, $customerPhone, $botPhoneId
                ),
                'list_lead_tasks' => $this->handleListLeadTasks(
                    $client, $params, $message, $customerPhone, $botPhoneId
                ),
                'view_task' => $this->handleViewTask(
                    $client, $params, $message, $customerPhone, $botPhoneId
                ),
                'update_task' => $this->handleUpdateTask(
                    $client, $user, $params, $message, $customerPhone, $botPhoneId
                ),
                'create_task' => $this->handleCreateTask(
                    $client, $user, $params, $message, $customerPhone, $botPhoneId
                ),
                'list_notes' => $this->handleListNotes(
                    $client, $user, $params, $message, $customerPhone, $botPhoneId
                ),
                'view_note' => $this->handleViewNote(
                    $client, $params, $message, $customerPhone, $botPhoneId
                ),
                'create_note' => $this->handleCreateNote(
                    $client, $user, $params, $message, $customerPhone, $botPhoneId
                ),
                'delete_note' => $this->handleDeleteNote(
                    $client, $params, $message, $customerPhone, $botPhoneId
                ),
                'back_to_notes_list' => $this->handleBackToNotesList(
                    $client, $params, $message, $customerPhone, $botPhoneId
                ),
                'lead_search_by_date' => $this->handleSearchByDate(
                    $client, $params, $message, $customerPhone, $botPhoneId
                ),
                default => [
                    'lead' => null,
                    'candidates' => null,
                    'message' => $message ?: '🤖 No entiendo esa acción.',
                ],
            };
        } catch (Exception $e) {
            return [
                'lead' => null,
                'isError' => true,
                'candidates' => null,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    // =========================================================================
    // LEAD ACTION HANDLERS
    // =========================================================================

    private function handleSearch(
        Client $client,
        array $params,
        string $message,
        string $customerPhone,
        ?string $botPhoneId
    ): array {
        $clientId = $client->id;
        $searchType = $params['searchType'] ?? 'name';
        $searchValue = $params['searchValue'] ?? '';

        if (empty($searchValue)) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '🤖 No se proporcionó un valor para buscar.',
            ];
        }

        $leads = $this->searchLeads($client, $searchType, $searchValue);

        if ($leads->isEmpty()) {
            $this->setActiveLead($clientId, $customerPhone, null, $botPhoneId);
            $this->setPendingCandidateLeadIds($clientId, $customerPhone, [], $botPhoneId);
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '❌ No se encontraron prospectos con esos criterios.',
            ];
        }

        if ($leads->count() === 1) {
            $lead = $leads->first();
            $this->setActiveLead($clientId, $customerPhone, $lead->id, $botPhoneId);
            $this->setPendingCandidateLeadIds($clientId, $customerPhone, [], $botPhoneId);

            return [
                'candidates' => null,
                'lead' => $this->formatLeadData($lead),
                'message' => $this->formatLeadInfo($lead),
            ];
        }

        // Múltiples resultados
        $candidateLeadIds = $leads->pluck('id')->toArray();
        $this->setPendingCandidateLeadIds($clientId, $customerPhone, $candidateLeadIds, $botPhoneId);

        return [
            'lead' => null,
            'candidates' => $this->formatLeadCandidates($leads),
            'message' => "👥 Se encontraron {$leads->count()} prospectos. Selecciona uno:",
        ];
    }


    private function handleSearchByDate(
        Client $client,
        array $params,
        string $message,
        string $customerPhone,
        ?string $botPhoneId
    ): array {
        $dateNow = new DateTime('now');
        
        // Llega el día, ej: 2026-02-15
        $dateStartStr = $params['dateStart'] ?? null;
        $dateEndStr = $params['dateEnd'] ?? $dateNow->format('Y-m-d');

        if (!$dateStartStr) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '🤖 No se proporcionó una fecha para buscar.',
            ];
        }

        $dateEnd = (new DateTime($dateEndStr))->setTime(23, 59, 59);
        $dateStart = (new DateTime($dateStartStr))->setTime(0, 0, 0);

        if ($dateStart > $dateEnd) {
            [$dateStart, $dateEnd] = [$dateEnd, $dateStart];
        }
        
        $dateEndLegendStr = (new DateTime($dateEndStr))->format('d/m/Y');
        $dateStartLegendStr = (new DateTime($dateStartStr))->format('d/m/Y');
        
        $utcTz = new DateTimeZone('UTC');
        $clientTz = new DateTimeZone($client->timezone);
        $searchDateStart = (new DateTime($dateStartStr, $clientTz))->setTime(0, 0, 0)->setTimezone($utcTz);
        $searchDateEnd = (new DateTime($dateEndStr, $clientTz))->setTime(23, 59, 59)->setTimezone($utcTz);

        $this->leadServiceView->setClient($client);

        $opts = [
            'page' => 1,
            'limit' => 10,
            'sort' => 'date_desc',
            'with' => [
                'mainLeadContact.leadContactEmails',
                'mainLeadContact.leadContactPhones',
                'status',
            ],
            'filters' => [
                'created_date_end' => "{$searchDateEnd->format('Y-m-d H:i:s')}",
                'created_date_start' => "{$searchDateStart->format('Y-m-d H:i:s')}",
            ],
        ];

        $this->logInfo("<<<<< OPTS >>>>>: " .
            json_encode($opts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        $result = $this->leadServiceView->list($opts);

        $leads = $result->getCollection()->loadCount('notes');

        if ($leads->isEmpty()) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => "❌ No se encontraron prospectos entre {$dateStartLegendStr} y {$dateEndLegendStr}.",
            ];
        }

        if ($leads->count() === 1) {
            $lead = $leads->first();
            $this->setActiveLead($client->id, $customerPhone, $lead->id, $botPhoneId);
            $this->setPendingCandidateLeadIds($client->id, $customerPhone, [], $botPhoneId);

            return [
                'candidates' => null,
                'lead' => $this->formatLeadData($lead),
                'message' => $this->formatLeadInfo($lead),
            ];
        }

        $candidateLeadIds = $leads->pluck('id')->toArray();
        $this->setPendingCandidateLeadIds($client->id, $customerPhone, $candidateLeadIds, $botPhoneId);

        $leadsCount = $leads->count();
        $message = <<<TEXT
            👥 Se encontraron {$leadsCount} prospectos entre {$dateStartLegendStr} y {$dateEndLegendStr}.
            Selecciona uno:
        TEXT;

        return [
            'lead' => null,
            'candidates' => $this->formatLeadCandidates($leads),
            'message' => $message,
        ];
    }


    private function handleSelect(
        Client $client,
        array $params,
        string $message,
        string $customerPhone,
        ?string $botPhoneId
    ): array {
        $clientId = $client->id;
        $selectedIndex = $params['selectedIndex'] ?? null;

        $pendingCandidates = $this->getPendingCandidateLeadIds($clientId, $customerPhone, $botPhoneId);

        if (empty($pendingCandidates)) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '❌ No hay candidatos pendientes para seleccionar.',
            ];
        }

        if ($selectedIndex === null || $selectedIndex < 0 || $selectedIndex >= count($pendingCandidates)) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => $this->getInvalidSelectionMessage(),
            ];
        }

        $selectedLeadId = $pendingCandidates[$selectedIndex];
        $lead = Lead::where('client_id', $clientId)
            ->where('id', $selectedLeadId)
            ->with(['mainLeadContact.leadContactEmails', 'mainLeadContact.leadContactPhones', 'status'])
            ->withCount('notes')
            ->first();

        if (!$lead) {
            $this->setActiveLead($clientId, $customerPhone, null, $botPhoneId);
            $this->setPendingCandidateLeadIds($clientId, $customerPhone, [], $botPhoneId);
            $this->clearPendingUpdate($clientId, $customerPhone, $botPhoneId);
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '❌ El prospecto seleccionado no existe o no pertenece a este cliente.',
            ];
        }

        $this->setActiveLead($clientId, $customerPhone, $lead->id, $botPhoneId);
        $this->setPendingCandidateLeadIds($clientId, $customerPhone, [], $botPhoneId);

        // Si hay pending update, mostrar preview
        if ($this->hasPendingUpdate($clientId, $customerPhone, $botPhoneId)) {
            $pendingUpdate = $this->getPendingUpdate($clientId, $customerPhone, $botPhoneId);
            $previewMessage = $this->buildUpdatePreview($lead, $pendingUpdate['field'], $pendingUpdate['value']);
            return [
                'lead' => null,
                'candidates' => null,
                'message' => $previewMessage,
            ];
        }

        return [
            'candidates' => null,
            'lead' => $this->formatLeadData($lead),
            'message' => $this->formatLeadInfo($lead),
        ];
    }

    private function handlePreviewUpdate(
        Client $client,
        array $params,
        string $message,
        string $customerPhone,
        ?string $botPhoneId
    ): array {
        $clientId = $client->id;
        $searchType = $params['searchType'] ?? null;
        $searchValue = $params['searchValue'] ?? '';
        $pendingUpdateField = $params['pendingUpdateField'] ?? null;
        $pendingUpdateValue = $params['pendingUpdateValue'] ?? null;

        $hasSearchParams = $searchType && !empty($searchValue);
        $hasValidUpdateValue = $pendingUpdateValue !== null && trim((string)$pendingUpdateValue) !== '';

        // Si falta el nuevo valor pero hay parámetros de búsqueda, tratarlo como búsqueda
        if (!$hasValidUpdateValue && $hasSearchParams) {
            return $this->handleSearch($client, $params, $message, $customerPhone, $botPhoneId);
        }

        // Validar campo y valor
        if (!$pendingUpdateField || !$hasValidUpdateValue) {
            $msg = <<<TEXT
                🤖 ¿Qué campo querés actualizar y cuál es el nuevo valor?
                👉 Podés cambiar: nombre, apellido, email, teléfono o estado.
            TEXT;
            return [
                'lead' => null,
                'message' => $msg,
                'candidates' => null,
            ];
        }

        $allowedFields = ['name', 'lastname', 'email', 'phone', 'status'];
        if (!in_array($pendingUpdateField, $allowedFields)) {
            $msg = <<<TEXT
                ❌ No podés modificar el campo "{$pendingUpdateField}".

                👉 Solo están permitidos:
                • nombre  
                • apellido  
                • email
                • teléfono
                • estado
            TEXT;
            return [
                'message' => $msg,
                'lead' => null,
                'candidates' => null,
            ];
        }

        // Validar formato de email si corresponde
        if ($pendingUpdateField === 'email' && !filter_var($pendingUpdateValue, FILTER_VALIDATE_EMAIL)) {
            $msg = <<<TEXT
                ❌ El email no tiene un formato válido: {$pendingUpdateValue}.
                👉 Por favor proporcioná un email válido (ej: nombre@dominio.com).
            TEXT;
            return [
                'message' => $msg,
                'lead' => null,
                'candidates' => null,
            ];
        }

        // Validar estado si corresponde
        if ($pendingUpdateField === 'status') {
            $status = $this->statusService->findOneByClientAndName($client, $pendingUpdateValue);
            if (!$status && is_numeric($pendingUpdateValue)) {
                $status = $this->statusService->findOneByClientAndId($client, (int) $pendingUpdateValue);
            }
            if (!$status) {
                return [
                    'lead' => null,
                    'candidates' => null,
                    'message' => '',
                    'needsStatusMatch' => true,
                    'statusInput' => $pendingUpdateValue,
                ];
            }
        }

        // Guardar pending update
        $this->setPendingUpdate($clientId, $customerPhone, $pendingUpdateField, $pendingUpdateValue, $botPhoneId);

        // Sin parámetros de búsqueda: usar prospecto activo
        if (!$hasSearchParams) {
            $activeLeadId = $this->getActiveLeadId($clientId, $customerPhone, $botPhoneId);

            if ($activeLeadId) {
                $lead = Lead::where('client_id', $clientId)
                    ->where('id', $activeLeadId)
                    ->with(['mainLeadContact.leadContactEmails', 'mainLeadContact.leadContactPhones', 'status'])
                    ->first();

                if ($lead) {
                    return [
                        'lead' => null,
                        'candidates' => null,
                        'message' => $this->buildUpdatePreview($lead, $pendingUpdateField, $pendingUpdateValue),
                    ];
                }
            }

            $this->clearPendingUpdate($clientId, $customerPhone, $botPhoneId);
            $msg = <<<TEXT
                ❌ No hay un prospecto seleccionado.
                👉 Por favor, primero buscá e identificá un prospecto por ID, nombre, email o teléfono.
            TEXT;
            return [
                'lead' => null,
                'message' => $msg,
                'candidates' => null,
            ];
        }

        // Con parámetros de búsqueda: buscar
        $leads = $this->searchLeads($client, $searchType, $searchValue);

        if ($leads->isEmpty()) {
            $this->clearPendingUpdate($clientId, $customerPhone, $botPhoneId);
            $this->setActiveLead($clientId, $customerPhone, null, $botPhoneId);
            $this->setPendingCandidateLeadIds($clientId, $customerPhone, [], $botPhoneId);
            $msg = <<<TEXT
                ❌ No encontré ningún prospecto con {$searchType}: {$searchValue}.
                👉 Por favor verifica los datos.
            TEXT;
            return [
                'lead' => null,
                'message' => $msg,
                'candidates' => null,
            ];
        }

        if ($leads->count() === 1) {
            $lead = $leads->first();
            $this->setActiveLead($clientId, $customerPhone, $lead->id, $botPhoneId);
            $this->setPendingCandidateLeadIds($clientId, $customerPhone, [], $botPhoneId);

            return [
                'lead' => null,
                'candidates' => null,
                'message' => $this->buildUpdatePreview($lead, $pendingUpdateField, $pendingUpdateValue),
            ];
        }

        // Múltiples resultados
        $candidateLeadIds = $leads->pluck('id')->toArray();
        $this->setPendingCandidateLeadIds($clientId, $customerPhone, $candidateLeadIds, $botPhoneId);

        $msg = $message
            ?: "📋 Se encontraron {$leads->count()} prospectos. Selecciona uno para aplicar el cambio:"
        ;
        return [
            'lead' => null,
            'message' => $msg,
            'candidates' => $this->formatLeadCandidates($leads),
        ];
    }


    private function handleConfirmUpdate(
        Client $client,
        array $params,
        string $message,
        string $customerPhone,
        ?string $botPhoneId
    ): array {
        $clientId = $client->id;
        $activeLeadId = $this->getActiveLeadId($clientId, $customerPhone, $botPhoneId);

        if (!$activeLeadId) {
            $msg = <<<TEXT
                ❌ No hay un prospecto identificado.
                👉 Por favor busca e identifica un prospecto primero.
            TEXT;
            return [
                'message' => $msg,
                'lead' => null,
                'candidates' => null,
            ];
        }

        if (!$this->hasPendingUpdate($clientId, $customerPhone, $botPhoneId)) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '❌ No hay ninguna actualización pendiente de confirmar.',
            ];
        }

        $lead = Lead::where('client_id', $clientId)
            ->where('id', $activeLeadId)
            ->with(['mainLeadContact.leadContactEmails', 'mainLeadContact.leadContactPhones', 'status'])
            ->first();

        if (!$lead) {
            $this->setActiveLead($clientId, $customerPhone, null, $botPhoneId);
            $this->clearPendingUpdate($clientId, $customerPhone, $botPhoneId);
            $this->setPendingCandidateLeadIds($clientId, $customerPhone, [], $botPhoneId);
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '❌ El prospecto activo no existe o no pertenece a este cliente.',
            ];
        }

        try {
            $pendingUpdate = $this->getPendingUpdate($clientId, $customerPhone, $botPhoneId);
            $updatedLead = $this->updateLead($client, $lead, $pendingUpdate['field'], $pendingUpdate['value']);

            $this->clearPendingUpdate($clientId, $customerPhone, $botPhoneId);
            $this->setPendingCandidateLeadIds($clientId, $customerPhone, [], $botPhoneId);

            return [
                'lead' => $this->formatLeadData($updatedLead),
                'message' => $this->formatUpdatedLeadInfo($updatedLead),
                'candidates' => null,
            ];
        } catch (Exception $e) {
            $this->clearPendingUpdate($clientId, $customerPhone, $botPhoneId);
            return [
                'lead' => $this->formatLeadData($lead),
                'message' => '❌ Error al actualizar: ' . $e->getMessage(),
                'candidates' => null,
                'isError' => true,
            ];
        }
    }


    private function handleRejectUpdate(
        Client $client,
        array $params,
        string $message,
        string $customerPhone,
        ?string $botPhoneId
    ): array {
        $clientId = $client->id;

        $this->setActiveLead($clientId, $customerPhone, null, $botPhoneId);
        $this->clearPendingUpdate($clientId, $customerPhone, $botPhoneId);
        $this->setPendingCandidateLeadIds($clientId, $customerPhone, [], $botPhoneId);

        $msg = $message
            ?: '🤖 Cambio cancelado. Empecemos de nuevo buscando un prospecto por ID, nombre, email o teléfono.'
        ;
        return [
            'lead' => null,
            'message' => $msg,
            'candidates' => null,
        ];
    }

    // =========================================================================
    // NOTES ACTION HANDLERS
    // Requieren lead activo y manejan el subflujo de notas.
    // =========================================================================

    /**
     * Lista las notas del prospecto activo.
     * Si hay notas, devuelve candidates para que el Job pause con AWAITING_SELECTION.
     * Si no hay notas, devuelve solo el mensaje (STEP_DONE).
     */
    private function handleListNotes(
        Client $client,
        User $user,
        array $params,
        string $message,
        string $customerPhone,
        ?string $botPhoneId
    ): array {
        $clientId = $client->id;
        $activeLeadId = $this->getActiveLeadId($clientId, $customerPhone, $botPhoneId);

        if (!$activeLeadId) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '🤖 Primero necesitás seleccionar un prospecto para ver sus notas.',
            ];
        }

        $lead = Lead::where('client_id', $clientId)
            ->where('id', $activeLeadId)
            ->with(['mainLeadContact.leadContactEmails', 'mainLeadContact.leadContactPhones', 'status'])
            ->with('notes')
            ->first();

        if (!$lead) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '❌ El prospecto seleccionado ya no existe.',
            ];
        }

        $notes = $lead->notes->sortByDesc('created_at')->values();
        $noteIds = $notes->pluck('id')->toArray();
        $this->setNotesPendingIds($clientId, $customerPhone, $noteIds, $botPhoneId);
        $this->setNotesSelectedNoteId($clientId, $customerPhone, null, $botPhoneId);
        $this->setNotesSubState($clientId, $customerPhone, self::NOTES_SUB_LISTING, $botPhoneId);
        $this->setConversationStatus($clientId, $customerPhone, self::STATUS_LEAD_NOTES_FLOW, $botPhoneId);

        return [
            'lead' => null,
            'candidates' => null,
            'message' => $this->formatNotesList($lead, $notes),
        ];
    }

    /**
     * Muestra una nota completa por índice (1-based). Setea selectedNoteId.
     */
    private function handleViewNote(
        Client $client,
        array $params,
        string $message,
        string $customerPhone,
        ?string $botPhoneId
    ): array {
        $clientId = $client->id;
        $activeLeadId = $this->getActiveLeadId($clientId, $customerPhone, $botPhoneId);

        if (!$activeLeadId) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '🤖 Primero necesitás seleccionar un prospecto.',
            ];
        }

        $noteIndex = isset($params['noteIndex']) ? (int) $params['noteIndex'] : null;
        $pendingIds = $this->getNotesPendingIds($clientId, $customerPhone, $botPhoneId);

        if ($noteIndex === null || empty($pendingIds)) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '🤖 Indicá el número de la nota que querés ver (1, 2, 3, etc.).',
            ];
        }

        $zeroBased = $noteIndex < 1 ? 0 : $noteIndex - 1;
        $noteId = $pendingIds[$zeroBased] ?? null;

        if (!$noteId) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '🤖 Número de nota inválido.',
            ];
        }

        $note = Note::where('id', $noteId)
            ->where('lead_id', $activeLeadId)
            ->where('client_id', $clientId)
            ->first();

        if (!$note) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '❌ No se encontró esa nota.',
            ];
        }

        $this->setNotesSelectedNoteId($clientId, $customerPhone, $note->id, $botPhoneId);
        $this->setNotesSubState($clientId, $customerPhone, self::NOTES_SUB_VIEWING, $botPhoneId);

        $date = $note->created_at?->format('d/m/Y') ?? '-';
        $text = $note->text ?? '(sin contenido)';

        $msg = <<<TEXT
            📝 Nota {$noteIndex} [{$date}]

            {$text}

            🤖 Decí "eliminar" para borrarla o "volver" al listado.
        TEXT;

        return [
            'lead' => null,
            'candidates' => null,
            'message' => $msg,
        ];
    }

    /**
     * Vuelve al listado de notas (limpia selectedNoteId).
     */
    private function handleBackToNotesList(
        Client $client,
        array $params,
        string $message,
        string $customerPhone,
        ?string $botPhoneId
    ): array {
        $clientId = $client->id;
        $activeLeadId = $this->getActiveLeadId($clientId, $customerPhone, $botPhoneId);

        if (!$activeLeadId) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '🤖 No hay prospecto seleccionado.',
            ];
        }

        $this->setNotesSelectedNoteId($clientId, $customerPhone, null, $botPhoneId);
        $this->setNotesSubState($clientId, $customerPhone, self::NOTES_SUB_LISTING, $botPhoneId);

        $lead = Lead::where('client_id', $clientId)
            ->where('id', $activeLeadId)
            ->with(['mainLeadContact', 'notes'])
            ->first();

        if (!$lead) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '❌ El prospecto ya no existe.',
            ];
        }

        $notes = $lead->notes->sortByDesc('created_at')->values();
        $this->setNotesPendingIds($clientId, $customerPhone, $notes->pluck('id')->toArray(), $botPhoneId);

        return [
            'lead' => null,
            'candidates' => null,
            'message' => $this->formatNotesList($lead, $notes),
        ];
    }

    /**
     * Crea una nota. Si content viene vacío, setea sub-estado awaiting_content para pedir contenido.
     */
    private function handleCreateNote(
        Client $client,
        User $user,
        array $params,
        string $message,
        string $customerPhone,
        ?string $botPhoneId
    ): array {
        $clientId = $client->id;
        $activeLeadId = $this->getActiveLeadId($clientId, $customerPhone, $botPhoneId);

        if (!$activeLeadId) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '🤖 Primero necesitás seleccionar un prospecto para crear una nota.',
            ];
        }

        $content = trim((string) ($params['content'] ?? $message));

        if ($content !== '') {
            $lead = Lead::where('client_id', $clientId)->where('id', $activeLeadId)->first();
            if (!$lead) {
                return [
                    'lead' => null,
                    'candidates' => null,
                    'message' => '❌ El prospecto seleccionado ya no existe.',
                ];
            }

            $this->noteService->create($lead, [
                'text' => $content,
                'user_id' => $user->id,
                'client_id' => $client->id,
            ]);

            $lead = $lead->fresh()->load('notes');
            $notes = $lead->notes->sortByDesc('created_at')->values();
            $this->setNotesPendingIds($clientId, $customerPhone, $notes->pluck('id')->toArray(), $botPhoneId);
            $this->setNotesSelectedNoteId($clientId, $customerPhone, null, $botPhoneId);
            $this->setNotesSubState($clientId, $customerPhone, self::NOTES_SUB_POST_CREATE, $botPhoneId);
            $this->setConversationStatus($clientId, $customerPhone, self::STATUS_LEAD_NOTES_FLOW, $botPhoneId);

            return [
                'lead' => null,
                'candidates' => null,
                'message' => "✅ Nota creada.\n\n" . $this->formatPostCreateNote($lead, $notes),
            ];
        }

        $this->setNotesSubState($clientId, $customerPhone, self::NOTES_SUB_AWAITING_CONTENT, $botPhoneId);
        $this->setConversationStatus($clientId, $customerPhone, self::STATUS_LEAD_NOTES_FLOW, $botPhoneId);

        return [
            'lead' => null,
            'candidates' => null,
            'message' => '🤖 ¿Cuál es el texto de la nota?',
        ];
    }

    /**
     * Elimina una nota por índice (1-based) o por ID.
     */
    private function handleDeleteNote(
        Client $client,
        array $params,
        string $message,
        string $customerPhone,
        ?string $botPhoneId
    ): array {
        $clientId = $client->id;
        $activeLeadId = $this->getActiveLeadId($clientId, $customerPhone, $botPhoneId);

        if (!$activeLeadId) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '🤖 Primero necesitás seleccionar un prospecto.',
            ];
        }

        $noteIndex = isset($params['noteIndex']) ? (int) $params['noteIndex'] : null;
        $noteId = isset($params['noteId']) ? (int) $params['noteId'] : null;
        $useSelectedNote = filter_var($params['useSelectedNote'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $pendingIds = $this->getNotesPendingIds($clientId, $customerPhone, $botPhoneId);

        if ($useSelectedNote) {
            $noteId = $this->getNotesSelectedNoteId($clientId, $customerPhone, $botPhoneId);
        }

        if ($noteIndex !== null && $noteId === null && !empty($pendingIds)) {
            $zeroBased = $noteIndex < 1 ? 0 : $noteIndex - 1;
            $noteId = $pendingIds[$zeroBased] ?? null;
        }

        if (!$noteId) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '🤖 Indicá el número de la nota a eliminar (1, 2, 3, etc.).',
            ];
        }

        $note = Note::where('id', $noteId)
            ->where('lead_id', $activeLeadId)
            ->where('client_id', $clientId)
            ->first();

        if (!$note) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '❌ No se encontró esa nota.',
            ];
        }

        $this->noteService->delete($note);

        $lead = Lead::where('client_id', $clientId)->where('id', $activeLeadId)->with('notes')->first();
        $notes = $lead->notes->sortByDesc('created_at')->values();
        $this->setNotesPendingIds($clientId, $customerPhone, $notes->pluck('id')->toArray(), $botPhoneId);
        $this->setNotesSelectedNoteId($clientId, $customerPhone, null, $botPhoneId);
        $this->setNotesSubState($clientId, $customerPhone, self::NOTES_SUB_LISTING, $botPhoneId);

        $msg = $notes->isEmpty()
            ? '✅ Nota eliminada. Este prospecto no tiene más notas.'
            : "✅ Nota eliminada.\n\n" . $this->formatNotesList($lead, $notes);

        return [
            'lead' => null,
            'candidates' => null,
            'message' => $msg,
        ];
    }

    public function getLeadNotesMessage(
        int $clientId,
        string $customerPhone,
        ?string $botPhoneId = null
    ): string {
        $activeLeadId = $this->getActiveLeadId($clientId, $customerPhone, $botPhoneId);

        if (!$activeLeadId) {
            return '🤖 Primero necesitás seleccionar un prospecto para ver sus notas.';
        }

        $lead = Lead::where('client_id', $clientId)
            ->where('id', $activeLeadId)
            ->with(['mainLeadContact.leadContactEmails', 'mainLeadContact.leadContactPhones', 'status'])
            ->with('notes')
            ->first()
        ;

        if (!$lead) {
            return '❌ El prospecto seleccionado ya no existe.';
        }

        $notes = $lead->notes->sortByDesc('created_at')->values();
        $this->setNotesPendingIds($clientId, $customerPhone, $notes->pluck('id')->toArray(), $botPhoneId);
        $this->setNotesSelectedNoteId($clientId, $customerPhone, null, $botPhoneId);
        $this->setNotesSubState($clientId, $customerPhone, self::NOTES_SUB_LISTING, $botPhoneId);
        $this->setConversationStatus($clientId, $customerPhone, self::STATUS_LEAD_NOTES_FLOW, $botPhoneId);

        return $this->formatNotesList($lead, $notes);
    }

    /**
     * Formatea la lista de notas para mostrar al usuario.
     */
    private function formatNotesList(Lead $lead, $notes): string
    {
        if ($notes->isEmpty()) {
            return <<<TEXT
                📝 Notas de {$lead->mainFullName} (0)

                No hay notas para mostrar.

                🤖 ¿Qué querés hacer?

                • Crear nota: "crear nota [texto]" ej: "crear nota llamar el lunes"
                • Volver: escribí "volver"
            TEXT;
        }

        $items = [];
        foreach ($notes->values() as $i => $note) {
            $date = $note->created_at?->format('d/m/Y') ?? '-';
            $preview = Str::limit($note->text ?? '', 50);
            $num = $i + 1;
            $items[] = "{$num}. [{$date}] {$preview}";
        }

        $listItems = implode("\n", $items);
        
        $message = <<<TEXT
            📝 Notas de {$lead->mainFullName} ({$notes->count()})

            {$listItems}

            🤖 ¿Qué querés hacer?

            • Crear nota: "crear nota [texto]" ej: "crear nota llamar el lunes"
            • Ver nota: escribí su número ej: "1"
            • Eliminar nota: "eliminar [número]" ej: "eliminar 1"
            • Volver: escribí "volver"
        TEXT;

        return $message;
    }

    private function formatPostCreateNote(Lead $lead, $notes): string
    {
        $items = [];
        foreach ($notes->values() as $note) {
            $date = $note->created_at?->format('d/m/Y') ?? '-';
            $preview = Str::limit($note->text ?? '', 50);
            $items[] = "[{$date}] {$preview}";
        }

        $listItems = implode("\n", $items);

        return <<<TEXT
            📝 Notas de {$lead->mainFullName} ({$notes->count()})

            {$listItems}

            🤖 ¿Qué querés hacer?

            • 1. Volver
        TEXT;
    }

    // =========================================================================
    // TASK ACTION HANDLERS
    // =========================================================================

    /**
     * Lista las tareas del usuario (no requiere lead activo)
     */
    private function handleListUserTasks(
        Client $client,
        User $user,
        array $params,
        string $message,
        string $customerPhone,
        ?string $botPhoneId
    ): array {
        $filter = $params['filter'] ?? 'non_expired';

        $filters = match ($filter) {
            'expired' => ['expired' => $client],
            'expires_today' => ['expires_today' => $client],
            default => ['status' => $filter],
        };

        $options = [
            'limit' => 10,
            'filters' => $filters,
            'sort' => 'limit_date desc',
        ];

        $userTasks = $this->taskServiceView->findByFiltersAndUser($user, $options);

        if ($userTasks->isEmpty()) {
            $this->setActiveTaskId($client->id, $customerPhone, null, $botPhoneId);
            $this->setTaskSubState($client->id, $customerPhone, self::TASK_SUB_LISTING, $botPhoneId);
            $this->setConversationStatus($client->id, $customerPhone, self::STATUS_TASK_SCOPE, $botPhoneId);

            $taskStatusLabel = $this->getTaskStatusLabel($filter);
            return [
                'lead' => null,
                'message' => "🤖 No tenés tareas {$taskStatusLabel}.\n\n• Volver: escribí \"volver\"",
                'candidates' => null,
            ];
        }

        $taskStatusLabel = $this->getTaskStatusLabel($filter);

        if ($userTasks->count() === 1) {
            $task = $userTasks->first();
            $this->setActiveTaskId($client->id, $customerPhone, $task->id, $botPhoneId);
            $this->setTaskSubState($client->id, $customerPhone, self::TASK_SUB_VIEWING, $botPhoneId);
            $this->setConversationStatus($client->id, $customerPhone, self::STATUS_TASK_SCOPE, $botPhoneId);
            return [
                'lead' => null,
                'candidates' => null,
                'message' => "📋 Tu tarea {$taskStatusLabel}:\n\n" . $this->formattedTaskDetail($client, $task),
            ];
        }

        $taskIds = $userTasks->pluck('id')->toArray();
        $this->setActiveTaskId($client->id, $customerPhone, null, $botPhoneId);
        $this->setPendingTaskIds($client->id, $customerPhone, $taskIds, $botPhoneId);
        $this->setTaskSubState($client->id, $customerPhone, self::TASK_SUB_LISTING, $botPhoneId);
        $this->setConversationStatus($client->id, $customerPhone, self::STATUS_TASK_SCOPE, $botPhoneId);

        return [
            'lead' => null,
            'candidates' => $this->formattedTaskList($userTasks),
            'message' => "📋 Tus tareas {$taskStatusLabel} ({$userTasks->count()}):",
        ];
    }


    /**
     * Lista las tareas del prospecto activo (requiere lead activo)
     */
    private function handleListLeadTasks(
        Client $client,
        array $params,
        string $message,
        string $customerPhone,
        ?string $botPhoneId
    ): array {
        $clientId = $client->id;
        $activeLeadId = $this->getActiveLeadId($clientId, $customerPhone, $botPhoneId);

        if (!$activeLeadId) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '🤖 Primero necesitás seleccionar un prospecto para ver sus tareas.',
            ];
        }

        $lead = Lead::where('client_id', $clientId)
            ->where('id', $activeLeadId)
            ->with(['tasks.user'])
            ->first()
        ;

        if (!$lead) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '❌ El prospecto seleccionado ya no existe.',
            ];
        }

        $filter = $params['filter'] ?? 'non_expired';

        $filters = ['lead_id' => $activeLeadId];
        if ($filter === 'expires_today') {
            $filters['expires_today'] = $client;
        } else {
            $filters['status'] = $filter;
        }

        $options = [
            'limit' => 10,
            'filters' => $filters,
            'sort' => 'limit_date desc',
        ];

        $this->logInfo("<<<<< FILTERS >>>>>: " .
            json_encode($options, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        $leadTasks = $this->taskServiceView->findByFiltersAndClient($options, $client);

        if ($leadTasks->isEmpty()) {
            $this->setActiveTaskId($clientId, $customerPhone, null, $botPhoneId);
            $this->setTaskSubState($clientId, $customerPhone, self::TASK_SUB_LISTING, $botPhoneId);
            $this->setConversationStatus($clientId, $customerPhone, self::STATUS_TASK_SCOPE, $botPhoneId);

            $taskStatusLabel = $this->getTaskStatusLabel($filter);
            $message = <<<TEXT
                🤖 No hay tareas {$taskStatusLabel} para este prospecto.

                ¿Qué querés hacer?

                • Crear tarea: "crear tarea [titulo] con fecha de vencimiento [fecha]"
                ej: "crear tarea contactar a Juan Perez con fecha de vencimiento en 1 mes"
                • Volver: escribí "volver"
            TEXT;
            return [
                'lead' => null,
                'candidates' => null,
                'message' => $message,
            ];
        }

        $taskStatusLabel = $this->getTaskStatusLabel($filter);

        if ($leadTasks->count() === 1) {
            $task = $leadTasks->first();
            $this->setActiveTaskId($clientId, $customerPhone, $task->id, $botPhoneId);
            $this->setTaskSubState($clientId, $customerPhone, self::TASK_SUB_VIEWING, $botPhoneId);
            $this->setConversationStatus($clientId, $customerPhone, self::STATUS_TASK_SCOPE, $botPhoneId);
            return [
                'lead' => null,
                'candidates' => null,
                'message' => "📋 Tarea {$taskStatusLabel} del prospecto:\n\n" .
                $this->formattedTaskDetail($client, $task),
            ];
        }

        $taskIds = $leadTasks->pluck('id')->toArray();
        $this->setActiveTaskId($clientId, $customerPhone, null, $botPhoneId);
        $this->setPendingTaskIds($clientId, $customerPhone, $taskIds, $botPhoneId);
        $this->setTaskSubState($clientId, $customerPhone, self::TASK_SUB_LISTING, $botPhoneId);
        $this->setConversationStatus($clientId, $customerPhone, self::STATUS_TASK_SCOPE, $botPhoneId);

        return [
            'lead' => null,
            'candidates' => $this->formattedTaskList($leadTasks),
            'message' => "📋 Tareas {$taskStatusLabel} del prospecto ({$leadTasks->count()}):",
        ];
    }


    private function handleViewTask(
        Client $client,
        array $params,
        string $message,
        string $customerPhone,
        ?string $botPhoneId
    ): array {
        $clientId = $client->id;
        $taskId = $params['taskId'] ?? null;
        $taskIndex = $params['taskIndex'] ?? null;

        // Si viene índice, buscar en pendingTaskIds
        if ($taskId === null && $taskIndex !== null) {
            $pendingTaskIds = $this->getPendingCandidateTaskIds($clientId, $customerPhone, $botPhoneId);
            if (empty($pendingTaskIds) || !isset($pendingTaskIds[$taskIndex])) {
                return [
                    'lead' => null,
                    'candidates' => null,
                    'message' => '❌ No hay una lista de tareas activa o el índice es inválido.',
                ];
            }
            $taskId = $pendingTaskIds[$taskIndex];
        }

        // Fallback a tarea activa si no se especificó taskId ni taskIndex
        if (!$taskId) {
            $taskId = $this->getActiveTaskId($clientId, $customerPhone, $botPhoneId);
        }

        if (!$taskId) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '🤖 Necesito el ID de la tarea o un índice válido de la lista.',
            ];
        }

        $task = Task::where('client_id', $clientId)
            ->where('id', $taskId)
            ->with(['user', 'lead'])
            ->first()
        ;

        if (!$task) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => "🤖 No encontré la tarea #{$taskId}.",
            ];
        }

        $this->setActiveTaskId($clientId, $customerPhone, $task->id, $botPhoneId);
        $this->setTaskSubState($clientId, $customerPhone, self::TASK_SUB_VIEWING, $botPhoneId);
        $this->setConversationStatus($clientId, $customerPhone, self::STATUS_TASK_SCOPE, $botPhoneId);

        return [
            'lead' => null,
            'candidates' => null,
            'message' => $this->formattedTaskDetail($client, $task),
        ];
    }


    private function handleUpdateTask(
        Client $client,
        User $user,
        array $params,
        string $message,
        string $customerPhone,
        ?string $botPhoneId
    ): array {
        $clientId = $client->id;
        $taskId = $params['taskId'] ?? null;
        $taskIndex = $params['taskIndex'] ?? null;
        $newStatus = $params['newStatus'] ?? null;

        // Si viene índice, buscar en pendingTaskIds
        if ($taskId === null && $taskIndex !== null) {
            $pendingTaskIds = $this->getPendingCandidateTaskIds($clientId, $customerPhone, $botPhoneId);
            if (empty($pendingTaskIds) || !isset($pendingTaskIds[$taskIndex])) {
                return [
                    'lead' => null,
                    'candidates' => null,
                    'message' => '🤖 No hay una lista de tareas activa o el índice es inválido.',
                ];
            }
            $taskId = $pendingTaskIds[$taskIndex];
        }

        // Fallback a tarea activa si no se especificó taskId ni taskIndex
        if (!$taskId) {
            $taskId = $this->getActiveTaskId($clientId, $customerPhone, $botPhoneId);
        }

        if (!$taskId) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '🤖 Necesito el ID de la tarea para actualizarla.',
            ];
        }

        if (!in_array($newStatus, ['pending', 'completed'])) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '🤖 El estado debe ser "pending" (pendiente) o "completed" (completada).',
            ];
        }

        $task = $this->taskService->findByClientAndIds($client, [$taskId])->first();

        if (!$task) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => "🤖 No encontré la tarea #{$taskId}.",
            ];
        }

        $task = $this->taskService->update($task, ['status' => $newStatus], $user);

        $statusLabel = $newStatus === 'completed' ? 'completada' : 'pendiente';
        return [
            'lead' => null,
            'candidates' => null,
            'message' => "✅ Tarea marcada como {$statusLabel}.\n\n" . $this->formattedTaskDetail($client, $task),
        ];
    }


    private function handleCreateTask(
        Client $client,
        User $user,
        array $params,
        string $message,
        string $customerPhone,
        ?string $botPhoneId
    ): array {
        $clientId = $client->id;
        $activeLeadId = $this->getActiveLeadId($clientId, $customerPhone, $botPhoneId);

        if (!$activeLeadId) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '🤖 Primero necesitás seleccionar un prospecto para crear una tarea.',
            ];
        }

        $lead = Lead::where('client_id', $clientId)
            ->where('id', $activeLeadId)
            ->first()
        ;

        if (!$lead) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '🤖 El prospecto seleccionado ya no existe.',
            ];
        }

        $title = $params['title'] ?? null;
        if (!$title || trim($title) === '') {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '🤖 Necesito al menos un título para crear la tarea.',
            ];
        }

        $taskData = [
            'title' => $title,
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'client_id' => $clientId,
            'status' => 'pending',
        ];

        // Campos opcionales
        if (!empty($params['description'])) {
            $taskData['description'] = $params['description'];
        }
        if (!empty($params['limitDate'])) {
            $taskData['limit_date'] = $params['limitDate'];
        }
        if (isset($params['isImportant'])) {
            $taskData['is_important'] = (bool) $params['isImportant'];
        }

        $task = $this->taskService->create($taskData);
        $this->setActiveTaskId($clientId, $customerPhone, $task->id, $botPhoneId);
        $this->setTaskSubState($clientId, $customerPhone, self::TASK_SUB_POST_CREATE, $botPhoneId);
        $this->setConversationStatus($clientId, $customerPhone, self::STATUS_TASK_SCOPE, $botPhoneId);

        return [
            'lead' => null,
            'candidates' => null,
            'message' => "✅ Tarea creada:\n\n" . $this->formattedTaskDetailPostCreate($client, $task->load('user')),
        ];
    }

    // =========================================================================
    // TASK CONTEXT / FORMATTERS
    // =========================================================================

    /**
     * Formatea el detalle de una tarea para mostrar al usuario (método público)
     */
    public function formatTaskInfo(Client $client, int $taskId): ?string
    {
        $task = Task::where('client_id', $client->id)
            ->where('id', $taskId)
            ->with(['user', 'lead'])
            ->first()
        ;

        return $task ? $this->formattedTaskDetail($client, $task) : null;
    }


    private function formattedTaskDetail(Client $client, Task $task): string
    {
        $statusLegend = $task->status === 'completed' ? 'Completada ✅' : 'Pendiente 🟡';
        $importantLegend = $task->is_important ? '(Es importante ⭐)' : '';
        $leadId = $task->lead->id;
        $userId = $task->user->id;
        $userName = $task->user->fullName ?? '[asignar vacío]';
        $leadName = $task->lead->mainFullName ?? '[asignar vacío]';

        $clientTz = new DateTimeZone($client->timezone);
        $limitDate = (new DateTime($task->limit_date))->setTimezone($clientTz);
        $limitDateStr = $task->limit_date->format('d/m/Y H:i') . ' hs.';
        
        $isExpired = $limitDate < new DateTime('now', $clientTz);

        $expirationLegend = "Vence: {$limitDateStr}";
        if ($isExpired) {
            $expirationLegend = "Venció: {$limitDateStr}";
        }

        $actionLines = [];
        if (!$isExpired && $task->status == 'pending') {
            $actionLines[] = '• Escribir "completar tarea" para marcar la tarea como completada ✅';
        }
        $actionLines[] = '• Crear tarea: "crear tarea [texto]" ej: "crear tarea llamar el lunes que venza en 1 mes"';
        $actionLines[] = '• Volver: escribí "volver"';
        $actionsBlock = implode("\n", $actionLines);

        $detail = <<<TEXT
            {$task->title} (ID: {$task->id}) {$importantLegend}
            
            🗓  {$expirationLegend}
            📌 Estado: {$statusLegend}

            👤 Usuario: {$userName} (ID: {$userId})
            👤 Prospecto: {$leadName} (ID: {$leadId})

            🤖 ¿Qué querés hacer?

            {$actionsBlock}
        TEXT;

        return $detail;
    }

    private function formattedTaskDetailPostCreate(Client $client, Task $task): string
    {
        $statusLegend = $task->status === 'completed' ? 'Completada ✅' : 'Pendiente 🟡';
        $importantLegend = $task->is_important ? '(Es importante ⭐)' : '';
        $leadId = $task->lead->id;
        $userId = $task->user->id;
        $userName = $task->user->fullName ?? '[asignar vacío]';
        $leadName = $task->lead->mainFullName ?? '[asignar vacío]';

        $clientTz = new DateTimeZone($client->timezone);
        $limitDate = (new DateTime($task->limit_date))->setTimezone($clientTz);
        $limitDateStr = $task->limit_date->format('d/m/Y H:i') . ' hs.';

        $isExpired = $limitDate < new DateTime('now', $clientTz);

        $expirationLegend = "Vence: {$limitDateStr}";
        if ($isExpired) {
            $expirationLegend = "Venció: {$limitDateStr}";
        }

        return <<<TEXT
            {$task->title} (ID: {$task->id}) {$importantLegend}
            
            🗓  {$expirationLegend}
            📌 Estado: {$statusLegend}

            👤 Usuario: {$userName} (ID: {$userId})
            👤 Prospecto: {$leadName} (ID: {$leadId})

            🤖 ¿Qué querés hacer?

            • 1. Volver
        TEXT;
    }

    private function getTaskStatusLabel(string $filter): string
    {
        return match ($filter) {
            'non_expired' => 'pendientes 🟡',
            'completed' => 'completadas ✅',
            'expires_today' => 'para hoy 🟢',
            'expired' => 'vencidas 🔴',
            default => '',
        };
    }

    // =========================================================================
    // LEAD CONTEXT / SEARCH HELPERS
    // =========================================================================

    private function searchLeadById(Client $client, int $id): Collection
    {
        $lead = Lead::where('client_id', $client->id)
            ->where('id', $id)
            ->with(['mainLeadContact.leadContactEmails', 'mainLeadContact.leadContactPhones', 'status'])
            ->withCount('notes')
            ->first()
        ;

        return $lead ? collect([$lead]) : collect([]);
    }


    private function searchLeadsByEmail(Client $client, string $email): Collection
    {
        $leadIds = $this->leadServiceView->listIdsByClientAndEmail($client, $email);

        if ($leadIds->isEmpty()) {
            return collect([]);
        }

        return Lead::where('client_id', $client->id)
            ->whereIn('id', $leadIds)
            ->with(['mainLeadContact.leadContactEmails', 'mainLeadContact.leadContactPhones', 'status'])
            ->withCount('notes')
            ->orderBy('lead_created_at', 'desc')
            ->limit(10)
            ->get()
        ;
    }


    private function searchLeadsByPhone(Client $client, string $phone): Collection
    {
        $leadIds = $this->leadServiceView->listIdsByClientAndPhone($client, $phone);

        if ($leadIds->isEmpty()) {
            return collect([]);
        }

        return Lead::where('client_id', $client->id)
            ->whereIn('id', $leadIds)
            ->with(['mainLeadContact.leadContactEmails', 'mainLeadContact.leadContactPhones', 'status'])
            ->withCount('notes')
            ->orderBy('lead_created_at', 'desc')
            ->limit(10)
            ->get()
        ;
    }


    private function searchLeadsByName(Client $client, string $name): Collection
    {
        $nameParts = explode(' ', trim($name));
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? null;

        $query = Lead::where('client_id', $client->id)
            ->whereHas('leadContacts', function ($q) use ($firstName, $lastName) {
                if ($lastName) {
                    $q->where(function ($subQ) use ($firstName, $lastName) {
                        $subQ->where('name', 'like', "%{$firstName}%")
                            ->where('last_name', 'like', "%{$lastName}%");
                    })->orWhere(function ($subQ) use ($firstName, $lastName) {
                        $subQ->where('name', 'like', "%{$firstName} {$lastName}%")
                            ->orWhere('last_name', 'like', "%{$firstName} {$lastName}%");
                    });
                } else {
                    $q->where('name', 'like', "%{$firstName}%")
                        ->orWhere('last_name', 'like', "%{$firstName}%");
                }
            })
            ->with(['mainLeadContact.leadContactEmails', 'mainLeadContact.leadContactPhones', 'status'])
            ->withCount('notes')
            ->orderBy('lead_created_at', 'desc')
        ;

        return $query->limit(10)->get();
    }


    private function searchLeadsByStatus(Client $client, string $statusName): Collection
    {
        $status = $this->statusService->findOneByClientAndName($client, $statusName);

        if (!$status && is_numeric($statusName)) {
            $status = $this->statusService->findOneByClientAndId($client, (int) $statusName);
        }

        if (!$status) {
            return collect([]);
        }

        $this->leadServiceView->setClient($client);

        $result = $this->leadServiceView->list([
            'page' => 1,
            'limit' => 10,
            'sort' => 'date_desc',
            'with' => [
                'mainLeadContact.leadContactEmails',
                'mainLeadContact.leadContactPhones',
                'status',
            ],
            'filters' => [
                'status_id' => $status->id,
            ],
        ]);

        return $result->getCollection()->loadCount('notes');
    }


    private function searchLeadsByTag(Client $client, string $tagName): Collection
    {
        $tag = Tag::where('client_id', $client->id)
            ->where('name', $tagName)
            ->first()
        ;

        if (!$tag) {
            return collect([]);
        }

        $this->leadServiceView->setClient($client);

        $result = $this->leadServiceView->list([
            'page' => 1,
            'limit' => 10,
            'sort' => 'date_desc',
            'with' => [
                'mainLeadContact.leadContactEmails',
                'mainLeadContact.leadContactPhones',
                'status',
            ],
            'filters' => [
                'tag_id' => [$tag->id],
            ],
        ]);

        return $result->getCollection()->loadCount('notes');
    }

    /**
     * Devuelve un mapa [id => name] con todos los estados del cliente.
     */
    public function getStatusNameMap(Client $client): array
    {
        $statuses = $this->statusService->findAllByClient($client);

        if ($statuses->isEmpty()) {
            return [];
        }

        return $statuses->pluck('name', 'id')->toArray();
    }


    public function findOneStatusByClientAndName(Client $client, string $name): ?Status
    {
        return $this->statusService->findOneByClientAndName($client, $name);
    }


    // =========================================================================
    // LEAD CONTEXT / UPDATE HELPERS
    // =========================================================================

    private function updateLeadName(Lead $lead, string $name): Lead
    {
        $mainContact = $lead->mainLeadContact;
        if (!$mainContact) {
            throw new Exception('El prospecto no tiene contacto principal');
        }

        $this->leadContactService->update($mainContact, ['name' => $name]);
        return $lead->fresh(['mainLeadContact', 'status']);
    }


    private function updateLeadLastname(Lead $lead, string $lastname): Lead
    {
        $mainContact = $lead->mainLeadContact;
        if (!$mainContact) {
            throw new Exception('El prospecto no tiene contacto principal');
        }

        $this->leadContactService->update($mainContact, ['last_name' => $lastname]);
        return $lead->fresh(['mainLeadContact', 'status']);
    }


    private function updateLeadEmail(Lead $lead, string $email): Lead
    {
        $mainContact = $lead->mainLeadContact;
        if (!$mainContact) {
            throw new Exception('El prospecto no tiene contacto principal');
        }

        $leadContactEmail = $mainContact->leadContactEmails->first();

        if (!$leadContactEmail) {
            $this->leadContactEmailService->create($mainContact, ['email' => $email]);
            return $lead->fresh(['mainLeadContact.leadContactEmails', 'status']);
        }

        $existentEmail = resolve(LeadContactEmailRepository::class)
            ->findOneByLeadAndEmail($lead, $email);

        if ($existentEmail && $existentEmail->id !== $leadContactEmail->id) {
            throw new Exception('El prospecto ya tiene un email con esa dirección');
        }

        $this->leadContactEmailService->update($leadContactEmail, ['email' => $email]);
        return $lead->fresh(['mainLeadContact.leadContactEmails', 'status']);
    }


    private function updateLeadPhone(Lead $lead, string $phone): Lead
    {
        $mainContact = $lead->mainLeadContact;
        if (!$mainContact) {
            throw new Exception('El prospecto no tiene contacto principal');
        }

        $leadContactPhone = $mainContact->leadContactPhones->first();

        if (!$leadContactPhone) {
            $this->leadContactPhoneService->create($mainContact, ['phone' => $phone]);
            return $lead->fresh(['mainLeadContact.leadContactPhones', 'status']);
        }

        $existentPhone = resolve(LeadContactPhoneRepository::class)
            ->findOneByLeadAndPhone($lead, $phone);

        if ($existentPhone && $existentPhone->id !== $leadContactPhone->id) {
            throw new Exception('El prospecto ya tiene un teléfono con ese número');
        }

        $this->leadContactPhoneService->update($leadContactPhone, ['phone' => $phone]);
        return $lead->fresh(['mainLeadContact.leadContactPhones', 'status']);
    }


    private function updateLeadStatus(Client $client, Lead $lead, string $statusValue): Lead
    {
        $status = $this->statusService->findOneByClientAndName($client, $statusValue);

        if (!$status && is_numeric($statusValue)) {
            $status = $this->statusService->findOneByClientAndId($client, (int) $statusValue);
        }

        if (!$status) {
            throw new Exception("Estado no encontrado: {$statusValue}");
        }

        if ($status->client_id !== $client->id) {
            throw new Exception('El estado no pertenece a este cliente');
        }

        $this->actionsLeadService->changeStatus($lead, $status);
        return $lead->fresh(['mainLeadContact', 'status']);
    }


    // =========================================================================
    // SALE CONTEXT / BUSINESS
    // =========================================================================

    public function createLeadSale(Client $client, User $user, int $leadId, float $amount, ?string $description): array
    {
        $lead = Lead::where('client_id', $client->id)
            ->where('id', $leadId)
            ->with(['mainLeadContact.leadContactEmails', 'mainLeadContact.leadContactPhones', 'status'])
            ->first();

        if (!$lead) {
            return [
                'lead' => null,
                'candidates' => null,
                'message' => '❌ No se encontró el prospecto asociado.',
            ];
        }

        $leadSaleService = resolve(LeadSaleService::class);
        $leadSaleData = [
            'amount' => $amount,
            'description' => $description,
            'user_id' => $user->id,
            'client_id' => $client->id,
            'sale_date' => new DateTime(),
            'is_manually_created' => false,
        ];
        $leadSaleService->create($lead, $leadSaleData);

        $formattedAmount = number_format($amount, 2, ',', '.');
        $descText = $description ? "\n📝 Descripción: {$description}" : '';
        $leadInfo = $this->formatUpdatedLeadInfo($lead);

        $msg = <<<TEXT
            ✅ Venta registrada exitosamente.
            💰 Monto: \${$formattedAmount}{$descText}

            {$leadInfo}
        TEXT;

        return [
            'lead' => $this->formatLeadData($lead),
            'message' => $msg,
            'candidates' => null,
        ];
    }

    // =========================================================================
    // INFRASTRUCTURE HELPERS
    // Redis keys, TTL y persistencia de historial.
    // =========================================================================

    private function getRedisHelper(int $clientId): RedisHelper
    {
        return new RedisHelper($clientId, 'wap_sales_agent');
    }


    private function buildSessionHashKey(string $customerPhone, ?string $botPhoneId = null): string
    {
        $key = self::SESSION_KEY_PREFIX . "_customer_{$customerPhone}";
        if ($botPhoneId) {
            $key .= "_bot_{$botPhoneId}";
        }
        return $key;
    }


    private function buildHistoryListKey(string $customerPhone, ?string $botPhoneId = null): string
    {
        return $this->buildSessionHashKey($customerPhone, $botPhoneId) . ':history';
    }


    private function refreshSessionTTL(RedisHelper $redis, string $customerPhone, ?string $botPhoneId): void
    {
        if ($redis->redisIsDown()) {
            return;
        }

        $hashKey = $this->buildSessionHashKey($customerPhone, $botPhoneId);
        $listKey = $this->buildHistoryListKey($customerPhone, $botPhoneId);

        $redis->expire($redis->getScopedKey($hashKey), self::SESSION_TTL_SECONDS);
        $redis->expire($redis->getScopedKey($listKey), self::SESSION_TTL_SECONDS);
    }


    private function addHistoryEntry(int $clientId, string $customerPhone, array $entry, ?string $botPhoneId): void
    {
        $redis = $this->getRedisHelper($clientId);
        if ($redis->redisIsDown()) {
            return;
        }
        if (!isset($entry['at'])) {
            $entry['at'] = now()->toIso8601String();
        }

        $listKey = $this->buildHistoryListKey($customerPhone, $botPhoneId);
        $scopedListKey = $redis->getScopedKey($listKey);

        $messageJson = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $redis->rpush($scopedListKey, $messageJson);

        // Limitar tamaño
        $currentLength = $redis->llen($scopedListKey);
        if ($currentLength > self::OPEN_AI_HISTORY_LIMIT) {
            $toRemove = $currentLength - self::OPEN_AI_HISTORY_LIMIT;
            for ($i = 0; $i < $toRemove; $i++) {
                $redis->lpop($scopedListKey);
            }
        }
        $this->refreshSessionTTL($redis, $customerPhone, $botPhoneId);
    }


    // =========================================================================
    // FAILURE / LOGGING
    // =========================================================================

    public function failed(Throwable $e)
    {
        $this->logInfo((string) $e);
        Log::channel('WapSalesAgentAnswerIncomingMessageJobErrors')->error((string) $e);
    }

    protected function logInfo(string $msg): void
    {
        $logUuid = Str::orderedUuid();
        $this->getInfoLog()->info("[{$logUuid}] | {$msg}");
    }

    protected function getInfoLog()
    {
        return Log::channel('WapSalesAgentAnswerIncomingMessageJobInfo');
    }

}
