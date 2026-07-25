<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImageHelper
{
    public function uploadImage(UploadedFile $image, string $directory): string
    {
        $extension = strtolower($image->getClientOriginalExtension() ?: 'jpg');
        $fileName = Str::uuid()->toString().'.'.$extension;
        $path = trim($directory, '/').'/'.$fileName;

        $uploaded = Storage::disk('s3')->put($path, file_get_contents($image->getRealPath()));

        if (!$uploaded) {
            throw new RuntimeException('s3_image_upload_failed');
        }

        return $path;
    }
}
