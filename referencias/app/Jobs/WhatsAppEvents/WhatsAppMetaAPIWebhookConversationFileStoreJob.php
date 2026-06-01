<?php

namespace App\Jobs\WhatsAppEvents;

use Exception;
use Throwable;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Helpers\WhatsAppMetaAPI\WhatsAppMetaAPIHelper;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;
use App\Models\MongoDB\WhatsAppMetaAPI\WhatsAppConversationMessage;
use App\Helpers\WhatsAppMetaAPI\WhatsAppConversationAttachmentHelper;


/**
 * queue: ENV_whatsapp_meta_api_webhook_queue
 * Descarga el archivo media de Meta y lo sube a S3.
 */
class WhatsAppMetaAPIWebhookConversationFileStoreJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable;

    protected $logUuid = null;


    public function __construct(
        public readonly string $whatsAppConversationMessageId,
    ) {
    }


    public function handle()
    {
        $this->logUuid = Str::orderedUuid();
        $this->logInfo(
            "Starting WhatsAppMetaAPIWebhookConversationFileStoreJob for ID: {$this->whatsAppConversationMessageId}"
        );

        $wapConversationMsg = WhatsAppConversationMessage::find($this->whatsAppConversationMessageId);
        if (!$wapConversationMsg) {
            $this->logInfo('WhatsAppConversationMessage not found. RETURNING.');
            return true;
        }

        if (!$wapConversationMsg->hasDownloadableMedia()) {
            $this->logInfo('Message has no downloadable media. RETURNING.');
            return true;
        }

        $mediaId = $wapConversationMsg->media['id'];
        $mimeType = $wapConversationMsg->media['mime_type'] ?? 'application/octet-stream';
        $this->logInfo("mediaId: {$mediaId} | mimeType: {$mimeType}");

        // Buscar la conexión activa para obtener el access_token
        $whatsAppMetaAPIService = resolve(WhatsAppMetaAPIService::class);
        $connection = $whatsAppMetaAPIService->findActiveByPhoneNumberId(
            $wapConversationMsg->metaConnectedPhoneNumberId
        );
        if (!$connection) {
            $phoneNumberId = $wapConversationMsg->metaConnectedPhoneNumberId;
            $this->logInfo("No WhatsAppMetaAPIConnection found for phoneNumberId: {$phoneNumberId}. RETURNING.");
            return true;
        }
        $this->logInfo("WhatsAppMetaAPIConnection ID: {$connection->id}");

        // Obtener URL de descarga desde Meta
        $whatsAppHelper = resolve(WhatsAppMetaAPIHelper::class);
        $mediaInfo = $whatsAppHelper->getMediaInfo($mediaId, $connection->access_token);
        $mediaUrl = $mediaInfo['url'] ?? null;
        if (!$mediaUrl) {
            $this->logInfo('No media URL returned from Meta. RETURNING.');
            return true;
        }
        $this->logInfo("Media URL obtained.");

        // Descargar el archivo
        $fileContent = $whatsAppHelper->downloadMediaFile($mediaUrl, $connection->access_token);
        $this->logInfo("File downloaded. Size: " . strlen($fileContent) . " bytes");

        // Determinar extensión y ruta
        $extension = WhatsAppConversationAttachmentHelper::getExtensionFromMimeType($mimeType);
        $directory = "{$wapConversationMsg->metaConnectedPhoneNumberId}/{$wapConversationMsg->customerPhoneNumber}";
        $filename = "{$mediaId}.{$extension}";

        // Subir a S3
        $attachmentHelper = new WhatsAppConversationAttachmentHelper();
        $uploadResult = $attachmentHelper->uploadFileContent($fileContent, $directory, $filename);
        $this->logInfo("File uploaded to S3: {$uploadResult['bucketFilePath']}");

        // Actualizar el modelo con la info del archivo
        $wapConversationMsg->media = array_merge($wapConversationMsg->media, [
            'clientyFileInfo' => [
                'size' => $uploadResult['size'],
                'bucketName' => $uploadResult['bucketName'],
                'bucketFilePath' => $uploadResult['bucketFilePath'],
            ]
        ]);
        $wapConversationMsg->save();
        $this->logInfo("Model updated with clientyFileInfo. Finished execution.");

        return true;
    }


    public function failed(Throwable $e)
    {
        $this->logInfo((string) $e);
        Log::channel('WhatsAppMetaAPIWebhookConversationFileStoreJobErrors')->error((string) $e);
    }


    protected function logInfo(string $msg): void
    {
        $this->getInfoLog()->info("[{$this->logUuid}] | {$msg}");
    }

    protected function getInfoLog()
    {
        return Log::channel('WhatsAppMetaAPIWebhookConversationFileStoreJobInfo');
    }

}
