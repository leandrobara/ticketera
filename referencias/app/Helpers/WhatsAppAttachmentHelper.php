<?php

namespace App\Helpers;

use App\Models\Client;
use App\Models\WhatsAppAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;
use Illuminate\Contracts\Filesystem\FileNotFoundException;


class WhatsAppAttachmentHelper
{

    private $bucketName;
    private $filesystemName;


    public function __construct(string $filesystemName)
    {
        $this->filesystemName = $filesystemName;
        $this->bucketName = config("filesystems.disks.{$filesystemName}.bucket");
    }


    public function uploadFile(Client $client, File $file): array
    {
        $newFilename = $this->getFileBucketFilename($file);
        $directory = $this->getDirectoryPathByClient($client);
        $bucketFilePath = Storage::disk($this->filesystemName)->putFileAs($directory, $file, $newFilename);
        $wapAttachmentData = [
            'bucketName' => $this->bucketName,
            'bucketFilepath' => $bucketFilePath,
            'hash' => $this->getHashFromFile($file),
        ];
        return $wapAttachmentData;
    }


    public function getWhatsAppAttachmentFileRawData(WhatsAppAttachment $wapAttachment): ?string
    {
        $fileData = null;
        try {
            $fileData = Storage::disk($this->filesystemName)->get($wapAttachment->bucket_filepath);
        } catch (FileNotFoundException $e) {
            return null;
        }
        return $fileData;
    }


    public function getTemporaryUrl(WhatsAppAttachment $wapAttachment, int $minutes = 5): string
    {
        $options = ['ResponseContentType' => $wapAttachment->mime_type];
        return Storage::disk($this->filesystemName)->temporaryUrl(
            $wapAttachment->bucket_filepath, now()->addMinutes($minutes), $options
        );
    }


    public function getHashFromFile(File $file): string
    {
        return md5_file($file->path());
    }


    private function getFileBucketFilename(File $file): string
    {
        $fileExtension = $file->extension();
        $fileHash = $this->getHashFromFile($file);
        $newFileName = "{$fileHash}.{$fileExtension}";
        return $newFileName;
    }


    private function getDirectoryPathByClient(Client $client): string
    {
        return $client->subdomain;
    }

}
