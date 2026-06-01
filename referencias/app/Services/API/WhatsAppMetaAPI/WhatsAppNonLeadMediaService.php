<?php

namespace App\Services\API\WhatsAppMetaAPI;

use Exception;
use Illuminate\Http\UploadedFile;
use App\Models\WhatsAppMetaAPIConnection;
use App\Helpers\AudioEncodingLambdaHelper;
use App\Helpers\WhatsAppMetaAPI\WhatsAppMetaAPIHelper;
use App\Helpers\WhatsAppMetaAPI\WhatsAppConversationAttachmentHelper;


class WhatsAppNonLeadMediaService
{

    /**
     * Procesa un audio grabado desde el navegador: convierte a OGG/Opus vía Lambda,
     * sube a Meta API y a S3, y retorna el array conversationMessageMedia listo para el job.
     */
    public function processAudioVoiceFile(
        UploadedFile $audioVoiceFile,
        WhatsAppMetaAPIConnection $connection,
        string $normalizedPhone,
    ): array {
        $sourceAudioContent = file_get_contents($audioVoiceFile->getRealPath());
        if ($sourceAudioContent === false) {
            throw new Exception('Could not read uploaded voice note');
        }

        $encodedAudioContent = resolve(AudioEncodingLambdaHelper::class)->encodeToOgg(
            $sourceAudioContent, $audioVoiceFile->getClientMimeType(),
        );

        $metaMediaId = resolve(WhatsAppMetaAPIHelper::class)->uploadMediaFromContent(
            mimeType: 'audio/ogg',
            uploadType: 'audio/ogg',
            filename: 'voice-note.ogg',
            fileContent: $encodedAudioContent,
            accessToken: $connection->access_token,
            phoneNumberId: $connection->phone_number_id,
        );
        if (!$metaMediaId) {
            throw new Exception('Meta did not return a media id for the encoded voice note');
        }

        $directory = "{$connection->phone_number_id}/{$normalizedPhone}";
        $filename = "{$metaMediaId}.ogg";
        $uploadResult = resolve(WhatsAppConversationAttachmentHelper::class)->uploadFileContent(
            $encodedAudioContent, $directory, $filename,
        );

        return [
            'id' => $metaMediaId,
            'isVoiceNote' => true,
            'metaId' => $metaMediaId,
            'metaMediaType' => 'audio',
            'mime_type' => 'audio/ogg',
            'filename' => $filename,
            'size' => $uploadResult['size'],
            'clientyFileInfo' => [
                'size' => $uploadResult['size'],
                'bucketName' => $uploadResult['bucketName'],
                'bucketFilePath' => $uploadResult['bucketFilePath'],
            ],
        ];
    }


    /**
     * Procesa un archivo adjunto (imagen, video o documento): sube a Meta API y a S3,
     * y retorna el array conversationMessageMedia listo para el job.
     * No aplica conversión — el archivo se sube tal cual.
     */
    public function processMediaFile(
        UploadedFile $mediaFile,
        string $mediaType,
        WhatsAppMetaAPIConnection $connection,
        string $normalizedPhone,
    ): array {
        $fileContent = file_get_contents($mediaFile->getRealPath());
        if ($fileContent === false) {
            throw new Exception('Could not read uploaded media file');
        }

        $mimeType = strtolower(trim(explode(';', $mediaFile->getClientMimeType())[0]));
        $extension = WhatsAppConversationAttachmentHelper::getExtensionFromMimeType($mimeType);
        $originalFilename = $mediaFile->getClientOriginalName() ?: "file.{$extension}";

        $metaMediaId = resolve(WhatsAppMetaAPIHelper::class)->uploadMediaFromContent(
            mimeType: $mimeType,
            uploadType: $mimeType,
            fileContent: $fileContent,
            filename: $originalFilename,
            accessToken: $connection->access_token,
            phoneNumberId: $connection->phone_number_id,
        );
        if (!$metaMediaId) {
            throw new Exception('Meta did not return a media id for the uploaded file');
        }

        $directory = "{$connection->phone_number_id}/{$normalizedPhone}";
        $storedFilename = "{$metaMediaId}.{$extension}";
        $uploadResult = resolve(WhatsAppConversationAttachmentHelper::class)->uploadFileContent(
            $fileContent, $directory, $storedFilename,
        );

        return [
            'id' => $metaMediaId,
            'isVoiceNote' => false,
            'metaId' => $metaMediaId,
            'metaMediaType' => $mediaType,
            'mime_type' => $mimeType,
            'filename' => $originalFilename,
            'size' => $uploadResult['size'],
            'clientyFileInfo' => [
                'size' => $uploadResult['size'],
                'bucketName' => $uploadResult['bucketName'],
                'bucketFilePath' => $uploadResult['bucketFilePath'],
            ],
        ];
    }
}
