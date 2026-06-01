<?php

namespace App\Jobs\WhatsAppEvents;

use Throwable;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\WhatsAppMetaAPIConnection;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\API\WhatsAppTemplateService;
use App\Services\API\WhatsAppAttachmentService;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;
use App\Services\API\Dispatchers\WhatsAppEventsDispatcherService;


/**
 * queue: ENV_whatsapp_meta_api_templates_sync_queue
 */
class WhatsAppMetaAPISyncUsersTemplatesJob implements ShouldQueue
{

    const NEW_WABA_SYNC_ACTION = 'userWabaSync';
    const CREATE_TRIGGER_ACTION = 'templateCreate';
    const DELETE_TRIGGER_ACTION = 'templateDelete';

    use CustomDispatchable, InteractsWithQueue, Queueable;


    public $timeout = 240;
    public string $logUuid;


    public function __construct(
        public readonly int $triggerUserId,
        public readonly string $triggerAction,
        public readonly ?int $whatsAppTemplateId = null,
    ) {
        $this->logUuid = Str::afterLast(Str::orderedUuid(), '-');
    }


    public function handle()
    {
        $triggerUser = User::findOrFail($this->triggerUserId);

        $this->logInfo("=============================================================\n\n");
        $this->logInfo("- triggerUserId {$this->triggerUserId}");
        $this->logInfo("- triggerAction {$this->triggerAction}");
        $this->logInfo("- whatsAppTemplateId {$this->whatsAppTemplateId}");

        $currentWapMetaConn = $triggerUser->whatsAppMetaAPIConnection;
        if (!$currentWapMetaConn) {
            $this->logInfo("[FINISHING] - User has no whatsAppMetaAPIConnection.");
            return;
        }
        $this->logInfo("- whatsAppMetaAPIConnectionId {$currentWapMetaConn->id}");

        $wapMetaAPIService = resolve(WhatsAppMetaAPIService::class);
        $wapTemplateService = resolve(WhatsAppTemplateService::class);

        $otherWABAConnections = $wapMetaAPIService->findClientOtherWABAIdConnections($currentWapMetaConn);
        $otherWABAIds = $otherWABAConnections->pluck('waba_id')->unique()->values()->toArray();
        $this->logInfo("- currentWABAId: {$currentWapMetaConn->waba_id}");
        $this->logInfo('- otherWABAIds: ' . json_encode($otherWABAIds));

        
        if ($this->triggerAction == self::CREATE_TRIGGER_ACTION) {
            $createdTemplate = WhatsAppTemplate::findOrFail($this->whatsAppTemplateId);
            foreach ($otherWABAIds as $otherWABAId) {
                $this->logInfo("- otherWABAId: {$otherWABAId}");
                $this->logInfo("- createdTemplate meta_name: {$createdTemplate->meta_name}");
                $otherWABAMatchingTemplate = $wapTemplateService->findMatchingTemplateForWaba(
                    $createdTemplate, $otherWABAId
                );
                if ($otherWABAMatchingTemplate) {
                    $this->logInfo("- otherWABAMatchingTemplate ID: {$otherWABAMatchingTemplate->id} ALREADY EXISTS");
                    continue;
                }
                $newTplData = $this->getCreateDataFromTemplate($createdTemplate);
                $otherWABAConnection = $otherWABAConnections->where('waba_id', $otherWABAId)->first();
                $this->handleAttachmentMetaReUpload($newTplData, $otherWABAConnection);
                $newWapTemplate = $wapTemplateService->create($newTplData, $otherWABAConnection);
                $this->logInfo("- newWapTemplate ID: {$newWapTemplate->id} [CREATED]");
            }
        }


        if ($this->triggerAction == self::DELETE_TRIGGER_ACTION) {
            $deletedTemplate = WhatsAppTemplate::withTrashed()->findOrFail($this->whatsAppTemplateId);
            foreach ($otherWABAIds as $otherWABAId) {
                $this->logInfo("- otherWABAId: {$otherWABAId}");
                $this->logInfo("- deletedTemplate meta_name: {$deletedTemplate->meta_name}");
                $otherWABAMatchingTemplate = $wapTemplateService->findMatchingTemplateForWaba(
                    $deletedTemplate, $otherWABAId
                );
                if (!$otherWABAMatchingTemplate) {
                    $this->logInfo("- No matching template found for WABA ID: {$otherWABAId}");
                    continue;
                }
                $otherWABAConnection = $otherWABAConnections->where('waba_id', $otherWABAId)->first();
                $wapTemplateService->delete($otherWABAMatchingTemplate, $otherWABAConnection);
                $this->logInfo("- otherWABAMatchingTemplate ID: {$otherWABAMatchingTemplate->id} [DELETED]");
            }
        }

        
        if ($this->triggerAction == self::NEW_WABA_SYNC_ACTION) {
            $processedMetaNames = new Collection();

            foreach ($otherWABAIds as $otherWABAId) {
                $this->logInfo("\n----------------------------");
                $this->logInfo("- otherWABAId: " . $otherWABAId);

                $otherWABATemplates = $wapTemplateService->findMetaTemplatesByClientAndWabaId(
                    $currentWapMetaConn->client, $otherWABAId
                );
                $this->logInfo("- otherWABATemplates count: " . $otherWABATemplates->count());
                foreach ($otherWABATemplates as $otherWABATemplate) {
                    $this->logInfo('--------');
                    $this->logInfo("- otherWABATemplate ID: {$otherWABATemplate->id}");
                    $this->logInfo("- otherWABATemplate meta_name: {$otherWABATemplate->meta_name}");

                    if ($processedMetaNames->contains($otherWABATemplate->meta_name)) {
                        $this->logInfo("- meta_name ALREADY PROCESSED");
                        continue;
                    }
                    $processedMetaNames->push($otherWABATemplate->meta_name);

                    $existingTemplate = $wapTemplateService->findMatchingTemplateForWaba(
                        $otherWABATemplate, $currentWapMetaConn->waba_id
                    );
                    if ($existingTemplate) {
                        $this->logInfo("- existingTemplate ID: {$existingTemplate->id} ALREADY EXISTS");
                        continue;
                    }

                    resolve(WhatsAppEventsDispatcherService::class)->dispatchWhatsAppMetaAPICloneTemplateJob(
                        $otherWABATemplate, $currentWapMetaConn
                    );
                    $this->logInfo("- WhatsAppMetaAPICloneTemplateJob DISPATCHED");
                }
            }
        }
    }



    protected function getCreateDataFromTemplate(WhatsAppTemplate $wapTemplate): array
    {
        $data = $wapTemplate->only([
            'body',
            'title',
            'client_id',
            'meta_name',
            'is_proposal',
            'meta_category',
            'meta_header_text',
            'meta_footer_text',
            'template_category_id',
            'whatsapp_attachment_id',
            // NO incluir meta_body_variables_json ni meta_header_variables_json
        ]);
        
        // Decodificar las variables JSON y combinarlas en el formato que espera el service
        $bodyVariables = [];
        $headerVariables = [];
        if (!empty($wapTemplate->meta_header_variables_json)) {
            $headerVariables = json_decode($wapTemplate->meta_header_variables_json, true) ?: [];
        }
        if (!empty($wapTemplate->meta_body_variables_json)) {
            $bodyVariables = json_decode($wapTemplate->meta_body_variables_json, true) ?: [];
        }
        
        // Combinar todas las variables en el formato esperado
        $data['meta_variables'] = array_merge($headerVariables, $bodyVariables);
        $data['enable_meta_template'] = true;
        return $data;
    }


    protected function handleAttachmentMetaReUpload(
        array $newTplData,
        WhatsAppMetaAPIConnection $targetWapMetaConn
    ): bool {
        $wapAttachmentId = $newTplData['whatsapp_attachment_id'] ?? null;
        if (!$wapAttachmentId) {
            return false;
        }

        // Si el template tiene un attachment, debemos obtener un nuevo meta_handle_id
        $wapAttachmentService = resolve(WhatsAppAttachmentService::class);
        $attachment = $wapAttachmentService->findOrFail($newTplData['whatsapp_attachment_id']);
        $this->logInfo("- Attachment ID {$attachment->id}");
        
        $attachment = $wapAttachmentService->uploadToWhatsAppMeta($targetWapMetaConn, $attachment);
        $this->logInfo("- Attachment new meta_handle_id: {$attachment->meta_handle_id}");
        return true;
    }


    public function failed(Throwable $e)
    {
        $this->logInfo((string) $e);
    }


    protected function logInfo(string $msg): void
    {
        Log::channel('WhatsAppMetaAPISyncUsersTemplatesJobInfo')->info(
            "[{$this->logUuid}-{$this->triggerAction}] | {$msg}"
        );
    }

}
