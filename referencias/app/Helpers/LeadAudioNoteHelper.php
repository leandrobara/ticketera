<?php

namespace App\Helpers;

use App\Models\Note;
use App\Models\Client;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;
use Illuminate\Contracts\Filesystem\FileNotFoundException;


class LeadAudioNoteHelper
{

    private $bucketName;
    private $filesystemDiskName;


    public function __construct(string $filesystemDiskName)
    {
        $this->filesystemDiskName = $filesystemDiskName;
        $this->bucketName = config("filesystems.disks.{$filesystemDiskName}.bucket");
    }


    public function uploadFile(Client $client, File $file): array
    {
        $newFilename = $this->getFileBucketFilename($file);
        $directory = $this->getDirectoryPathByClient($client);
        $bucketFilePath = Storage::disk($this->filesystemDiskName)->putFileAs($directory, $file, $newFilename);
        
        $audioNoteBucketData = [
            'bucketName' => $this->bucketName,
            'bucketFilepath' => $bucketFilePath,
            'hash' => $this->getHashFromFile($file),
        ];
        return $audioNoteBucketData;
    }


    public function getAudioNoteFileRawData(Note $note): ?string
    {
        $fileData = null;
        try {
            $fileData = Storage::disk($this->filesystemDiskName)->get($note->audionote_bucket_filepath);
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


    public function getFilesystemDiskName(): string
    {
        return $this->filesystemDiskName;
    }

}
