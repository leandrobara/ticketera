<?php

namespace App\Services\Api\Admin;

use App\Helpers\ImageHelper;
use App\Models\ShowImage;
use App\Repositories\ShowImageRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ImageService
{
    public function __construct(
        private readonly ImageHelper $imageHelper,
        private readonly ShowImageRepository $showImageRepository,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->showImageRepository->listPaginated($filters, $filters['per_page'] ?? 20);
    }

    public function getOne(ShowImage $showImage): ShowImage
    {
        return $this->showImageRepository->getOne($showImage);
    }

    public function create(array $data): ShowImage
    {
        $image = $data['image'];
        unset($data['image']);

        $data['type'] = $data['type'] ?? 'gallery';
        $data['sort_order'] = $data['sort_order'] ?? 1;
        $data['is_main'] = (bool) ($data['is_main'] ?? false);
        $data['path'] = $this->imageHelper->uploadImage(
            $image,
            $this->getShowImageDirectory((int) $data['show_id'])
        );

        if ($data['is_main']) {
            $this->showImageRepository->unsetMainForShow($data['show_id']);
        }

        return $this->showImageRepository->store($data);
    }

    public function update(ShowImage $showImage, array $data): ShowImage
    {
        if (array_key_exists('image', $data)) {
            $image = $data['image'];
            unset($data['image']);

            $showId = $data['show_id'] ?? $showImage->show_id;
            $data['path'] = $this->imageHelper->uploadImage($image, $this->getShowImageDirectory((int) $showId));
        }

        $showId = $data['show_id'] ?? $showImage->show_id;

        if ((bool) ($data['is_main'] ?? false)) {
            $this->showImageRepository->unsetMainForShow($showId, $showImage->id);
        }

        return $this->showImageRepository->update($showImage, $data);
    }

    public function delete(ShowImage $showImage): void
    {
        $this->showImageRepository->delete($showImage);
    }

    private function getShowImageDirectory(int $showId): string
    {
        $basePath = trim((string) config('filesystems.show_images_path', 'shows'), '/');

        return $basePath.'/'.$showId;
    }
}
