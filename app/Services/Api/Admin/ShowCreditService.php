<?php

namespace App\Services\Api\Admin;

use App\Helpers\ImageHelper;
use App\Models\ShowCredit;
use App\Repositories\ShowCreditRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ShowCreditService
{
    public function __construct(
        private readonly ImageHelper $imageHelper,
        private readonly ShowCreditRepository $showCreditRepository,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->showCreditRepository->listPaginated($filters, $filters['per_page'] ?? 20);
    }

    public function getOne(ShowCredit $showCredit): ShowCredit
    {
        return $this->showCreditRepository->getOne($showCredit);
    }

    public function create(array $data): ShowCredit
    {
        $data = $this->storePhotoIfPresent($data, (int) $data['show_id']);
        $data['sort_order'] = $data['sort_order'] ?? 1;
        $this->validateDuplicateCredit($data);

        return $this->showCreditRepository->store($data);
    }

    public function update(ShowCredit $showCredit, array $data): ShowCredit
    {
        $showId = (int) ($data['show_id'] ?? $showCredit->show_id);
        $data = $this->storePhotoIfPresent($data, $showId);

        $mergedData = [
            ...$showCredit->only([
                'show_id',
                'person_id',
                'role_label',
                'section',
                'character_name',
                'display_name_override',
            ]),
            ...$data,
        ];

        $this->validateDuplicateCredit($mergedData, $showCredit->id);

        return $this->showCreditRepository->update($showCredit, $data);
    }

    public function delete(ShowCredit $showCredit): void
    {
        $this->showCreditRepository->delete($showCredit);
    }

    private function validateDuplicateCredit(array $data, ?int $ignoreShowCreditId = null): void
    {
        if (!$this->showCreditRepository->findDuplicate($data, $ignoreShowCreditId)) {
            return;
        }

        $field = filled($data['person_id'] ?? null) ? 'person_id' : 'display_name_override';

        throw ValidationException::withMessages([
            $field => ['show_credit_already_exists_for_name_role_section_and_character'],
        ]);
    }

    private function storePhotoIfPresent(array $data, int $showId): array
    {
        if (!array_key_exists('photo', $data)) {
            return $data;
        }

        $photo = $data['photo'];
        unset($data['photo']);

        if (!$photo) {
            return $data;
        }

        $data['photo_path_override'] = $this->imageHelper->uploadImage(
            $photo,
            $this->getShowImageDirectory($showId)
        );

        return $data;
    }

    private function getShowImageDirectory(int $showId): string
    {
        $basePath = trim((string) config('filesystems.show_images_path', 'shows'), '/');

        return $basePath.'/'.$showId;
    }
}
