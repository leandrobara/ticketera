<?php

namespace App\Jobs\WhatsAppEvents;

use Throwable;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\WhatsAppMetaAPIConnection;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\API\WhatsAppTemplateService;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;
use App\Services\API\WhatsAppAttachmentService;


/**
 * queue: ENV_whatsapp_meta_api_clone_template_queue
 */
class WhatsAppMetaAPICloneTemplateJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable;

    public string $logUuid;


    public function __construct(
        public readonly int $sourceWhatsAppTemplateId,
        public readonly int $targetWhatsAppMetaAPIConnectionId,
    ) {
        $this->logUuid = Str::afterLast(Str::orderedUuid(), '-');
    }


    public function handle()
    {
        $wapTemplateService = resolve(WhatsAppTemplateService::class);
        $sourceWapTemplate = WhatsAppTemplate::findOrFail($this->sourceWhatsAppTemplateId);
        $targetWapMetaConn = WhatsAppMetaAPIConnection::findOrFail($this->targetWhatsAppMetaAPIConnectionId);

        $this->logInfo("================================\n\n");
        $this->logInfo("- sourceWhatsAppTemplateId {$this->sourceWhatsAppTemplateId}");
        $this->logInfo("- targetWhatsAppMetaAPIConnectionId {$this->targetWhatsAppMetaAPIConnectionId}");

        $newTplData = $this->getCreateDataFromTemplate($sourceWapTemplate);
        $this->handleAttachmentMetaReUpload($newTplData, $targetWapMetaConn);

        $newWapTemplate = $wapTemplateService->create($newTplData, $targetWapMetaConn);

        $this->logInfo("- newWapTemplate ID {$newWapTemplate->id} created");
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


    protected function logInfo(string $msg): void
    {
        Log::channel('WhatsAppMetaAPICloneUserTemplateJobInfo')->info("[{$this->logUuid}] | {$msg}");
    }


    public function failed(Throwable $e)
    {
        $this->logInfo((string) $e);
    }

}
