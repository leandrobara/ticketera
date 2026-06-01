<?php

namespace App\Jobs\WhatsAppEvents;

use Exception;
use Throwable;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\DTO\WapSalesAgent\LeadCandidateDTO;
use App\DTO\WapSalesAgent\TaskCandidateDTO;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\DTO\WapBot\WhatsAppMetaAPIWebhookPayloadDTO;
use App\Helpers\WhatsAppMetaAPI\WhatsAppMetaAPIHelper;
use App\Helpers\WapSalesAgent\WapSalesAgentPromptHelper;
use App\Services\API\WapSalesAgent\WapSalesAgentBotService;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;
use App\Services\API\WapSalesAgent\WapSalesAgentConversationService;


/**
 * Job que procesa mensajes entrantes del agente de ventas de WhatsApp.
 *
 * Este Job solo ORQUESTA el flujo:
 * 1. Valida el payload
 * 2. Delega la lógica de negocio al WapSalesAgentConversationService
 * 3. Envía la respuesta por WhatsApp
 *
 * queue: ENV_wap_sales_agent_queue
 */
class WapSalesAgentAnswerIncomingMessageJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable;

    protected $logUuid = null;
    private bool $isTestMode = false;

    // Contexto compartido entre métodos (se setea en handle)
    private $whatsAppConnection;

    private User $user;
    private Client $client;
    private string $customerPhoneNumber;
    private string $incomingMessageText;
    private string $connectedPhoneNumberId;
    private WhatsAppMetaAPIHelper $whatsAppHelper;
    private WapSalesAgentPromptHelper $promptHelper;
    private WapSalesAgentConversationService $wapSalesAgentService;
    
    // Workflow step outcomes
    private const STEP_OUTCOME_DONE = 'DONE';
    private const STEP_OUTCOME_ERROR = 'ERROR';
    private const STEP_OUTCOME_SWITCHED_CONTEXT = 'SWITCHED_CONTEXT';
    private const STEP_OUTCOME_REQUIRES_SELECTION = 'REQUIRES_SELECTION';
    private const STEP_OUTCOME_REQUIRES_CONFIRMATION = 'REQUIRES_CONFIRMATION';
    private const STEP_OUTCOME_REQUIRES_STATUS_SELECTION = 'REQUIRES_STATUS_SELECTION';

    // Lead scope menu options (numbered menu when a lead is selected)
    private const LEAD_SCOPE_MENU_OPTIONS = [
        1  => ['action' => 'update_name',     'label' => 'Cambiar nombre'],
        2  => ['action' => 'update_lastname', 'label' => 'Cambiar apellido'],
        3  => ['action' => 'update_email',    'label' => 'Cambiar email'],
        4  => ['action' => 'update_phone',    'label' => 'Cambiar teléfono'],
        5  => ['action' => 'update_status',   'label' => 'Cambiar estado'],
        6  => ['action' => 'view_statuses',   'label' => 'Ver estados'],
        7  => ['action' => 'view_notes',      'label' => 'Ver notas'],
        8  => ['action' => 'create_note',     'label' => 'Crear nota'],
        9  => ['action' => 'view_tasks',      'label' => 'Ver tareas'],
        10 => ['action' => 'create_task',     'label' => 'Crear tarea'],
        11 => ['action' => 'exit',            'label' => 'Salir'],
    ];


    public function __construct(public readonly array $metaWebhookPayload)
    {
    }


    public function handle()
    {
        $this->logUuid = Str::orderedUuid();
        $this->isTestMode = !empty($this->metaWebhookPayload['_test']);
        $this->logInfo('Starting WapSalesAgentAnswerIncomingMessageJob' . ($this->isTestMode ? ' [TEST MODE]' : ''));
        $this->logInfo(json_encode($this->metaWebhookPayload));

        $payloadDTO = new WhatsAppMetaAPIWebhookPayloadDTO($this->metaWebhookPayload);
        if (!$payloadDTO->isIncomingMessage()) {
            $this->logInfo('Payload is not an incoming message. RETURNING.');
            return true;
        }
        if (!$payloadDTO->isParsableMessage()) {
            $this->logInfo('Payload is not a parsable message. RETURNING.');
            return true;
        }
        
        $this->customerPhoneNumber = $payloadDTO->getFromNumber();
        $this->connectedPhoneNumberId = $payloadDTO->getPhoneNumberId();
        if (!$this->connectedPhoneNumberId || !$this->customerPhoneNumber) {
            $this->logInfo('Missing phone identifiers in payload. RETURNING.');
            return true;
        }
        $this->logInfo('- customerPhoneNumber: ' . $this->customerPhoneNumber);
        $this->logInfo('- connectedPhoneNumberId: ' . $this->connectedPhoneNumberId);


        $this->incomingMessageText = (string) ($payloadDTO->getMessage() ?? '');
        $this->logInfo('- incomingMessageText: ' . $this->incomingMessageText);

        if ($this->isTestMode) {
            // Test mode: client y user vienen del payload, sin bot ni conexión WhatsApp
            $this->user = User::find($this->metaWebhookPayload['_test_user_id'] ?? null);
            $this->client = Client::find($this->metaWebhookPayload['_test_client_id'] ?? null);
            if (!$this->client || !$this->user) {
                $this->logInfo('[TEST MODE] Client or User not found. RETURNING.');
                return true;
            }
            $this->logInfo("[TEST MODE] userId: {$this->user->id}");
            $this->logInfo("[TEST MODE] clientId: {$this->client->id}");
        } else {
            // +1 318 706-2664 (Kapso) (meta phone ID: 976199978920754)
            $wapSalesAgentPhoneNumberId = config('app.kapso.wap_sales_agent_meta_phone_id');
            if ($this->connectedPhoneNumberId != $wapSalesAgentPhoneNumberId) {
                $this->logInfo('Message was not sent to SalesAgent phone number.');
                return true;
            }

            $this->whatsAppConnection = resolve(WhatsAppMetaAPIService::class)->findActiveByPhoneNumber(
                $this->customerPhoneNumber
            );
            if (!$this->whatsAppConnection) {
                $this->logInfo('No WhatsAppMetaAPIConnection. RETURNING.');
                return true;
            }
            $this->logInfo("whatsAppConnectionId: {$this->whatsAppConnection->id}");

            $wapSalesAgentBot = resolve(WapSalesAgentBotService::class)->findActiveByMetaPhoneNumberId(
                $this->whatsAppConnection->phone_number_id
            );
            if (!$wapSalesAgentBot) {
                $this->logInfo('No active WapSalesAgentBot. RETURNING.');
                return true;
            }
            if ($wapSalesAgentBot->client_id != $this->whatsAppConnection->client_id) {
                $this->logInfo('wapSalesAgentBot.client_id != whatsAppConnection.client_id. RETURNING.');
                return true;
            }
            if (!$wapSalesAgentBot->client || !$wapSalesAgentBot->client->enabled) {
                $this->logInfo('Client missing or disabled. RETURNING.');
                return true;
            }
            if (!$wapSalesAgentBot->user) {
                $this->logInfo('Non existent User. RETURNING.');
                return true;
            }
            if (!$wapSalesAgentBot->user->enabled) {
                $this->logInfo("User ID: {$wapSalesAgentBot->user_id} is not enabled. RETURNING.");
                return true;
            }
            if ($this->whatsAppConnection->phone_number_id != $wapSalesAgentBot->meta_phone_number_id) {
                $this->logInfo(
                    'whatsAppConnection.phone_number_id != wapSalesAgentBot.meta_phone_number_id. RETURNING.'
                );
                return true;
            }

            $this->user = $wapSalesAgentBot->user;
            $this->client = $wapSalesAgentBot->client;
            $this->logInfo("userId: {$this->user->id}");
            $this->logInfo("clientId: {$this->client->id}");
        }

        $this->whatsAppHelper = resolve(WhatsAppMetaAPIHelper::class);
        $this->promptHelper = resolve(WapSalesAgentPromptHelper::class);
        $this->wapSalesAgentService = resolve(WapSalesAgentConversationService::class);

        $upperMessage = trim(strtoupper($this->incomingMessageText));
        if ($upperMessage == '/CLEAR' || $upperMessage == '/LIMPIAR') {
            $this->wapSalesAgentService->resetSession(
                clientId: $this->client->id,
                botPhoneId: $this->connectedPhoneNumberId,
                customerPhone: $this->customerPhoneNumber,
            );

            $clearedMessage = $this->wapSalesAgentService->getClearedConversationMessage();
            $this->sendAndLogResponse($clearedMessage);
            $this->logInfo("clear: {$clearedMessage}. RETURNING");
            return true;
        }

        if ($this->handleStatusCommand()) {
            return true;
        }

        if (!$this->isTestMode) {
            // $this->logInfo('Sending Wap typing indicator...');
            // $this->whatsAppHelper->sendTypingIndicatorFromKapsoAPI($payloadDTO->getMessageId());
        }

        $this->addIncomingUserMessageToHistory($payloadDTO);

        $conversationStatus = $this->wapSalesAgentService->getConversationStatus(
            clientId: $this->client->id,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
        );

        return $this->handleByConversationStatus($conversationStatus);
    }

    // =========================================================================
    // BOOTSTRAP / REQUEST CONTEXT
    // =========================================================================


    private function handleStatusCommand(): bool
    {
        $upperMessage = trim(strtoupper($this->incomingMessageText));
        if (!in_array($upperMessage, ['/STATUS', '/ESTADO'], true)) {
            return false;
        }

        $conversationStatus = $this->wapSalesAgentService->getConversationStatus(
            clientId: $this->client->id,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
        );

        $lines = [
            '🤖 Estado actual de la conversación:',
            "• Status: {$conversationStatus}",
        ];

        $activeLeadId = $this->wapSalesAgentService->getActiveLeadId(
            clientId: $this->client->id,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
        );
        if ($activeLeadId) {
            $lines[] = "• Lead activo: {$activeLeadId}";
        }

        $activeTaskId = $this->wapSalesAgentService->getActiveTaskId(
            clientId: $this->client->id,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
        );
        if ($activeTaskId) {
            $lines[] = "• Tarea activa: {$activeTaskId}";
        }

        $pendingUpdate = $this->wapSalesAgentService->getPendingUpdate(
            clientId: $this->client->id,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
        );
        if ($pendingUpdate !== null) {
            $field = $pendingUpdate['field'] ?? '-';
            $value = $pendingUpdate['value'] ?? '-';
            $lines[] = "• Update pendiente: {$field} = {$value}";
        }

        $validatorContext = $this->wapSalesAgentService->getValidatorContext(
            clientId: $this->client->id,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
        );
        if ($validatorContext !== null) {
            $domains = $validatorContext['domains'] ?? [];
            $lines[] = '• Validator context: ' . (!empty($domains) ? implode(', ', $domains) : '-');
        }

        $workflow = $this->wapSalesAgentService->getWorkflow(
            clientId: $this->client->id,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
        );
        if ($workflow !== null) {
            $stepsCount = count($workflow['steps'] ?? []);
            $nextStepIndex = (int) ($workflow['next_step_index'] ?? 0);
            $lines[] = "• Workflow: {$stepsCount} steps, next_step_index={$nextStepIndex}";
        }

        if ($conversationStatus === WapSalesAgentConversationService::STATUS_LEAD_NOTES_FLOW) {
            $notesSubState = $this->wapSalesAgentService->getNotesSubState(
                clientId: $this->client->id,
                botPhoneId: $this->connectedPhoneNumberId,
                customerPhone: $this->customerPhoneNumber,
            );
            $lines[] = "• Notes sub-state: {$notesSubState}";
        }

        if ($conversationStatus === WapSalesAgentConversationService::STATUS_TASK_SCOPE) {
            $taskSubState = $this->getTaskSubState();
            $lines[] = "• Task sub-state: {$taskSubState}";
        }

        if ($conversationStatus === WapSalesAgentConversationService::STATUS_AWAITING_LEAD_SALE_INFO) {
            $saleSubState = $this->wapSalesAgentService->getSaleSubState(
                clientId: $this->client->id,
                botPhoneId: $this->connectedPhoneNumberId,
                customerPhone: $this->customerPhoneNumber,
            );
            $saleLeadId = $this->wapSalesAgentService->getSaleLeadId(
                clientId: $this->client->id,
                botPhoneId: $this->connectedPhoneNumberId,
                customerPhone: $this->customerPhoneNumber,
            );
            $lines[] = "• Sale sub-state: {$saleSubState}";
            if ($saleLeadId) {
                $lines[] = "• Sale lead ID: {$saleLeadId}";
            }
        }

        $pendingLeadCandidates = $this->wapSalesAgentService->getPendingCandidateLeadIds(
            clientId: $this->client->id,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
        );
        if (!empty($pendingLeadCandidates)) {
            $lines[] = '• Leads pendientes de selección: ' . count($pendingLeadCandidates);
        }

        $pendingTaskCandidates = $this->wapSalesAgentService->getPendingCandidateTaskIds(
            clientId: $this->client->id,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
        );
        if (!empty($pendingTaskCandidates)) {
            $lines[] = '• Tareas pendientes de selección: ' . count($pendingTaskCandidates);
        }

        $statusMessage = implode("\n", $lines);
        $this->sendAndLogResponse($statusMessage);
        $this->logInfo("status: {$statusMessage}");

        return true;
    }


    private function addIncomingUserMessageToHistory(WhatsAppMetaAPIWebhookPayloadDTO $payloadDTO): void
    {
        $this->wapSalesAgentService->addUserMessage(
            clientId: $this->client->id,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
            message: $this->incomingMessageText,
            messageType: $payloadDTO->getMessageType() ?? 'text',
            context: [
                'button_id' => $payloadDTO->getButtonId(),
                'button_title' => $payloadDTO->getButtonTitle(),
            ],
        );
    }


    private function handleByConversationStatus(string $conversationStatus): bool
    {
        $this->logInfo("Conversation status: {$conversationStatus}");
        return match ($conversationStatus) {
            WapSalesAgentConversationService::STATUS_TASK_SCOPE => $this->handleTaskScope(),
            WapSalesAgentConversationService::STATUS_LEAD_SCOPE => $this->handleLeadScope(),
            WapSalesAgentConversationService::STATUS_LEAD_NOTES_FLOW => $this->handleLeadNotesFlow(),
            WapSalesAgentConversationService::STATUS_VIEWING_STATUSES => $this->handleViewingStatuses(),
            WapSalesAgentConversationService::STATUS_AWAITING_SELECTION => $this->handleAwaitingSelection(),
            WapSalesAgentConversationService::STATUS_AWAITING_CONFIRMATION => $this->handleAwaitingConfirmation(),
            WapSalesAgentConversationService::STATUS_AWAITING_LEAD_SALE_INFO => $this->handleAwaitingLeadSaleInfo(),
            WapSalesAgentConversationService::STATUS_AWAITING_VALIDATOR_INFO => $this->handleAwaitingValidatorInfo(),
            WapSalesAgentConversationService::STATUS_AWAITING_STATUS_SELECTION
                => $this->handleAwaitingStatusSelection()
            ,
            default => $this->handleInit(),
        };
    }

    // =========================================================================
    // CONVERSATION STATUS HANDLERS
    // =========================================================================

    /**
     * Estado READY: Usuario puede pedir cualquier cosa.
     */
    private function handleInit(): bool
    {
        $this->logInfo(">>> handleInit() - mensaje: '{$this->incomingMessageText}'");

        $routerResponse = $this->callRouterAssistant($this->incomingMessageText);
        $route = $routerResponse['route'];
        $domains = $routerResponse['domains'];
        $this->logInfo("Router route: {$route}, domains: " . json_encode($domains));

        if ($this->routeIsNotOperational($route)) {
            $this->logInfo(">>> handleNonOperational() - route: '{$route}'");
            $message = $this->wapSalesAgentService->getNonOperationalMessage($route);
            $this->sendAndLogResponse($message);
            return true;
        }

        if ($this->operationalRouteHasNoDomains($domains)) {
            $this->logInfo("Router returned operational but empty domains — treating as unknown");
            $message = $this->wapSalesAgentService->getNonOperationalMessage('unknown');
            $this->sendAndLogResponse($message);
            return true;
        }

        $validatorsAssistantResult = $this->callDomainValidatorsAssistant(
            message: $this->incomingMessageText,
            domains: $domains,
        );

        if ($this->validatorsStillNeedMoreInfo($validatorsAssistantResult)) {
            $this->logInfo(">>> Status: READY -> AWAITING_VALIDATOR_INFO");
            $missingMessage = $this->persistValidatorContextAndRequestMissingInfo($domains, $validatorsAssistantResult);
            $this->sendAndLogResponse($missingMessage);
            return true;
        }

        $this->logInfo("All validators complete — building workflows");
        return $this->buildAndExecuteWorkflowAssistant(
            domains: $domains,
            conversationMessages: [['role' => 'user', 'content' => $this->incomingMessageText]],
        );
    }


    private function routeIsNotOperational(string $route): bool
    {
        return $route !== 'operational';
    }


    private function operationalRouteHasNoDomains(array $domains): bool
    {
        return collect($domains)->isEmpty();
    }


    private function persistValidatorContextAndRequestMissingInfo(
        array $domains,
        array $validatorResult
    ): string {
        $this->logInfo("Validators incomplete — asking for missing info");
        $conversation = [['role' => 'user', 'content' => $this->incomingMessageText]];
        $this->wapSalesAgentService->setValidatorContext(
            clientId: $this->client->id,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
            context: ['domains' => $domains, 'conversation' => $conversation],
        );
        $this->transitionToAwaitingValidatorInfoStatus();
        $missingMessage = $this->wapSalesAgentService->formatMissingInfoMessage($validatorResult['validationMessages']);
        return $missingMessage;
    }


    /**
     * Estado LEAD_SCOPE: Lead seleccionado, usuario elige acción del menú numerado.
     */
    private function handleLeadScope(): bool
    {
        $this->logInfo(">>> handleLeadScope() - mensaje: '{$this->incomingMessageText}'");

        if ($this->isBackOrCancelMessage($this->incomingMessageText)) {
            return $this->clearActiveLeadScopeAndReturnToReady();
        }

        $option = $this->parseLeadScopeMenuOption($this->incomingMessageText);

        if ($option === null) {
            return $this->handleInit();
        }

        return $this->executeLeadScopeMenuOption($option);
    }


    private function parseLeadScopeMenuOption(string $message): ?int
    {
        $msg = mb_strtolower(trim($message));

        if (preg_match('/^(\d+)$/', $msg, $matches)) {
            $num = (int) $matches[1];
            if (isset(self::LEAD_SCOPE_MENU_OPTIONS[$num])) {
                return $num;
            }
            return null;
        }

        return $this->matchLeadScopeMenuOptionByText($msg);
    }


    private function matchLeadScopeMenuOptionByText(string $message): ?int
    {
        $textMatches = [
            'cambiar nombre'   => 1,
            'cambiar apellido' => 2,
            'cambiar email'    => 3,
            'cambiar telefono' => 4,
            'cambiar estado'   => 5,
            'ver estados'      => 6,
            'ver notas'        => 7,
            'crear nota'       => 8,
            'ver tareas'       => 9,
            'crear tarea'      => 10,
            'salir'            => 11,
        ];

        $normalizedMessage = preg_replace('/\s+/', ' ', trim($message));

        foreach ($textMatches as $text => $optionNumber) {
            if ($normalizedMessage === $text) {
                return $optionNumber;
            }
        }

        return null;
    }


    private function executeLeadScopeMenuOption(int $option): bool
    {
        $this->logInfo(">>> executeLeadScopeMenuOption({$option})");

        if ($option === 11) {
            return $this->clearActiveLeadScopeAndReturnToReady();
        }

        if ($option === 6) {
            return $this->showClientStatusList();
        }

        if ($option === 7) {
            return $this->executeLeadScopeActionDirectly('list_notes');
        }

        if ($option === 9) {
            return $this->executeLeadScopeActionDirectly('list_lead_tasks');
        }

        $label = self::LEAD_SCOPE_MENU_OPTIONS[$option]['label'] ?? null;
        if ($label) {
            $this->logInfo(">>> Delegating to handleInit() with synthetic message: '{$label}'");
            $originalMessage = $this->incomingMessageText;
            $this->incomingMessageText = $label;
            $result = $this->handleInit();
            $this->incomingMessageText = $originalMessage;
            return $result;
        }

        $this->sendAndLogResponse('❌ Opción no válida. Elegí un número del 1 al 11 o escribí la acción.');
        return true;
    }


    private function executeLeadScopeActionDirectly(string $actionType): bool
    {
        $result = $this->wapSalesAgentService->executeAction(
            params: [],
            message: '',
            user: $this->user,
            client: $this->client,
            actionType: $actionType,
            customerPhone: $this->customerPhoneNumber,
            botPhoneId: $this->connectedPhoneNumberId,
        );

        $candidates = $result['candidates'] ?? null;
        if (!empty($candidates)) {
            $this->pauseForCandidatesSelection($result['message'], $candidates);
            return true;
        }

        $this->sendAndLogResponse($result['message']);
        return true;
    }


    private function handleViewingStatuses(): bool
    {
        $this->logInfo(">>> handleViewingStatuses() - mensaje: '{$this->incomingMessageText}'");

        if ($this->isBackOrCancelMessage($this->incomingMessageText)) {
            if ($this->tryRestoreLeadScope()) {
                $this->showLeadScopeMenu();
            } else {
                $this->returnToReadyContext();
            }
            return true;
        }

        return $this->showClientStatusList();
    }


    private function showClientStatusList(): bool
    {
        $this->logInfo(">>> showClientStatusList()");

        $statusMap = $this->wapSalesAgentService->getStatusNameMap($this->client);

        if (empty($statusMap)) {
            $this->sendAndLogResponse('❌ No hay estados configurados para este cliente.');
            $this->showLeadScopeMenu();
            return true;
        }

        $this->wapSalesAgentService->setConversationStatus(
            clientId: $this->client->id,
            customerPhone: $this->customerPhoneNumber,
            status: WapSalesAgentConversationService::STATUS_VIEWING_STATUSES,
            botPhoneId: $this->connectedPhoneNumberId,
        );

        asort($statusMap);
        $list = "📋 Estados disponibles:\n\n";
        foreach ($statusMap as $id => $name) {
            $list .= "{$name} ({$id})\n";
        }
        $list .= "\n🤖 Escribí \"volver\" para regresar al menú del prospecto.";

        $this->sendAndLogResponse($list);
        return true;
    }


    private function clearActiveLeadScopeAndReturnToReady(): bool
    {
        $this->logInfo(">>> Clearing active lead and returning to READY");
        $this->clearNotesContext();
        $this->wapSalesAgentService->setActiveLead(
            $this->client->id, $this->customerPhoneNumber, null, $this->connectedPhoneNumberId
        );
        $this->returnToReadyContext();
        $this->sendAndLogResponse(
            '🤖 Volviste al inicio. Buscá un prospecto por ID, nombre, email o teléfono.'
        );
        return true;
    }


    // ══════════════════════════════════════════════════════════════════════════
    // TASK CONTEXT HANDLERS
    // ══════════════════════════════════════════════════════════════════════════

    private function handleTaskScope(): bool
    {
        $this->logInfo(">>> handleTaskScope() - mensaje: '{$this->incomingMessageText}'");

        if ($this->isBackOrCancelMessage($this->incomingMessageText)) {
            return $this->handleTaskScopeBackOrCancel();
        }

        $subState = $this->getTaskSubState();
        $this->logInfo("Task sub-state: {$subState}");

        return match ($subState) {
            WapSalesAgentConversationService::TASK_SUB_VIEWING => $this->handleTaskScopeViewingSubState(),
            WapSalesAgentConversationService::TASK_SUB_AWAITING_CONTENT =>
                $this->handleTaskScopeAwaitingContentSubState()
            ,
            WapSalesAgentConversationService::TASK_SUB_POST_CREATE =>
                $this->handleTaskScopePostCreateSubState()
            ,
            default => $this->handleTaskScopeListingSubState(),
        };
    }

    private function handleTaskScopeListingSubState(): bool
    {
        $pendingIds = $this->wapSalesAgentService->getPendingCandidateTaskIds(
            $this->client->id, $this->customerPhoneNumber, $this->connectedPhoneNumberId
        );

        $selectedIndex = $this->parseSelectionIndex($this->incomingMessageText);
        if ($selectedIndex !== null) {
            if (isset($pendingIds[$selectedIndex])) {
                $this->executeTaskAction(
                    actionType: 'view_task',
                    params: ['taskIndex' => $selectedIndex],
                );
                return true;
            }

            $this->sendAndLogResponse($this->wapSalesAgentService->getInvalidSelectionMessage());
            return true;
        }

        $this->returnToReadyContext();

        return $this->handleInit();
    }

    private function getTaskSubState(): string
    {
        return $this->wapSalesAgentService->getTaskSubState(
            $this->client->id,
            $this->customerPhoneNumber,
            $this->connectedPhoneNumberId
        );
    }

    private function handleTaskScopeViewingSubState(): bool
    {
        $this->returnToReadyContext();

        return $this->handleInit();
    }

    private function handleTaskScopePostCreateSubState(): bool
    {
        $this->clearTaskContext();
        if ($this->tryRestoreLeadScope()) {
            $this->showLeadScopeMenu();
        } else {
            $this->returnToReadyContext();
        }
        return true;
    }

    private function handleTaskScopeAwaitingContentSubState(): bool
    {
        $title = trim($this->incomingMessageText);
        if ($title === '') {
            $this->sendAndLogResponse('🤖 Necesito al menos un título para crear la tarea.');
            return true;
        }

        $this->executeTaskAction(
            actionType: 'create_task',
            message: $title,
            params: ['title' => $title],
        );
        return true;
    }

    private function handleTaskScopeBackOrCancel(): bool
    {
        $this->logInfo(">>> handleTaskScopeBackOrCancel()");

        $clientId = $this->client->id;
        $subState = $this->getTaskSubState();

        if ($subState === WapSalesAgentConversationService::TASK_SUB_AWAITING_CONTENT) {
            $this->transitionToTaskScopeListingSubState();
            $this->sendAndLogResponse($this->wapSalesAgentService->getTaskListHelpMessage());
            return true;
        }

        $hasActiveLead = $this->wapSalesAgentService->getActiveLeadId(
            $clientId, $this->customerPhoneNumber, $this->connectedPhoneNumberId
        ) !== null;

        $this->clearTaskContext();

        if ($hasActiveLead) {
            $this->logInfo("hasActiveLead: " . $hasActiveLead);
            $this->tryRestoreLeadScope();
            $this->showLeadScopeMenu();
            return true;
        }

        $this->sendAndLogResponse($this->wapSalesAgentService->getCancelledMessage());
        return true;
    }

    private function executeTaskAction(string $actionType, string $message = '', array $params = []): void
    {
        $result = $this->wapSalesAgentService->executeAction(
            params: $params,
            message: $message,
            user: $this->user,
            client: $this->client,
            actionType: $actionType,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
        );
        $this->sendAndLogResponse($result['message']);
    }


    // ══════════════════════════════════════════════════════════════════════════
    // NOTES CONTEXT HANDLERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Estado LEAD_NOTES_FLOW: Usuario navega notas del prospecto (listar, ver, crear, eliminar).
     */
    private function handleLeadNotesFlow(): bool
    {
        $this->logInfo(">>> handleLeadNotesFlow() - mensaje: '{$this->incomingMessageText}'");

        if ($this->isBackOrCancelMessage($this->incomingMessageText)) {
            return $this->handleLeadNotesBackOrCancel();
        }

        $subState = $this->wapSalesAgentService->getNotesSubState(
            $this->client->id, $this->customerPhoneNumber, $this->connectedPhoneNumberId
        );
        $this->logInfo("Notes sub-state: {$subState}");

        return match ($subState) {
            WapSalesAgentConversationService::NOTES_SUB_AWAITING_CONTENT =>
                $this->handleLeadNotesAwaitingContentSubState()
            ,
            WapSalesAgentConversationService::NOTES_SUB_VIEWING => $this->handleLeadNotesViewingSubState(),
            WapSalesAgentConversationService::NOTES_SUB_POST_CREATE =>
                $this->handleLeadNotesPostCreateSubState()
            ,
            default => $this->handleLeadNotesListingSubState(),
        };
    }

    private function handleLeadNotesAwaitingContentSubState(): bool
    {
        $this->executeLeadNoteAction(
            actionType: 'create_note',
            message: $this->incomingMessageText,
            params: ['content' => $this->incomingMessageText],
        );
        return true;
    }

    private function handleLeadNotesViewingSubState(): bool
    {
        $msg = mb_strtolower(trim($this->incomingMessageText));

        if (in_array($msg, ['eliminar', 'borrar', 'eliminar nota', 'borrar nota'])) {
            $this->executeLeadNoteAction(
                actionType: 'delete_note',
                params: ['useSelectedNote' => true],
            );
            return true;
        }

        $this->sendAndLogResponse('🤖 Decí "eliminar" para borrar esta nota o "volver" al listado.');
        return true;
    }

    private function handleLeadNotesPostCreateSubState(): bool
    {
        $this->clearNotesContext();
        if ($this->tryRestoreLeadScope()) {
            $this->showLeadScopeMenu();
        } else {
            $this->returnToReadyContext();
        }
        return true;
    }

    private function handleLeadNotesListingSubState(): bool
    {
        $pendingIds = $this->wapSalesAgentService->getNotesPendingIds(
            $this->client->id, $this->customerPhoneNumber, $this->connectedPhoneNumberId
        );

        $deleteNoteIndex = $this->parseDeleteNoteIndex($this->incomingMessageText, count($pendingIds));
        if ($deleteNoteIndex !== null) {
            $this->executeLeadNoteAction(
                actionType: 'delete_note',
                params: ['noteIndex' => $deleteNoteIndex],
            );
            return true;
        }

        $createNoteContent = $this->parseCreateNoteWithContent($this->incomingMessageText);
        if ($createNoteContent !== null) {
            $this->executeLeadNoteAction(
                actionType: 'create_note',
                message: $createNoteContent !== '' ? $createNoteContent : '',
                params: $createNoteContent !== '' ? ['content' => $createNoteContent] : [],
            );
            return true;
        }

        $selectedIndex = $this->parseSelectionIndex($this->incomingMessageText);
        if ($selectedIndex !== null && isset($pendingIds[$selectedIndex])) {
            $this->executeLeadNoteAction(
                actionType: 'view_note',
                params: ['noteIndex' => $selectedIndex + 1],
            );
            return true;
        }

        $this->sendAndLogResponse(
            $this->wapSalesAgentService->getLeadNotesMessage(
                clientId: $this->client->id,
                customerPhone: $this->customerPhoneNumber,
                botPhoneId: $this->connectedPhoneNumberId,
            )
        );
        return true;
    }

    private function handleLeadNotesBackOrCancel(): bool
    {
        $clientId = $this->client->id;
        $subState = $this->wapSalesAgentService->getNotesSubState(
            $clientId, $this->customerPhoneNumber, $this->connectedPhoneNumberId
        );

        if ($subState === WapSalesAgentConversationService::NOTES_SUB_VIEWING
            || $subState === WapSalesAgentConversationService::NOTES_SUB_AWAITING_CONTENT
        ) {
            $this->executeLeadNoteAction(actionType: 'back_to_notes_list');
            return true;
        }

        $this->clearNotesContext();
        if ($this->tryRestoreLeadScope()) {
            $this->showLeadScopeMenu();
        } else {
            $this->returnToReadyContext();
        }
        return true;
    }

    private function executeLeadNoteAction(string $actionType, string $message = '', array $params = []): void
    {
        $result = $this->wapSalesAgentService->executeAction(
            client: $this->client,
            user: $this->user,
            customerPhone: $this->customerPhoneNumber,
            actionType: $actionType,
            botPhoneId: $this->connectedPhoneNumberId,
            message: $message,
            params: $params,
        );
        $this->sendAndLogResponse($result['message']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SALE INFO HANDLER
    // ══════════════════════════════════════════════════════════════════════════

    private function handleAwaitingLeadSaleInfo(): bool
    {
        $this->logInfo(">>> handleAwaitingLeadSaleInfo() - mensaje: '{$this->incomingMessageText}'");

        if ($this->isBackOrCancelMessage($this->incomingMessageText)) {
            return $this->handleLeadSaleBackOrCancel();
        }

        $subState = $this->wapSalesAgentService->getSaleSubState(
            $this->client->id, $this->customerPhoneNumber, $this->connectedPhoneNumberId
        );
        $this->logInfo("Sale sub-state: {$subState}");

        return match ($subState) {
            WapSalesAgentConversationService::SALE_SUB_COLLECTING => $this->handleLeadSaleCollectingSubState(),
            default                                   => $this->handleLeadSaleConfirmingSubState(),
        };
    }

    private function handleLeadSaleConfirmingSubState(): bool
    {
        if ($this->isConfirmMessage($this->incomingMessageText)) {
            $this->logInfo(">>> User wants to create sale - asking for data");
            $this->transitionToLeadSaleCollectingSubState();
            $this->sendAndLogResponse($this->wapSalesAgentService->getSaleCollectingMessage());
            return true;
        }

        if ($this->isRejectMessage($this->incomingMessageText)) {
            $this->logInfo(">>> User rejected sale creation");
            return $this->handleLeadSaleBackOrCancel();
        }

        $this->sendAndLogResponse($this->wapSalesAgentService->getPromptConfirmationMessage());
        return true;
    }

    private function handleLeadSaleCollectingSubState(): bool
    {
        $parsed = $this->extractSaleDataFromMessage($this->incomingMessageText);

        $isSaleDataComplete = (bool) ($parsed['is_sale_data_complete'] ?? false);
        $amount = $parsed['amount'] ?? null;

        if (!$isSaleDataComplete || $amount === null) {
            $this->sendAndLogResponse($parsed['clarification_message']);
            return true;
        }

        $leadId = $this->wapSalesAgentService->getSaleLeadId(
            $this->client->id, $this->customerPhoneNumber, $this->connectedPhoneNumberId
        );

        if (!$leadId) {
            $this->logInfo(">>> No sale lead ID found - clearing sale context");
            $this->clearLeadSaleContext();
            $this->sendAndLogResponse('❌ No se encontró el prospecto. Operación cancelada.');
            return true;
        }

        $result = $this->wapSalesAgentService->createLeadSale(
            client: $this->client,
            user: $this->user,
            leadId: $leadId,
            amount: $amount,
            description: $parsed['description'],
        );

        $this->sendAndLogResponse($result['message']);
        $this->clearLeadSaleContext();
        if (!$this->tryRestoreLeadScope()) {
            $this->returnToReadyContext();
        }
        $this->logInfo(">>> Sale created - returned to context");
        return true;
    }

    private function handleLeadSaleBackOrCancel(): bool
    {
        $leadId = $this->wapSalesAgentService->getSaleLeadId(
            $this->client->id, $this->customerPhoneNumber, $this->connectedPhoneNumberId
        );

        $this->clearLeadSaleContext();

        if ($leadId) {
            $this->tryRestoreLeadScope();
            $this->showLeadScopeMenu();
            $this->logInfo(">>> Sale skipped - showing lead");
            return true;
        }

        $this->sendAndLogResponse('👍 Listo, no se registró la venta.');
        $this->returnToReadyContext();
        $this->logInfo(">>> Sale skipped");
        return true;
    }


    /**
     * Extrae datos de venta con AI.
     *
     * @return array{amount: ?float, description: ?string, is_sale_data_complete: bool, clarification_message: string}
     */
    private function extractSaleDataFromMessage(string $message): array
    {
        $aiResponse = $this->callSaleDataExtractorAssistant($message);
        $normalized = $this->normalizeSaleDataExtractionResponse($aiResponse);

        if ($normalized !== null) {
            return $normalized;
        }

        return [
            'amount' => null,
            'description' => null,
            'is_sale_data_complete' => false,
            'clarification_message' => '🤖 No pude identificar el monto. ¿Cuál es el importe de la venta?',
        ];
    }


    /**
     * Parsea "crear nota", "crear una nota lorem ipsum", "crear nota: texto", etc.
     * Retorna: null si no coincide; '' si coincide sin contenido; el contenido si coincide con contenido.
     */
    private function parseCreateNoteWithContent(string $message): ?string
    {
        $msg = mb_strtolower(trim($message));
        $prefixes = ['crear nota', 'crear una nota', 'agregar nota', 'nueva nota'];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($msg, $prefix)) {
                $rest = trim(mb_substr($message, mb_strlen($prefix)));
                $rest = preg_replace('/^[:\s]+/', '', $rest);

                return $rest;
            }
        }

        return null;
    }


    private function parseCreateTaskWithContent(string $message): ?string
    {
        $msg = mb_strtolower(trim($message));
        $prefixes = ['crear tarea', 'crear una tarea', 'agregar tarea', 'nueva tarea'];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($msg, $prefix)) {
                $rest = trim(mb_substr($message, mb_strlen($prefix)));
                $rest = preg_replace('/^[:\s]+/', '', $rest);

                return $rest;
            }
        }

        return null;
    }


    /**
     * Ejecuta el extractor estructurado de datos de venta.
     */
    private function callSaleDataExtractorAssistant(string $message): array
    {
        $systemPrompt = $this->promptHelper->getSaleDataExtractionPrompt();
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message],
        ];

        $responseFormat = [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'SaleDataExtractionResponse',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['amount', 'description', 'is_sale_data_complete', 'clarification_message'],
                    'properties' => [
                        'amount' => [
                            'type' => ['number', 'null'],
                        ],
                        'description' => [
                            'type' => ['string', 'null'],
                        ],
                        'is_sale_data_complete' => [
                            'type' => 'boolean',
                        ],
                        'clarification_message' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];

        $responseStr = $this->callOpenAI($messages, $responseFormat);
        return $this->parseJsonResponse($responseStr);
    }


    /**
     * Normaliza y valida la respuesta del extractor AI.
     *
     * @param  array<string, mixed> $response
     * @return array{amount: ?float, description: ?string,
     *     is_sale_data_complete: bool,
     *     clarification_message: string}|null
     */
    private function normalizeSaleDataExtractionResponse(array $response): ?array
    {
        if (empty($response)) {
            return null;
        }

        $rawAmount = $response['amount'] ?? null;
        $amount = is_numeric($rawAmount) ? (float) $rawAmount : null;
        $description = isset($response['description']) && is_string($response['description'])
            ? trim($response['description'])
            : null
        ;
        $isComplete = (bool) ($response['is_sale_data_complete'] ?? false);
        $clarificationMessage = trim((string) ($response['clarification_message'] ?? ''));

        if ($amount !== null && $amount <= 0) {
            $amount = null;
        }

        if ($description === '') {
            $description = null;
        }

        if ($isComplete && $amount !== null) {
            return [
                'amount' => $amount,
                'description' => $description,
                'is_sale_data_complete' => true,
                'clarification_message' => '',
            ];
        }

        if ($clarificationMessage === '') {
            $clarificationMessage = '🤖 No pude identificar el monto. ¿Cuál es el importe de la venta?';
        } elseif (!str_starts_with($clarificationMessage, '🤖')) {
            $clarificationMessage = "🤖 {$clarificationMessage}";
        }

        return [
            'amount' => null,
            'description' => $description,
            'is_sale_data_complete' => false,
            'clarification_message' => $clarificationMessage,
        ];
    }


    /**
     * Parsea "eliminar 1", "eliminar la 2", "eliminar la primera", etc. Retorna índice 1-based o null.
     */
    private function parseDeleteNoteIndex(string $message, int $notesCount): ?int
    {
        $msg = mb_strtolower(trim($message));
        if ($notesCount < 1) {
            return null;
        }

        // Probar prefijos más largos primero
        $prefixes = ['eliminar la ', 'borrar la ', 'eliminar nota ', 'borrar nota ', 'eliminar ', 'borrar '];
        $rest = $msg;
        foreach ($prefixes as $prefix) {
            if (str_starts_with($msg, $prefix)) {
                $rest = trim(mb_substr($msg, mb_strlen($prefix)));
                break;
            }
        }
        if ($rest === $msg || $rest === '') {
            return null;
        }

        // "última" → última nota
        if (in_array($rest, ['ultima', 'última', 'ultimo', 'último'])) {
            return $notesCount;
        }

        $zeroBased = $this->parseSelectionIndex($rest);
        if ($zeroBased !== null && $zeroBased >= 0 && $zeroBased < $notesCount) {
            return $zeroBased + 1;
        }

        return null;
    }


    // =========================================================================
    // SELECTION FLOW
    // Entrada:
    // - handleAwaitingSelection()
    //
    // Decisiones:
    // - parsea el índice elegido
    // - resuelve si la selección corresponde a leads o tareas
    //
    // Efectos:
    // - activa el contexto elegido
    // - continúa el workflow o responde el detalle seleccionado
    // =========================================================================

    /**
     * Estado AWAITING_SELECTION: Usuario debe elegir de una lista (leads o tareas).
     */
    private function handleAwaitingSelection(): bool
    {
        $this->logInfo(">>> handleAwaitingSelection() - mensaje: '{$this->incomingMessageText}'");

        $clientId = $this->client->id;

        if ($this->isBackOrCancelMessage($this->incomingMessageText)) {
            $message = $this->wapSalesAgentService->cancelSelection(
                $clientId,
                $this->customerPhoneNumber,
                $this->connectedPhoneNumberId
            );
            $this->sendAndLogResponse($message);
            return true;
        }

        $selectedIndex = $this->parseSelectionIndex($this->incomingMessageText);
        $this->logInfo("Selection index parsed: " . ($selectedIndex ?? 'null'));

        if ($selectedIndex === null) {
            $this->sendAndLogResponse($this->wapSalesAgentService->getSelectByNumberOrCancelMessage());
            return true;
        }

        $pendingCandidateLeadIds = $this->wapSalesAgentService->getPendingCandidateLeadIds(
            $clientId,
            $this->customerPhoneNumber,
            $this->connectedPhoneNumberId
        );
        if (isset($pendingCandidateLeadIds[$selectedIndex])) {
            return $this->handleLeadSelectionAndExecuteWorkflow($selectedIndex, $pendingCandidateLeadIds);
        }

        $pendingCandidateTaskIds = $this->wapSalesAgentService->getPendingCandidateTaskIds(
            $clientId,
            $this->customerPhoneNumber,
            $this->connectedPhoneNumberId
        );
        if (isset($pendingCandidateTaskIds[$selectedIndex])) {
            return $this->handleTaskSelectionAndExecuteWorkflow($selectedIndex, $pendingCandidateTaskIds);
        }

        $this->sendAndLogResponse($this->wapSalesAgentService->getInvalidSelectionMessage());
        return true;
    }

    /**
     * Flujo cuando el usuario selecciona un lead de la lista
     */
    private function handleLeadSelectionAndExecuteWorkflow(int $selectedIndex, array $pendingCandidateLeadIds): bool
    {
        if (!$this->isValidLeadSelectedIndex($selectedIndex, $pendingCandidateLeadIds)) {
            return $this->respondInvalidSelection();
        }

        $selectedLeadId = $pendingCandidateLeadIds[$selectedIndex];
        $this->activateLeadAndShowActionMenu($selectedLeadId);

        return $this->resumeWorkflowOrRespond(function () use ($selectedLeadId) {
            $message = $this->selectedLeadInfoMessage($selectedLeadId);
            $this->sendAndLogResponse($message);
        });
    }


    private function isValidLeadSelectedIndex(int $selectedIndex, array $pendingCandidateLeadIds): bool
    {
        return isset($pendingCandidateLeadIds[$selectedIndex]);
    }


    private function isValidTaskSelectedIndex(int $selectedIndex, array $pendingCandidateTaskIds): bool
    {
        return isset($pendingCandidateTaskIds[$selectedIndex]);
    }


    private function activateLeadAndShowActionMenu(int $selectedLeadId): void
    {
        $this->wapSalesAgentService->setActiveLead(
            $this->client->id, $this->customerPhoneNumber, $selectedLeadId, $this->connectedPhoneNumberId
        );
        $this->logInfo("Lead selected: {$selectedLeadId}");
        $this->transitionToLeadScopeStatus();
        $this->logInfo(">>> Status: AWAITING_SELECTION -> LEAD_SCOPE");
    }


    private function hasPendingWorkflowSteps(): bool
    {
        $workflow = $this->wapSalesAgentService->getWorkflow(
            $this->client->id, $this->customerPhoneNumber, $this->connectedPhoneNumberId
        );
        if (!$workflow || empty($workflow['steps'])) {
            return false;
        }
        $nextStepIndex = $workflow['next_step_index'] ?? 0;
        if ($nextStepIndex < count($workflow['steps'])) {
            return true;
        }
        $this->wapSalesAgentService->clearWorkflow(
            clientId: $this->client->id,
            customerPhone: $this->customerPhoneNumber,
            botPhoneId: $this->connectedPhoneNumberId,
        );
        return false;
    }


    private function selectedLeadInfoMessage(int $selectedLeadId): string
    {
        $lead = $this->findConversationLead($selectedLeadId, withNotesCount: true);

        return $lead
            ? $this->wapSalesAgentService->formatLeadInfo($lead)
            : $this->wapSalesAgentService->getLeadNotFoundMessage()
        ;
    }


    /**
     * Flujo cuando el usuario selecciona una tarea de la lista
     */
    private function handleTaskSelectionAndExecuteWorkflow(int $selectedIndex, array $pendingCandidateTaskIds): bool
    {
        if (!$this->isValidTaskSelectedIndex($selectedIndex, $pendingCandidateTaskIds)) {
            return $this->respondInvalidSelection();
        }

        $selectedTaskId = $pendingCandidateTaskIds[$selectedIndex];
        $this->activateTaskAndMarkConversationReady($selectedTaskId);

        return $this->resumeWorkflowOrRespond(fn () => $this->sendTaskInfoToUser($selectedTaskId));
    }


    private function activateTaskAndMarkConversationReady(int $selectedTaskId): void
    {
        $this->wapSalesAgentService->setActiveTaskId(
            $this->client->id, $this->customerPhoneNumber, $selectedTaskId, $this->connectedPhoneNumberId
        );
        $this->transitionToTaskScopeViewingSubState();
        $this->logInfo("Task selected: {$selectedTaskId}");
        $this->logInfo(">>> Status: AWAITING_SELECTION -> TASK_SCOPE");
    }


    private function sendTaskInfoToUser(int $selectedTaskId): void
    {
        $taskInfo = $this->wapSalesAgentService->formatTaskInfo($this->client, $selectedTaskId);
        $message = $taskInfo
            ? $this->wapSalesAgentService->getTaskSelectedMessage($taskInfo)
            : $this->wapSalesAgentService->getTaskNotFoundMessage();
        $this->sendAndLogResponse($message);
    }

    // =========================================================================
    // CONFIRMATION FLOW
    // Entrada:
    // - handleAwaitingConfirmation()
    //
    // Decisiones:
    // - confirmar, rechazar o volver a pedir respuesta válida
    //
    // Efectos:
    // - ejecutar pending update
    // - continuar workflow
    // - limpiar estado de confirmación
    // =========================================================================

    /**
     * Estado AWAITING_CONFIRMATION: Usuario debe confirmar o cancelar con "sí" o "no".
     */
    private function handleAwaitingConfirmation(): bool
    {
        $this->logInfo(">>> handleAwaitingConfirmation() - mensaje: '{$this->incomingMessageText}'");

        if ($this->isInvalidConfirmationReply()) {
            $message = $this->wapSalesAgentService->getPromptConfirmationMessage();
            $this->sendAndLogResponse($message);
            return true;
        }

        if ($this->isRejectMessage($this->incomingMessageText)) {
            return $this->cancelConfirmationAndReturnToReady();
        }

        return $this->executePendingWorkflowSteps();
    }


    private function isInvalidConfirmationReply(): bool
    {
        $isConfirm = $this->isConfirmMessage($this->incomingMessageText);
        $isReject = $this->isRejectMessage($this->incomingMessageText);
        $this->logInfo(
            "Confirmation parsed: confirm=" . ($isConfirm ? 'true' : 'false') . ", reject=" .
            ($isReject ? 'true' : 'false')
        );
        return !$isConfirm && !$isReject;
    }


    private function cancelConfirmationAndReturnToReady(): bool
    {
        $this->logInfo(">>> User rejected - cancelling operation");
        $this->wapSalesAgentService->clearPendingUpdate(
            clientId: $this->client->id,
            customerPhone: $this->customerPhoneNumber,
            botPhoneId: $this->connectedPhoneNumberId,
        );
        $this->wapSalesAgentService->clearWorkflow(
            clientId: $this->client->id,
            customerPhone: $this->customerPhoneNumber,
            botPhoneId: $this->connectedPhoneNumberId,
        );
        $this->sendCancelledConfirmationMessage();
        return true;
    }

    private function sendCancelledConfirmationMessage(): void
    {
        $activeLeadId = $this->wapSalesAgentService->getActiveLeadId(
            $this->client->id,
            $this->customerPhoneNumber,
            $this->connectedPhoneNumberId
        );

        if (!$activeLeadId) {
            $this->sendAndLogResponse($this->wapSalesAgentService->getCancelledMessage());
            $this->returnToReadyContext();
            return;
        }

        $lead = $this->findConversationLead($activeLeadId, withNotesCount: true);

        if (!$lead) {
            $this->sendAndLogResponse($this->wapSalesAgentService->getCancelledMessage());
            $this->tryRestoreLeadScope();
            return;
        }

        $message = <<<TEXT
            🤖 Operación cancelada. ¿En qué puedo ayudarte con el prospecto?

            {$this->wapSalesAgentService->formatLeadActionMenu($lead)}
        TEXT;

        $this->sendAndLogResponse($message);
        $this->tryRestoreLeadScope();
    }


    private function executePendingWorkflowSteps(): bool
    {
        $this->logInfo(">>> User confirmed - executing pending action");
        $pendingUpdate = $this->getPendingConversationUpdate();
        $this->logInfo("Pending update: " . json_encode($pendingUpdate, JSON_UNESCAPED_UNICODE));

        if ($pendingUpdate) {
            $confirmedUpdateResult = $this->confirmPendingLeadUpdate();
            if ($this->shouldOfferSaleCreation($pendingUpdate, $confirmedUpdateResult)) {
                $this->sendStatusUpdateSummaryWithoutMenu($confirmedUpdateResult);
                $this->initSaleConfirmationFlow($confirmedUpdateResult);
                return true;
            }

            $this->sendAndLogResponse($confirmedUpdateResult['message']);
        }

        $this->clearConfirmationStateAndRestoreContextSilently();
        return $this->resumePendingWorkflowAfterConfirmation();
    }


    private function getPendingConversationUpdate(): ?array
    {
        return $this->wapSalesAgentService->getPendingUpdate(
            $this->client->id,
            $this->customerPhoneNumber,
            $this->connectedPhoneNumberId
        );
    }


    private function confirmPendingLeadUpdate(): array
    {
        return $this->wapSalesAgentService->executeAction(
            client: $this->client,
            user: $this->user,
            customerPhone: $this->customerPhoneNumber,
            botPhoneId: $this->connectedPhoneNumberId,
            actionType: 'confirm_lead_update',
            message: '',
            params: [],
        );
    }


    private function resumePendingWorkflowAfterConfirmation(): bool
    {
        if ($this->hasPendingWorkflowSteps()) {
            return $this->executeWorkflowSteps();
        }

        return true;
    }


    private function shouldOfferSaleCreation(array $pendingUpdate, array $result): bool
    {
        if ($pendingUpdate['field'] !== 'status') {
            return false;
        }

        if (!empty($result['isError'])) {
            return false;
        }

        $clientSettings = $this->client->clientSettings;
        if (!$clientSettings->register_sales_info) {
            return false;
        }

        $leadId = $result['lead']['id'] ?? null;
        if (!$leadId) {
            return false;
        }

        $lead = Lead::with('status')->find($leadId);

        return $lead && $lead->status && $lead->status->sale_probability === 100;
    }


    private function sendStatusUpdateSummaryWithoutMenu(array $result): void
    {
        $leadId = $result['lead']['id'] ?? null;

        if (!$leadId) {
            $this->sendAndLogResponse($result['message']);
            return;
        }

        $lead = $this->findConversationLead($leadId);

        if (!$lead) {
            $this->sendAndLogResponse($result['message']);
            return;
        }

        $this->sendAndLogResponse($this->wapSalesAgentService->formatUpdatedLeadSummary($lead));
    }


    private function initSaleConfirmationFlow(array $result): void
    {
        $leadId = $result['lead']['id'];
        $statusName = $result['lead']['status'] ?? 'el estado seleccionado';

        $this->transitionToLeadSaleConfirmingSubState($leadId);

        $msg = <<<TEXT
            💰 El estado "{$statusName}" tiene 100% de probabilidad de venta.
            ¿Querés registrar una venta asociada a este prospecto?

            Respondé "si" para crear la venta o "no" para continuar sin registrarla.
        TEXT;

        $this->sendAndLogResponse($msg);
        $this->logInfo(">>> Status: AWAITING_CONFIRMATION -> AWAITING_LEAD_SALE_INFO (sub: confirming)");
    }


    private function clearConfirmationStateAndRestoreContextSilently(): void
    {
        $this->clearPendingConfirmationState();

        if (!$this->tryRestoreLeadScope()) {
            $this->returnToReadyContext();
        }

        $this->logInfo(">>> Status: AWAITING_CONFIRMATION -> restored context silently");
    }


    private function clearPendingConfirmationState(): void
    {
        $this->wapSalesAgentService->clearPendingUpdate(
            $this->client->id,
            $this->customerPhoneNumber,
            $this->connectedPhoneNumberId
        );
    }

    // =========================================================================
    // VALIDATOR FLOW
    // Entrada:
    // - handleAwaitingValidatorInfo()
    //
    // Decisiones:
    // - reconstruir conversación y volver a validar
    // - pedir más info o continuar al workflow builder
    //
    // Efectos:
    // - persistir/limpiar validatorContext
    // - volver a READY o continuar el flujo
    // =========================================================================

    /**
     * Estado AWAITING_VALIDATOR_INFO: Esperando información faltante del usuario.
     */
    private function handleAwaitingValidatorInfo(): bool
    {
        $this->logInfo(">>> handleAwaitingValidatorInfo() - mensaje: '{$this->incomingMessageText}'");

        if ($this->isBackOrCancelMessage($this->incomingMessageText)) {
            return $this->cancelValidatorAndReturnToReady();
        }

        $validatorContext = $this->getPersistedValidatorContext();
        if (!$validatorContext) {
            return $this->restartFlowWhenValidatorContextIsMissing();
        }

        $validatorResolution = $this->resolveValidatorConversation($validatorContext);
        $validatorResult = $validatorResolution['result'];

        if ($this->validatorsStillNeedMoreInfo($validatorResult)) {
            $missingMessage = $this->saveValidatorContextAndAskForMissingInfo(
                $validatorResolution['domains'],
                $validatorResolution['conversation'],
                $validatorResult
            );
            $this->sendAndLogResponse($missingMessage);
            return true;
        }

        return $this->clearValidatorAndExecuteWorkflowAssistant(
            $validatorResolution['domains'],
            $validatorResolution['conversation']
        );
    }


    private function cancelValidatorAndReturnToReady(): bool
    {
        $this->logInfo(">>> User cancelled validator loop");
        $this->wapSalesAgentService->clearValidatorContext(
            clientId: $this->client->id,
            customerPhone: $this->customerPhoneNumber,
            botPhoneId: $this->connectedPhoneNumberId,
        );
        $this->sendAndLogResponse($this->wapSalesAgentService->getCancelledMessage());
        if ($this->tryRestoreLeadScope()) {
            $this->showLeadScopeMenu();
        } else {
            $this->returnToReadyContext();
        }
        return true;
    }


    private function getPersistedValidatorContext(): ?array
    {
        return $this->wapSalesAgentService->getValidatorContext(
            $this->client->id,
            $this->customerPhoneNumber,
            $this->connectedPhoneNumberId
        );
    }


    private function restartFlowWhenValidatorContextIsMissing(): bool
    {
        $this->logInfo(">>> No validator context found — resetting to READY");
        $this->returnToReadyContext();

        return $this->handleInit();
    }


    private function resolveValidatorConversation(array $validatorContext): array
    {
        $domains = $validatorContext['domains'] ?? [];
        $conversation = $this->appendUserMessageToConversation(
            $validatorContext['conversation'] ?? [],
            $this->incomingMessageText
        );

        return [
            'domains' => $domains,
            'conversation' => $conversation,
            'result' => $this->revalidateWithConversation($domains, $conversation),
        ];
    }


    private function appendUserMessageToConversation(array $conversation, string $userMessage): array
    {
        $conversation[] = ['role' => 'user', 'content' => $userMessage];
        return $conversation;
    }


    private function revalidateWithConversation(array $domains, array $conversation): array
    {
        return $this->callDomainValidatorsAssistant(
            message: $this->incomingMessageText,
            domains: $domains,
            conversationMessages: $conversation,
        );
    }


    private function validatorsStillNeedMoreInfo(array $validatorResult): bool
    {
        return !empty($validatorResult['validationMessages']);
    }


    private function saveValidatorContextAndAskForMissingInfo(
        array $domains,
        array $conversation,
        array $validatorResult
    ): string {
        $this->logInfo("Validators still incomplete — asking again");
        $this->wapSalesAgentService->setValidatorContext(
            clientId: $this->client->id,
            customerPhone: $this->customerPhoneNumber,
            context: ['domains' => $domains, 'conversation' => $conversation],
            botPhoneId: $this->connectedPhoneNumberId,
        );
        $missingMessage = $this->wapSalesAgentService->formatMissingInfoMessage($validatorResult['validationMessages']);
        return $missingMessage;
    }


    private function clearValidatorAndExecuteWorkflowAssistant(array $domains, array $conversation): bool
    {
        $this->logInfo("All validators now complete — proceeding to workflows");
        $this->wapSalesAgentService->clearValidatorContext(
            clientId: $this->client->id,
            customerPhone: $this->customerPhoneNumber,
            botPhoneId: $this->connectedPhoneNumberId,
        );
        $this->returnToReadyContext();
        $this->logInfo(">>> Status: AWAITING_VALIDATOR_INFO -> READY (complete)");
        return $this->buildAndExecuteWorkflowAssistant(
            domains: $domains,
            conversationMessages: $conversation,
        );
    }


    // =========================================================================
    // AWAITING STATUS SELECTION HANDLER
    // =========================================================================

    /**
     * Estado AWAITING_STATUS_SELECTION: el operador debe elegir un estado de la lista de sugerencias.
     */
    private function handleAwaitingStatusSelection(): bool
    {
        $this->logInfo(">>> handleAwaitingStatusSelection() - mensaje: '{$this->incomingMessageText}'");

        if ($this->isBackOrCancelMessage($this->incomingMessageText)) {
            return $this->cancelStatusSelectionAndRestore();
        }

        $suggestions = $this->wapSalesAgentService->getPendingStatusSuggestions(
            $this->client->id, $this->customerPhoneNumber, $this->connectedPhoneNumberId
        );

        if (empty($suggestions)) {
            $this->logInfo("No pending status suggestions found — returning to READY");
            $this->returnToReadyContext();
            return $this->handleInit();
        }

        $statusMap = $this->wapSalesAgentService->getStatusNameMap($this->client);

        $selectedIndex = $this->parseSelectionIndex($this->incomingMessageText);
        if ($selectedIndex !== null && isset($suggestions[$selectedIndex])) {
            $selectedStatusId = $suggestions[$selectedIndex];
            $selectedStatusName = $statusMap[$selectedStatusId] ?? null;

            if ($selectedStatusName) {
                return $this->resolveStatusSelectionAndResumeWorkflow($selectedStatusName);
            }
        }

        $status = $this->wapSalesAgentService->findOneStatusByClientAndName($this->client, $this->incomingMessageText);
        if ($status) {
            return $this->resolveStatusSelectionAndResumeWorkflow($status->name);
        }

        return $this->retryStatusMatcherFromSelection($this->incomingMessageText);
    }


    private function cancelStatusSelectionAndRestore(): bool
    {
        $this->logInfo(">>> Cancelling status selection");
        $this->wapSalesAgentService->clearPendingStatusSuggestions(
            $this->client->id, $this->customerPhoneNumber, $this->connectedPhoneNumberId
        );
        $this->wapSalesAgentService->clearPendingUpdate(
            $this->client->id, $this->customerPhoneNumber, $this->connectedPhoneNumberId
        );
        $this->wapSalesAgentService->clearWorkflow(
            $this->client->id, $this->customerPhoneNumber, $this->connectedPhoneNumberId
        );

        if ($this->tryRestoreLeadScope()) {
            $this->sendAndLogResponse('🤖 Operación cancelada.');
            $this->showLeadScopeMenu();
            return true;
        }

        $this->returnToReadyContext();
        $this->sendAndLogResponse(
            '🤖 Operación cancelada. Buscá un prospecto por ID, nombre, email o teléfono.'
        );
        return true;
    }


    private function resolveStatusSelectionAndResumeWorkflow(string $statusName): bool
    {
        $this->logInfo(">>> Status selected: {$statusName}");

        $this->wapSalesAgentService->clearPendingStatusSuggestions(
            $this->client->id, $this->customerPhoneNumber, $this->connectedPhoneNumberId
        );

        $workflow = $this->wapSalesAgentService->getWorkflow(
            $this->client->id, $this->customerPhoneNumber, $this->connectedPhoneNumberId
        );
        $currentStepIndex = $workflow['next_step_index'] ?? 0;

        $this->wapSalesAgentService->updateWorkflowStepParams(
            clientId: $this->client->id,
            customerPhone: $this->customerPhoneNumber,
            stepIndex: $currentStepIndex,
            newParams: ['pendingUpdateValue' => $statusName],
            botPhoneId: $this->connectedPhoneNumberId,
        );

        $this->wapSalesAgentService->setConversationStatus(
            clientId: $this->client->id,
            customerPhone: $this->customerPhoneNumber,
            status: WapSalesAgentConversationService::STATUS_READY,
            botPhoneId: $this->connectedPhoneNumberId,
        );

        return $this->executeWorkflowSteps();
    }


    private function retryStatusMatcherFromSelection(string $newInput): bool
    {
        $this->logInfo(">>> Retrying status matcher with: {$newInput}");

        $statusMap = $this->wapSalesAgentService->getStatusNameMap($this->client);

        if (empty($statusMap)) {
            $this->sendAndLogResponse('❌ No hay estados creados. Pedí al administrador que los configure.');
            return $this->cancelStatusSelectionAndRestore();
        }

        if (mb_strlen(trim($newInput)) <= 2) {
            $this->sendAndLogResponse(
                <<<TEXT
                    ❌ Escribí al menos 3 caracteres.
                    🤖 Seleccioná un número de la lista o escribí \"volver\" para cancelar.
                TEXT
            );
            return true;
        }

        $matches = $this->callStatusMatcherAssistant($newInput, array_values($statusMap));

        if (empty($matches)) {
            $this->sendAndLogResponse(
                <<<TEXT
                    ❌ No encontré estados similares a \"{$newInput}\".
                    🤖 Seleccioná un número de la lista anterior o escribí \"volver\" para cancelar.
                TEXT
            );
            return true;
        }

        $nameToId = array_flip($statusMap);
        $matchedStatusIds = [];
        foreach ($matches as $matchName) {
            if (isset($nameToId[$matchName])) {
                $matchedStatusIds[] = $nameToId[$matchName];
            }
        }

        if (empty($matchedStatusIds)) {
            $this->sendAndLogResponse(
                <<<TEXT
                    ❌ No encontré estados similares a \"{$newInput}\".
                    🤖 Seleccioná un número de la lista anterior o escribí \"volver\" para cancelar.
                TEXT
            );
            return true;
        }

        $this->wapSalesAgentService->setPendingStatusSuggestions(
            $this->client->id, $this->customerPhoneNumber, $matchedStatusIds, $this->connectedPhoneNumberId
        );

        $list = '';
        foreach (array_values($matchedStatusIds) as $i => $statusId) {
            $list .= ($i + 1) . '. ' . ($statusMap[$statusId] ?? '') . "\n";
        }

        $this->sendAndLogResponse(
            <<<TEXT
                🤖 No encontré el estado "{$newInput}".

                ¿Quisiste decir?

                {$list}

                👉 Seleccioná un número, escribí el nombre exacto, o escribí "volver" para cancelar.
            TEXT
        );
        return true;
    }


    private function tryRestoreLeadScope(): bool
    {
        $activeLeadId = $this->wapSalesAgentService->getActiveLeadId(
            $this->client->id,
            $this->customerPhoneNumber,
            $this->connectedPhoneNumberId
        );

        if (!$activeLeadId) {
            return false;
        }

        $this->transitionToLeadScopeStatus();

        return true;
    }


    private function returnToReadyContext(): void
    {
        $this->wapSalesAgentService->setConversationStatus(
            $this->client->id,
            $this->customerPhoneNumber,
            WapSalesAgentConversationService::STATUS_READY,
            $this->connectedPhoneNumberId
        );
    }


    private function showLeadScopeMenu(): void
    {
        $activeLeadId = $this->wapSalesAgentService->getActiveLeadId(
            $this->client->id,
            $this->customerPhoneNumber,
            $this->connectedPhoneNumberId
        );

        if (!$activeLeadId) {
            return;
        }

        $message = $this->selectedLeadInfoMessage($activeLeadId);
        $this->sendAndLogResponse($message);
    }


    // =========================================================================
    // WORKFLOW FLOW
    // Entrada:
    // - executeWorkflowSteps()
    //
    // Decisiones:
    // - ejecutar step a step
    // - pausar por selección, confirmación o cambio de contexto
    //
    // Efectos:
    // - actualizar next_step_index
    // - persistir/limpiar workflow
    // - responder al usuario
    // =========================================================================

    /**
     * Ejecuta el workflow step by step hasta completarlo o pausar por selección/confirmación.
     */
    private function executeWorkflowSteps(): bool
    {
        $workflow = $this->wapSalesAgentService->getWorkflow(
            $this->client->id, $this->customerPhoneNumber, $this->connectedPhoneNumberId
        );

        if (!$this->hasExecutableWorkflow($workflow)) {
            $this->logInfo("No workflow to execute");
            return false;
        }

        $steps = $workflow['steps'];
        $currentStepIndex = $workflow['next_step_index'] ?? 0;

        while ($currentStepIndex < count($steps)) {
            $step = $steps[$currentStepIndex];
            $this->logInfo("Executing step {$currentStepIndex}: " . json_encode($step));

            $stepOutcome = $this->executeStep($step);
            $this->logInfo("Step outcome type: " . $this->getStepOutcomeType($stepOutcome));

            if ($this->pauseWorkflowIfStepRequiresIt($stepOutcome, $currentStepIndex)) {
                return true;
            }

            $currentStepIndex++;
            $this->advanceWorkflowToStep($currentStepIndex);

            $this->sendMessageIfLastStepCompleted($stepOutcome, $currentStepIndex, count($steps));
        }

        $this->completeWorkflow();
        $this->restoreConversationContextAfterWorkflowCompletion();
        $this->logInfo("Workflow completed");

        return true;
    }


    private function hasExecutableWorkflow(?array $workflow): bool
    {
        return $workflow !== null && !empty($workflow['steps']);
    }


    /**
     * Inspecciona el outcome de un step y pausa el workflow si la acción lo requiere.
     */
    private function pauseWorkflowIfStepRequiresIt(array $stepOutcome, int $currentStepIndex): bool
    {
        $nextStepIndex = $currentStepIndex + 1;
        $stepOutcomeType = $this->getStepOutcomeType($stepOutcome);

        if ($stepOutcomeType === self::STEP_OUTCOME_ERROR) {
            $this->logInfo(">>> Step failed with ERROR - stopping workflow");
            $this->abortWorkflowAndRespond($stepOutcome['message']);
            return true;
        }

        if ($stepOutcomeType === self::STEP_OUTCOME_REQUIRES_SELECTION) {
            $this->logInfo(">>> Status: READY -> AWAITING_SELECTION");
            $this->pauseWorkflowForSelection(
                $nextStepIndex,
                $stepOutcome['message'],
                $stepOutcome['candidates'] ?? null
            );
            return true;
        }

        if ($stepOutcomeType === self::STEP_OUTCOME_REQUIRES_CONFIRMATION) {
            $this->logInfo(">>> Status: READY -> AWAITING_CONFIRMATION");
            $this->pauseWorkflowForConfirmation($nextStepIndex, $stepOutcome['message']);
            return true;
        }

        if ($stepOutcomeType === self::STEP_OUTCOME_REQUIRES_STATUS_SELECTION) {
            $this->logInfo(">>> Status: -> AWAITING_STATUS_SELECTION");
            $this->pauseWorkflowForStatusSelection($currentStepIndex, $stepOutcome['message']);
            return true;
        }

        if ($stepOutcomeType === self::STEP_OUTCOME_SWITCHED_CONTEXT) {
            $this->logInfo(">>> Step triggered context switch — pausing workflow");
            $this->wapSalesAgentService->clearWorkflow(
                $this->client->id, $this->customerPhoneNumber, $this->connectedPhoneNumberId
            );
            $this->sendAndLogResponse($stepOutcome['message']);
            return true;
        }

        return false;
    }


    private function pauseWorkflowForStatusSelection(int $currentStepIndex, string $message): void
    {
        $this->wapSalesAgentService->setWorkflowNextStepIndex(
            clientId: $this->client->id,
            nextStepIndex: $currentStepIndex,
            customerPhone: $this->customerPhoneNumber,
            botPhoneId: $this->connectedPhoneNumberId,
        );
        $this->wapSalesAgentService->setConversationStatus(
            clientId: $this->client->id,
            customerPhone: $this->customerPhoneNumber,
            status: WapSalesAgentConversationService::STATUS_AWAITING_STATUS_SELECTION,
            botPhoneId: $this->connectedPhoneNumberId,
        );
        $this->sendAndLogResponse($message);
    }


    private function abortWorkflowAndRespond(string $message): void
    {
        $this->wapSalesAgentService->clearWorkflow(
            clientId: $this->client->id,
            customerPhone: $this->customerPhoneNumber,
            botPhoneId: $this->connectedPhoneNumberId,
        );
        $this->sendAndLogResponse($message);
        if ($this->tryRestoreLeadScope()) {
            $this->showLeadScopeMenu();
        } else {
            $this->returnToReadyContext();
        }
    }


    private function pauseWorkflowForSelection(int $nextStepIndex, string $message, ?array $candidates): void
    {
        $this->wapSalesAgentService->setWorkflowNextStepIndex(
            clientId: $this->client->id,
            nextStepIndex: $nextStepIndex,
            customerPhone: $this->customerPhoneNumber,
            botPhoneId: $this->connectedPhoneNumberId,
        );
        $this->pauseForCandidatesSelection($message, $candidates);
    }


    private function pauseWorkflowForConfirmation(int $nextStepIndex, string $message): void
    {
        $this->transitionToAwaitingConfirmationStatus();
        $this->wapSalesAgentService->setWorkflowNextStepIndex(
            clientId: $this->client->id,
            nextStepIndex: $nextStepIndex,
            customerPhone: $this->customerPhoneNumber,
            botPhoneId: $this->connectedPhoneNumberId,
        );
        $this->sendAndLogResponse($message);
    }


    private function advanceWorkflowToStep(int $stepIndex): void
    {
        $this->wapSalesAgentService->setWorkflowNextStepIndex(
            nextStepIndex: $stepIndex,
            clientId: $this->client->id,
            customerPhone: $this->customerPhoneNumber,
            botPhoneId: $this->connectedPhoneNumberId,
        );
    }


    private function sendMessageIfLastStepCompleted(array $stepOutcome, int $currentIndex, int $totalSteps): void
    {
        $isLastStep = $currentIndex >= $totalSteps;
        $hasMessage = !empty($stepOutcome['message']);
        if ($hasMessage && $isLastStep) {
            $candidates = $stepOutcome['candidates'] ?? null;
            $fullMessage = $this->wapSalesAgentService->addCandidatesToMessage($stepOutcome['message'], $candidates);
            $this->sendAndLogResponse($fullMessage, $candidates);
        }
    }


    /**
     * Limpia el workflow persistido.
     */
    private function completeWorkflow(): void
    {
        $this->wapSalesAgentService->clearWorkflow(
            $this->client->id, $this->customerPhoneNumber, $this->connectedPhoneNumberId
        );
    }


    /**
     * Una vez terminado el workflow, restaura el contexto conversacional correcto.
     */
    private function restoreConversationContextAfterWorkflowCompletion(): void
    {
        $activeTaskId = $this->wapSalesAgentService->getActiveTaskId(
            clientId: $this->client->id,
            customerPhone: $this->customerPhoneNumber,
            botPhoneId: $this->connectedPhoneNumberId,
        );

        if ($activeTaskId) {
            $this->wapSalesAgentService->setConversationStatus(
                $this->client->id,
                $this->customerPhoneNumber,
                WapSalesAgentConversationService::STATUS_TASK_SCOPE,
                $this->connectedPhoneNumberId
            );
            return;
        }

        if (!$this->tryRestoreLeadScope()) {
            $this->returnToReadyContext();
        }
    }

    // =========================================================================
    // CONVERSATION TRANSITIONS
    // Métodos explícitos para centralizar cambios de estado y subestado.
    // =========================================================================

    private function transitionToLeadScopeStatus(): void
    {
        $this->wapSalesAgentService->setConversationStatus(
            clientId: $this->client->id,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
            status: WapSalesAgentConversationService::STATUS_LEAD_SCOPE,
        );
    }


    private function transitionToAwaitingSelectionStatus(): void
    {
        $this->wapSalesAgentService->setConversationStatus(
            clientId: $this->client->id,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
            status: WapSalesAgentConversationService::STATUS_AWAITING_SELECTION,
        );
    }


    private function transitionToAwaitingConfirmationStatus(): void
    {
        $this->wapSalesAgentService->setConversationStatus(
            clientId: $this->client->id,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
            status: WapSalesAgentConversationService::STATUS_AWAITING_CONFIRMATION,
        );
    }


    private function transitionToAwaitingValidatorInfoStatus(): void
    {
        $this->wapSalesAgentService->setConversationStatus(
            clientId: $this->client->id,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
            status: WapSalesAgentConversationService::STATUS_AWAITING_VALIDATOR_INFO,
        );
    }


    private function transitionToTaskScopeListingSubState(): void
    {
        $this->wapSalesAgentService->setConversationStatus(
            clientId: $this->client->id,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
            status: WapSalesAgentConversationService::STATUS_TASK_SCOPE,
        );
        $this->wapSalesAgentService->setTaskSubState(
            $this->client->id,
            $this->customerPhoneNumber,
            WapSalesAgentConversationService::TASK_SUB_LISTING,
            $this->connectedPhoneNumberId
        );
    }


    private function transitionToTaskScopeViewingSubState(): void
    {
        $this->wapSalesAgentService->setConversationStatus(
            clientId: $this->client->id,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
            status: WapSalesAgentConversationService::STATUS_TASK_SCOPE,
        );
        $this->wapSalesAgentService->setTaskSubState(
            $this->client->id,
            $this->customerPhoneNumber,
            WapSalesAgentConversationService::TASK_SUB_VIEWING,
            $this->connectedPhoneNumberId
        );
    }


    private function transitionToLeadSaleCollectingSubState(): void
    {
        $this->wapSalesAgentService->setSaleSubState(
            clientId: $this->client->id,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
            subState: WapSalesAgentConversationService::SALE_SUB_COLLECTING,
        );
    }


    private function transitionToLeadSaleConfirmingSubState(int $leadId): void
    {
        $this->wapSalesAgentService->setSaleLeadId(
            leadId: $leadId,
            clientId: $this->client->id,
            customerPhone: $this->customerPhoneNumber,
            botPhoneId: $this->connectedPhoneNumberId,
        );
        $this->wapSalesAgentService->setSaleSubState(
            clientId: $this->client->id,
            customerPhone: $this->customerPhoneNumber,
            botPhoneId: $this->connectedPhoneNumberId,
            subState: WapSalesAgentConversationService::SALE_SUB_CONFIRMING,
        );
        $this->wapSalesAgentService->setConversationStatus(
            clientId: $this->client->id,
            customerPhone: $this->customerPhoneNumber,
            botPhoneId: $this->connectedPhoneNumberId,
            status: WapSalesAgentConversationService::STATUS_AWAITING_LEAD_SALE_INFO,
        );
    }


    private function clearNotesContext(): void
    {
        $this->wapSalesAgentService->clearNotesContext(
            $this->client->id,
            $this->customerPhoneNumber,
            $this->connectedPhoneNumberId
        );
    }


    private function clearTaskContext(): void
    {
        $this->wapSalesAgentService->clearTaskContext(
            $this->client->id,
            $this->customerPhoneNumber,
            $this->connectedPhoneNumberId
        );
    }


    private function clearLeadSaleContext(): void
    {
        $this->wapSalesAgentService->clearSaleContext(
            $this->client->id,
            $this->customerPhoneNumber,
            $this->connectedPhoneNumberId
        );
        $this->returnToLeadActionMenuOrReady(sendMenu: false);
    }

    private function returnToLeadActionMenuOrReady(bool $sendMenu = true): void
    {
        if ($this->tryRestoreLeadScope()) {
            if ($sendMenu) {
                $this->showLeadScopeMenu();
            }
            return;
        }

        $this->returnToReadyContext();
    }

    /**
     * Ejecuta un step individual y retorna el resultado
     */
    private function executeStep(array $step): array
    {
        $entityAction = $this->extractWorkflowEntityAction($step);
        $params = $step['params'] ?? [];
        $actionType = $this->resolveWorkflowActionType($entityAction);

        if (!$actionType) {
            return $this->stepError('Acción no reconocida.');
        }

        $result = $this->executeWorkflowAction($actionType, $params);
        return $this->resolveWorkflowStepResult($actionType, $result);
    }


    private function extractWorkflowEntityAction(array $step): string
    {
        $entity = $step['entity'] ?? '';
        $action = $step['action'] ?? '';
        $entityAction = "{$entity}.{$action}";

        $this->logInfo("executeStep: {$entityAction}");

        return $entityAction;
    }


    private function resolveWorkflowActionType(string $entityAction): ?string
    {
        return match ($entityAction) {
            'lead.search' => 'lead_search',
            'lead.create' => 'lead_create',
            'lead.list_notes' => 'list_notes',
            'lead.view_notes' => 'list_notes',
            'lead.view_note' => 'view_note',
            'lead.create_note' => 'create_note',
            'lead.delete_note' => 'delete_note',
            'lead.preview_update' => 'preview_lead_update',
            'lead.search_by_date' => 'lead_search_by_date',
            'lead.back_to_notes_list' => 'back_to_notes_list',
            'task.view_task' => 'view_task',
            'task.update_task' => 'update_task',
            'task.create_task' => 'create_task',
            'task.list_user_tasks' => 'list_user_tasks',
            'task.list_lead_tasks' => 'list_lead_tasks',
            default => null,
        };
    }


    private function executeWorkflowAction(string $actionType, array $params): array
    {
        return $this->wapSalesAgentService->executeAction(
            client: $this->client,
            user: $this->user,
            customerPhone: $this->customerPhoneNumber,
            botPhoneId: $this->connectedPhoneNumberId,
            actionType: $actionType,
            message: '',
            params: $params,
        );
    }


    private function resolveWorkflowStepResult(string $actionType, array $result): array
    {
        if (!empty($result['isError'])) {
            return $this->stepError($result['message']);
        }

        if (!empty($result['needsStatusMatch'])) {
            return $this->handleStatusMatcherFlow($result['statusInput']);
        }

        if ($this->isSearchStepWithoutResults($actionType, $result)) {
            return $this->stepError($result['message']);
        }

        if (!empty($result['candidates'])) {
            return $this->stepNeedsSelection($result['message'], $result['candidates']);
        }

        if ($this->stepRequiresConfirmation($actionType)) {
            return $this->stepNeedsConfirmation($result['message']);
        }

        if ($this->workflowSwitchedContext()) {
            return $this->stepContextSwitch($result['message']);
        }

        return $this->stepDone($result['message'], $result['lead'] ?? null);
    }


    private function isSearchStepWithoutResults(string $actionType, array $result): bool
    {
        $isSearchAction = in_array($actionType, ['lead_search', 'lead_search_by_date']);

        return $isSearchAction && empty($result['lead']) && empty($result['candidates']);
    }


    private function stepRequiresConfirmation(string $actionType): bool
    {
        return $actionType === 'preview_lead_update' && $this->wapSalesAgentService->hasPendingUpdate(
            $this->client->id,
            $this->customerPhoneNumber,
            $this->connectedPhoneNumberId
        );
    }


    private function workflowSwitchedContext(): bool
    {
        $currentStatus = $this->wapSalesAgentService->getConversationStatus(
            clientId: $this->client->id,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
        );
        return $currentStatus === WapSalesAgentConversationService::STATUS_LEAD_NOTES_FLOW;
    }


    // =========================================================================
    // WORKFLOW STEP OUTCOMES
    // Contrato interno para que el flujo del workflow no dependa de arrays anónimos.
    // =========================================================================

    private function getStepOutcomeType(array $stepOutcome): string
    {
        return $stepOutcome['type'] ?? 'unknown';
    }

    private function stepDone(string $message, mixed $lead = null): array
    {
        return [
            'type' => self::STEP_OUTCOME_DONE,
            'message' => $message,
            'lead' => $lead,
        ];
    }

    private function stepError(string $message): array
    {
        return [
            'type' => self::STEP_OUTCOME_ERROR,
            'message' => $message,
        ];
    }

    private function stepNeedsSelection(string $message, array $candidates): array
    {
        return [
            'type' => self::STEP_OUTCOME_REQUIRES_SELECTION,
            'message' => $message,
            'candidates' => $candidates,
        ];
    }

    private function stepNeedsConfirmation(string $message): array
    {
        return [
            'type' => self::STEP_OUTCOME_REQUIRES_CONFIRMATION,
            'message' => $message,
        ];
    }

    private function stepContextSwitch(string $message): array
    {
        return [
            'type' => self::STEP_OUTCOME_SWITCHED_CONTEXT,
            'message' => $message,
        ];
    }

    private function stepNeedsStatusSelection(string $message): array
    {
        return [
            'type' => self::STEP_OUTCOME_REQUIRES_STATUS_SELECTION,
            'message' => $message,
        ];
    }

    /**
     * Cancelar la selección de un prospecto
     */
    private function isBackOrCancelMessage(string $message): bool
    {
        $msg = mb_strtolower(trim($message));
        $cancelWords = ['cancelar', 'cancelo', 'cancelado', 'salir', 'volver', 'atras', 'atrás', 'exit', 'quit'];
        
        foreach ($cancelWords as $word) {
            if ($msg === $word) {
                return true;
            }
        }
        
        return false;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // STATUS MATCHER (fuzzy search via LLM)
    // ══════════════════════════════════════════════════════════════════════════

    private function handleStatusMatcherFlow(string $statusInput): array
    {
        $statusMap = $this->wapSalesAgentService->getStatusNameMap($this->client);

        if (empty($statusMap)) {
            return $this->stepError('❌ No hay estados creados. Pedí al administrador que los configure.');
        }

        if (mb_strlen(trim($statusInput)) <= 2) {
            return $this->stepError('❌ Escribí al menos 3 caracteres para buscar un estado.');
        }

        $matches = $this->callStatusMatcherAssistant($statusInput, array_values($statusMap));

        if (empty($matches)) {
            return $this->stepError(
                "❌ No encontré estados similares a \"{$statusInput}\".\n👉 Verificá el nombre del estado."
            );
        }

        $matchedStatusIds = [];
        $nameToId = array_flip($statusMap);
        foreach ($matches as $matchName) {
            if (isset($nameToId[$matchName])) {
                $matchedStatusIds[] = $nameToId[$matchName];
            }
        }

        if (empty($matchedStatusIds)) {
            return $this->stepError(
                "❌ No encontré estados similares a \"{$statusInput}\".\n👉 Verificá el nombre del estado."
            );
        }

        $this->wapSalesAgentService->setPendingStatusSuggestions(
            $this->client->id,
            $this->customerPhoneNumber,
            $matchedStatusIds,
            $this->connectedPhoneNumberId
        );

        $list = '';
        foreach (array_values($matchedStatusIds) as $i => $statusId) {
            $list .= ($i + 1) . '. ' . ($statusMap[$statusId] ?? '') . "\n";
        }

        $msg = "🤖 No encontré el estado \"{$statusInput}\".\n\n";
        $msg .= "¿Quisiste decir?\n\n{$list}\n";
        $msg .= "👉 Seleccioná un número, escribí el nombre exacto, o escribí \"volver\" para cancelar.";

        return $this->stepNeedsStatusSelection($msg);
    }


    private function callStatusMatcherAssistant(string $statusInput, array $statusNames): array
    {
        $this->logInfo("<<<statusNames>>>> " .
            json_encode($statusNames, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        
        $systemPrompt = $this->promptHelper->getStatusMatcherPrompt();
        $systemPrompt = str_replace('{{AVAILABLE_STATUSES}}', implode(', ', $statusNames), $systemPrompt);
        $systemPrompt = str_replace('{{STATUS_INPUT}}', $statusInput, $systemPrompt);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $statusInput],
        ];

        $responseFormat = [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'StatusMatcherResponse',
                'strict' => false,
                'schema' => [
                    'type' => 'object',
                    'required' => ['matches'],
                    'properties' => [
                        'matches' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->callOpenAI($messages, $responseFormat);
        $parsed = $this->parseJsonResponse($response);

        $this->logInfo("StatusMatcher response: " . json_encode($parsed, JSON_UNESCAPED_UNICODE));

        return $parsed['matches'] ?? [];
    }


    // ══════════════════════════════════════════════════════════════════════════
    // ROUTER, VALIDATORS Y WORKFLOW BUILDER
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Llama al Router para clasificar el mensaje
     */
    private function callRouterAssistant(string $message): array
    {
        $systemPrompt = $this->promptHelper->getRouterPrompt();
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message],
        ];

        $responseFormat = [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'RouterResponse',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['route', 'domains'],
                    'properties' => [
                        'route' => [
                            'type' => 'string',
                            'enum' => ['operational', 'help', 'smalltalk', 'unknown'],
                        ],
                        'domains' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                                'enum' => ['lead', 'task'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $responseStr = $this->callOpenAI($messages, $responseFormat);
        $response = $this->parseJsonResponse($responseStr);

        return [
            'route' => $response['route'] ?? 'unknown',
            'domains' => $response['domains'] ?? [],
        ];
    }


    /**
     * Ejecuta validators secuencialmente para cada dominio.
     *
     * @param  string $message              El mensaje actual del usuario
     * @param  array  $domains              Dominios detectados por el Router (e.g. ['lead', 'task'])
     * @param  array  $conversationMessages  Mensajes de conversación acumulados (para re-validaciones)
     * @return array{validationMessages: string[]}
     */
    private function callDomainValidatorsAssistant(
        string $message,
        array $domains,
        array $conversationMessages = []
    ): array {
        $responseFormat = [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'ValidatorResponse',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['complete', 'message'],
                    'properties' => [
                        'complete' => ['type' => 'boolean'],
                        'message'  => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        $validationMessages = [];

        // Un solo contexto por lead: incluye prospecto + notas (las notas están atadas al lead)
        $activeLeadContext = $this->buildActiveLeadContext();
        $activeTaskContext = $this->buildActiveTaskContext();

        foreach ($domains as $domain) {
            $domainPrompt = match ($domain) {
                'lead' => $this->promptHelper->getLeadValidatorPrompt(),
                'task'  => $this->promptHelper->getTaskValidatorPrompt(),
                default => null,
            };

            if (!$domainPrompt) {
                continue;
            }

            $prompt = Str::replace(
                ['{{ACTIVE_LEAD_CONTEXT}}', '{{ACTIVE_TASK_CONTEXT}}'],
                [$activeLeadContext, $activeTaskContext],
                $domainPrompt
            );
            $messages = [['role' => 'system', 'content' => $prompt]];

            if (!empty($conversationMessages)) {
                // Re-validación: pasar toda la conversación acumulada
                foreach ($conversationMessages as $conversationMessage) {
                    $messages[] = $conversationMessage;
                }
            } else {
                // Primera validación: solo el mensaje del usuario
                $messages[] = ['role' => 'user', 'content' => $message];
            }

            $this->logInfo("Validating domain '{$domain}'...");
            $responseStr = $this->callOpenAI($messages, $responseFormat);
            $response = $this->parseJsonResponse($responseStr);

            $isComplete = $response['complete'] ?? false;
            $validatorMessage = $response['message'] ?? '';

            $this->logInfo(
                "Validator '{$domain}': complete=" .
                ($isComplete ? 'true' : 'false') .
                ", message='{$validatorMessage}'"
            );

            if (!$isComplete && !empty($validatorMessage)) {
                $validationMessages[] = $validatorMessage;
            }
        }

        return [
            'validationMessages' => $validationMessages,
        ];
    }


    /**
     * Llama al WorkflowBuilder para generar steps, pasando la conversación acumulada.
     */
    private function callWorkflowBuilderAssistant(array $domains, array $conversationMessages): array
    {
        $responseFormat = [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'WorkflowResponse',
                'strict' => false,
                'schema' => [
                    'type' => 'object',
                    'required' => ['steps'],
                    'properties' => [
                        'steps' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'required' => ['step_id', 'entity', 'action', 'params'],
                                'properties' => [
                                    'step_id' => ['type' => 'integer'],
                                    'entity'  => ['type' => 'string'],
                                    'action'  => ['type' => 'string'],
                                    'params'  => ['type' => 'object'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $currentClientDate = now($this->client->timezone)->format('Y-m-d');
        $activeLeadContext = $this->buildActiveLeadContext();
        $activeTaskContext = $this->buildActiveTaskContext();

        $allSteps = [];

        usort($domains, fn ($a, $b) => ($a === 'lead' ? -1 : 1));

        foreach ($domains as $domain) {
            $domainPrompt = match ($domain) {
                'lead' => $this->promptHelper->getLeadWorkflowBuilderPrompt(),
                'task'  => $this->promptHelper->getTaskWorkflowBuilderPrompt(),
                default => null,
            };

            if (!$domainPrompt) {
                continue;
            }

            $prompt = str_replace(
                ['{{ACTIVE_LEAD_CONTEXT}}', '{{ACTIVE_TASK_CONTEXT}}', '{{CURRENT_DATE}}'],
                [$activeLeadContext, $activeTaskContext, $currentClientDate],
                $domainPrompt
            );

            $messages = [['role' => 'system', 'content' => $prompt]];

            foreach ($conversationMessages as $conversationMessage) {
                $messages[] = $conversationMessage;
            }

            $responseStr = $this->callOpenAI($messages, $responseFormat);
            $response = $this->parseJsonResponse($responseStr);
            $steps = $response['steps'] ?? [];


            $this->logInfo("WFB '{$domain}' returned " . count($steps) .
                " steps: " . json_encode($steps, JSON_UNESCAPED_UNICODE)
            );

            // Re-numerar step_ids para evitar colisiones
            $offset = count($allSteps);
            foreach ($steps as &$step) {
                $step['step_id'] = $step['step_id'] + $offset;
            }

            $allSteps = array_merge($allSteps, $steps);
        }

        return ['steps' => $allSteps];
    }


    /**
     * Construye y ejecuta workflows para los dominios dados.
     * Se usa tanto desde handleInit() como desde handleAwaitingValidatorInfo().
     */
    private function buildAndExecuteWorkflowAssistant(array $domains, array $conversationMessages): bool
    {
        $workflow = $this->callWorkflowBuilderAssistant($domains, $conversationMessages);

        $this->logInfo("WorkflowBuilder workflow: " .
            json_encode($workflow, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        $this->logInfo("WorkflowBuilder steps: " . count($workflow['steps'] ?? []));

        if (empty($workflow['steps'])) {
            $message = <<<TEXT
                No pude generar acciones con la información proporcionada. Intentá de nuevo o escribí "cancelar".

                Ejemplos:
                • "Buscar a Juan Pérez"
                • "Ver tareas pendientes"
                • "Crear tarea llamar mañana"
                TEXT
            ;

            $this->sendAndLogResponse($message);
            return true;
        }

        // Guardar workflow en Redis y ejecutar
        $workflow['next_step_index'] = 0;
        $this->wapSalesAgentService->setWorkflow(
            workflow: $workflow,
            clientId: $this->client->id,
            customerPhone: $this->customerPhoneNumber,
            botPhoneId: $this->connectedPhoneNumberId,
        );

        return $this->executeWorkflowSteps();
    }


    /**
     * Construye el string de contexto del lead activo (si existe).
     * Incluye datos básicos del prospecto y sus notas (las notas están siempre atadas al lead).
     * Usado por validators y workflow builders para validar y generar acciones.
     */
    private function buildActiveLeadContext(): string
    {
        $noActiveLeadLegend = 'No hay ningún lead activo en la sesión actual.';

        $activeLeadId = $this->wapSalesAgentService->getActiveLeadId(
            clientId: $this->client->id,
            customerPhone: $this->customerPhoneNumber,
            botPhoneId: $this->connectedPhoneNumberId,
        );

        if (!$activeLeadId) {
            return $noActiveLeadLegend;
        }

        $lead = Lead::where('client_id', $this->client->id)
            ->where('id', $activeLeadId)
            ->with([
                'status',
                'mainLeadContact.leadContactEmails',
                'mainLeadContact.leadContactPhones',
                'notes' => fn ($q) => $q->orderByDesc('created_at')->limit(5),
            ])
            ->withCount('notes')
            ->first()
        ;

        if (!$lead) {
            return $noActiveLeadLegend;
        }

        $status = $lead->status->name ?? '-';
        $name = $lead->mainLeadContact->name ?? '-';
        $lastname = $lead->mainLeadContact->last_name ?? '-';
        $email = $lead->main_email ?? '-';
        $phone = $lead->main_phone ?? '-';

        $notesSection = $this->formatNotesForLeadContext($lead);

        return <<<TEXT
            Hay un prospecto activo en la sesión actual:
            ID: {$lead->id}
            Nombre: {$name}
            Apellido: {$lastname}
            Email: {$email}
            Teléfono: {$phone}
            Estado: {$status}
            {$notesSection}
            El usuario NO necesita identificar al prospecto nuevamente.
        TEXT;
    }

    /**
     * Formatea las notas del lead para el contexto (Validator/WorkflowBuilder).
     * Máximo 5 notas, ordenadas por fecha descendente, con preview corto.
     */
    private function formatNotesForLeadContext(Lead $lead): string
    {
        $notes = $lead->notes;
        $count = $lead->notes_count;

        if ($count === 0) {
            return 'Notas: 0 (el prospecto no tiene notas aún).';
        }

        $lines = ["Notas ({$count}):"];

        foreach ($notes->values() as $i => $note) {
            $num = $i + 1;
            $date = $note->created_at?->format('d/m/Y') ?? '-';
            $preview = Str::limit($note->text ?? '', 40);
            $lines[] = "  {$num}. [{$date}] {$preview}";
        }

        if ($count > 5) {
            $lines[] = "  ... y " . ($count - 5) . " más";
        }

        return implode("\n", $lines);
    }


    /**
     * Construye el string de contexto de la tarea activa (si existe).
     * Usado por el workflow builder de task para que use la tarea seleccionada.
     */
    private function buildActiveTaskContext(): string
    {
        $noActiveTaskLegend = 'No hay ninguna tarea activa en la sesión actual.';

        $activeTaskId = $this->wapSalesAgentService->getActiveTaskId(
            $this->client->id,
            $this->customerPhoneNumber,
            $this->connectedPhoneNumberId
        );

        if (!$activeTaskId) {
            return $noActiveTaskLegend;
        }

        $task = Task::where('client_id', $this->client->id)
            ->where('id', $activeTaskId)
            ->with(['user', 'lead'])
            ->first()
        ;

        if (!$task) {
            return $noActiveTaskLegend;
        }

        $title = $task->title ?? '-';
        // @todo LB -> ojo timezone del cliente
        $limitDate = $task->limit_date?->format('d/m/Y') ?? '-';
        $status = $task->status === 'completed' ? 'Completada' : 'Pendiente';

        return <<<TEXT
            Hay una tarea activa en la sesión actual:
            ID: {$task->id}
            Título: {$title}
            Estado: {$status}
            Vence: {$limitDate}
            Si el usuario dice "completar", "actualizar", "ver detalle" o similar
            sin especificar tarea, usa taskId: {$task->id}.
        TEXT;
    }


    // ══════════════════════════════════════════════════════════════════════════
    // OPENAI HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Llama a OpenAI con mensajes y formato de respuesta
     */
    private function callOpenAI(array $messages, array $responseFormat): ?string
    {
        try {
            $apiKey = config('app.openai.api_key');
            if (!$apiKey) {
                $this->logInfo('OpenAI API KEY not found');
                return null;
            }

            $payload = [
                'model' => 'gpt-4.1',
                'temperature' => 0.2,
                'messages' => $messages,
                'max_completion_tokens' => 1000,
                'response_format' => $responseFormat,
            ];

            // Log sin el prompt completo
            $logMessages = $messages;
            if (!empty($logMessages[0]['content'])) {
                $logMessages[0]['content'] = '<SYSTEM PROMPT>';
            }
            // $this->logInfo("OpenAI request: " . json_encode($logMessages));

            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey,
                ])
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            if (!$response->successful()) {
                $this->logInfo('Error en respuesta OpenAI: ' . $response->body());
                return null;
            }

            $responseData = $response->json();
            // $this->logInfo("OpenAI response: " . json_encode($responseData));

            return $responseData['choices'][0]['message']['content'] ?? null;
        } catch (Exception $e) {
            $this->logInfo('Error al llamar a OpenAI: ' . ((string) $e));
            return null;
        }
    }


    /**
     * Parsea respuesta JSON de OpenAI
     */
    private function parseJsonResponse(?string $response): array
    {
        if (!$response) {
            return [];
        }

        try {
            $raw = trim($response);

            // Remover fences ```json ... ``` si vienen
            if (str_starts_with($raw, '```')) {
                $raw = preg_replace('/^```[a-zA-Z]*\n?/', '', $raw);
                $raw = preg_replace('/\n?```$/', '', $raw);
                $raw = trim($raw);
            }

            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        } catch (Exception $e) {
            $this->logInfo('Error parsing JSON: ' . $e->getMessage());
            return [];
        }
    }


    // =========================================================================
    // FLOW HELPERS
    // Helpers cortos para evitar repetir transiciones y respuestas de flujo.
    // =========================================================================

    private function respondInvalidSelection(): bool
    {
        $this->sendAndLogResponse($this->wapSalesAgentService->getInvalidSelectionMessage());
        return false;
    }

    private function resumeWorkflowOrRespond(callable $fallbackResponse): bool
    {
        if ($this->hasPendingWorkflowSteps()) {
            return $this->executeWorkflowSteps();
        }

        $fallbackResponse();
        return true;
    }

    private function pauseForCandidatesSelection(string $message, ?array $candidates): void
    {
        $this->applySelectionContextForCandidates($candidates);

        $fullMessage = $this->wapSalesAgentService->addCandidatesToMessage($message, $candidates);
        $this->sendAndLogResponse($fullMessage, $candidates);
    }

    private function applySelectionContextForCandidates(?array $candidates): void
    {
        if ($this->wapSalesAgentService->areTaskCandidates($candidates)) {
            $this->transitionToTaskScopeListingSubState();
            return;
        }

        $this->transitionToAwaitingSelectionStatus();
    }

    private function findConversationLead(int $leadId, bool $withNotesCount = false): ?Lead
    {
        $query = Lead::where('client_id', $this->client->id)
            ->where('id', $leadId)
            ->with(['mainLeadContact.leadContactEmails', 'mainLeadContact.leadContactPhones', 'status']);

        if ($withNotesCount) {
            $query->withCount('notes');
        }

        return $query->first();
    }


    // ══════════════════════════════════════════════════════════════════════════
    // PARSING HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Parsea el índice de selección del mensaje ("1", "2", "primero", "segundo", etc.)
     */
    private function parseSelectionIndex(string $message): ?int
    {
        $msg = mb_strtolower(trim($message));

        // Números directos
        if (preg_match('/^(\d+)$/', $msg, $matches)) {
            return (int) $matches[1] - 1; // Base 0
        }

        // Palabras ordinales
        $ordinals = [
            'primero' => 0, 'primera' => 0, 'uno' => 0, '1ro' => 0, '1ero' => 0,
            'segundo' => 1, 'segunda' => 1, 'dos' => 1, '2do' => 1,
            'tercero' => 2, 'tercera' => 2, 'tres' => 2, '3ro' => 2, '3ero' => 2,
            'cuarto' => 3, 'cuarta' => 3, 'cuatro' => 3, '4to' => 3,
            'quinto' => 4, 'quinta' => 4, 'cinco' => 4, '5to' => 4,
            'sexto' => 5, 'sexta' => 5, 'seis' => 5,
            'septimo' => 6, 'septima' => 6, 'siete' => 6,
            'octavo' => 7, 'octava' => 7, 'ocho' => 7,
            'noveno' => 8, 'novena' => 8, 'nueve' => 8,
            'decimo' => 9, 'decima' => 9, 'diez' => 9,
        ];

        foreach ($ordinals as $word => $index) {
            if (str_contains($msg, $word)) {
                return $index;
            }
        }

        // "el 1", "el 2", etc.
        if (preg_match('/el\s*(\d+)/', $msg, $matches)) {
            return (int) $matches[1] - 1;
        }

        return null;
    }


    /**
     * Detecta si el mensaje es una confirmación
     */
    private function isConfirmMessage(string $message): bool
    {
        $msg = mb_strtolower(trim($message));
        $confirmWords = [
            'si',
            'sí',
            'yes',
            'ok',
            'dale',
            'confirmo',
            'acepto',
            'confirmar',
            'aceptar',
            'claro',
            'obvio',
            'seguro'
        ];

        foreach ($confirmWords as $word) {
            if ($msg === $word || str_starts_with($msg, $word . ' ') || str_starts_with($msg, $word . ',')) {
                return true;
            }
        }

        return false;
    }


    /**
     * Detecta si el mensaje es un rechazo
     */
    private function isRejectMessage(string $message): bool
    {
        $msg = mb_strtolower(trim($message));
        $rejectWords = ['no', 'cancelar', 'cancelo', 'cancelado', 'olvidalo', 'olvídalo', 'nope', 'nel', 'na', 'nah'];
        foreach ($rejectWords as $word) {
            if ($msg === $word || str_starts_with($msg, $word . ' ') || str_starts_with($msg, $word . ',')) {
                return true;
            }
        }
        return false;
    }


    private function serializeCandidatesForStorage(?array $candidates): ?array
    {
        if ($candidates === null) {
            return null;
        }
        return array_map(
            fn ($c) => $c instanceof LeadCandidateDTO || $c instanceof TaskCandidateDTO
                ? $c->toArray()
                : $c,
            $candidates
        );
    }


    // ══════════════════════════════════════════════════════════════════════════
    // RESPONSE HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Envía respuesta por WhatsApp y guarda en historial.
     * El mensaje debe venir ya compuesto (con candidatos si aplica).
     * @param  array<LeadCandidateDTO|TaskCandidateDTO>|null  $candidates  Solo para storage en historial
     */
    private function sendAndLogResponse(string $message, ?array $candidates = null): void
    {
        if (!$this->isTestMode) {
            $this->whatsAppHelper->sendTextMessageFromKapsoAPI(
                $this->customerPhoneNumber, $message
            );
        }

        // Guardar en historial (serializar DTOs a array para Redis)
        $candidatesForStorage = $this->serializeCandidatesForStorage($candidates);

        $this->wapSalesAgentService->addAssistantMessage(
            clientId: $this->client->id,
            botPhoneId: $this->connectedPhoneNumberId,
            customerPhone: $this->customerPhoneNumber,
            message: $message,
            messageType: 'assistant_text',
            context: [],
            resultData: ['candidates' => $candidatesForStorage],
        );

        $this->logInfo("Response sent: '{$message}'");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // LOGGING
    // ══════════════════════════════════════════════════════════════════════════

    public function failed(Throwable $e)
    {
        $this->logInfo((string) $e);
        Log::channel('WapSalesAgentAnswerIncomingMessageJobErrors')->error((string) $e);
    }


    protected function logInfo(string $msg): void
    {
        $this->getInfoLog()->info("[{$this->logUuid}] | {$msg}");
    }


    protected function getInfoLog()
    {
        return Log::channel('WapSalesAgentAnswerIncomingMessageJobInfo');
    }

}
