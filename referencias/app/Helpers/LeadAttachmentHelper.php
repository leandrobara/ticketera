<?php

namespace App\Helpers;

use App\Models\Client;
use App\Models\LeadAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;
use Illuminate\Contracts\Filesystem\FileNotFoundException;


class LeadAttachmentHelper
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
        // $bucketFilePath = Storage::disk($this->filesystemName)->putFile($newFilepath, $file);
        $bucketFilePath = Storage::disk($this->filesystemName)->putFileAs($directory, $file, $newFilename);
        
        $leadAttachmentData = [
            'bucketName' => $this->bucketName,
            'bucketFilepath' => $bucketFilePath,
            'hash' => $this->getHashFromFile($file),
        ];
        return $leadAttachmentData;
    }


    public function getLeadAttachmentFileRawData(LeadAttachment $leadAttachment): ?string
    {
        $fileData = null;
        try {
            $fileData = Storage::disk($this->filesystemName)->get($leadAttachment->bucket_filepath);
        } catch (FileNotFoundException $e) {
            return null;
        }
        return $fileData;
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
