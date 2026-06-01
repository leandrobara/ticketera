<?php

namespace App\DTO\Attachments;

use Illuminate\Http\UploadedFile;


class SaveAttachmentDTO
{

    public $size;
    public $name;
    public $pathname;
    public $mimeType;
    public $extension;
    public $originalFile;

    public static function build(UploadedFile $file)
    {
        $fileName = $file->getClientOriginalName();
        $fileName = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $fileName);
        $fileName = mb_ereg_replace("([\.]{2,})", '', $fileName);

        $dto = new SaveAttachmentDTO();
        $dto->size = $file->getSize();
        $dto->name = $fileName;
        $dto->pathname = $file->getPathname();
        $dto->extension = $file->extension();
        $dto->mimeType = $file->getMimeType();
        $dto->originalFile = $file;

        return $dto;
    }

}
