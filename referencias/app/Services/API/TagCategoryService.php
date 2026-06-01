<?php

namespace App\Services\API;

use App\Models\TagCategory;
use App\Repositories\TagCategoryRepository;
use App\Services\Traits\GetClientFromRequest;

class TagCategoryService
{

    use GetClientFromRequest;

    private $tagCategoryCategoryRepository;


    public function __construct(TagCategoryRepository $tagCategoryRepository)
    {
        $this->tagCategoryRepository = $tagCategoryRepository;
    }


    public function findAll()
    {
        return $this->tagCategoryRepository->findAllByClient($this->getClient());
    }


    public function create($data)
    {
        $data['client_id'] = $this->getClient()->id;

        return $this->tagCategoryRepository->create($data);
    }


    public function update(TagCategory $tagCategory, array $data)
    {
        return $this->tagCategoryRepository->update($tagCategory, $data);
    }


    public function delete(TagCategory $tagCategory)
    {
        return $this->tagCategoryRepository->delete($tagCategory);
    }


    public function getTagsCount(TagCategory $tagCategory)
    {
        return $tagCategory->tagsCount;
    }

}
