<?php

namespace App\Helpers\WhatsAppMetaAPI;

use Throwable;
use Illuminate\Support\Facades\Storage;


class WhatsAppConversationAttachmentHelper
{

    private string $bucketName;


    public function __construct(private readonly string $filesystemDiskName = 'whatsapp_conversations_files')
    {
        $this->bucketName = (string) config("filesystems.disks.{$filesystemDiskName}.bucket");
    }


    /**
     * @throws Throwable Si el driver de storage falla al subir el archivo.
     */
    public function uploadFileContent(string $fileContent, string $directory, string $filename): array
    {
        $bucketFilePath = "{$directory}/{$filename}";
        // put() de Laravel atrapa excepciones y retorna false silenciosamente.
        // Usamos writeStream del adapter para que la excepción real se propague.
        Storage::disk($this->filesystemDiskName)->getDriver()->write($bucketFilePath, $fileContent);
        return [
            'size' => strlen($fileContent),
            'bucketName' => $this->bucketName,
            'bucketFilePath' => $bucketFilePath,
        ];
    }


    /**
     * Genera una URL temporal (presigned) de S3 para un archivo del bucket.
     */
    public function getTemporaryUrl(string $bucketFilePath, string $mimeType, int $minutes = 10): string
    {
        return Storage::disk($this->filesystemDiskName)->temporaryUrl(
            $bucketFilePath, now()->addMinutes($minutes), ['ResponseContentType' => $mimeType]
        );
    }


    /**
     * Resuelve la extensión a partir de un mime_type.
     * Ej: "audio/ogg; codecs=opus" -> "ogg", "image/jpeg" -> "jpg"
     */
    public static function getExtensionFromMimeType(string $mimeType): string
    {
        // Tomar solo la parte antes de ";" (ej: "audio/ogg; codecs=opus" -> "audio/ogg")
        $baseMimeType = trim(explode(';', $mimeType)[0]);

        $map = [
            'text/csv' => 'csv',
            'audio/mp4' => 'm4a',
            'audio/amr' => 'amr',
            'audio/aac' => 'aac',
            'image/png' => 'png',
            'audio/ogg' => 'ogg',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'audio/mpeg' => 'mp3',
            'image/jpeg' => 'jpg',
            'video/3gpp' => '3gp',
            'text/plain' => 'txt',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        ];

        if (isset($map[$baseMimeType])) {
            return $map[$baseMimeType];
        }

        // Fallback: tomar la parte después del "/" (ej: "image/png" -> "png")
        $parts = explode('/', $baseMimeType);
        return $parts[1] ?? 'bin';
    }

}
