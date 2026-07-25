<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Admin\CreateImageRequest;
use App\Http\Requests\Admin\DeleteImageRequest;
use App\Http\Requests\Admin\GetImageRequest;
use App\Http\Requests\Admin\ListImageRequest;
use App\Http\Requests\Admin\UpdateImageRequest;
use App\Models\ShowImage;
use App\Services\Api\Admin\ImageService;

class ImageController extends BaseAPIController
{
    public function list(ListImageRequest $req): array
    {
        $images = resolve(ImageService::class)->list($req->validated());
        return $this->getSuccessResponse($images);
    }

    public function create(CreateImageRequest $req): array
    {
        $image = resolve(ImageService::class)->create($req->validated());
        return $this->getSuccessResponse($image);
    }

    public function show(ShowImage $image, GetImageRequest $req): array
    {
        $image = resolve(ImageService::class)->getOne($image);
        return $this->getSuccessResponse($image);
    }

    public function update(ShowImage $image, UpdateImageRequest $req): array
    {
        $image = resolve(ImageService::class)->update($image, $req->validated());
        return $this->getSuccessResponse($image);
    }

    public function delete(ShowImage $image, DeleteImageRequest $req): array
    {
        resolve(ImageService::class)->delete($image);
        return $this->getSuccessResponse($image);
    }
}
