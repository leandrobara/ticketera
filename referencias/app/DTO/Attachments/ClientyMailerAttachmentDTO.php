<?php

namespace App\DTO\Attachments;

use Illuminate\Http\UploadedFile;


class ClientyMailerAttachmentDTO
{

    public $id;
    public $type;
    public $size;
    public $hash;
    public $filename;
    public $createdAt;
    public $updatedAt;
    public $bucketName;


    public static function buildFromResponseArray(array $response)
    {
        $dto = new ClientyMailerAttachmentDTO();
        $dto->id = $response['id'];
        $dto->type = $response['type'];
        $dto->size = $response['size'];
        $dto->hash = $response['hash'];
        $dto->filename = $response['filename'];
        $dto->createdAt = $response['created_at'];
        $dto->updatedAt = $response['updated_at'];
        $dto->bucketName = $response['bucket_name'];

        return $dto;
    }

}
