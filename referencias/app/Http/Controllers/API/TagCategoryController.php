<?php

namespace App\Http\Controllers\API;

use App\Models\TagCategory;
use Illuminate\Http\Request;
use App\Services\API\TagCategoryService;
use App\Http\Resources\TagCategoryResource;
use App\Http\Requests\GetTagCategoryRequest;
use App\Http\Requests\CreateTagCategoryRequest;
use App\Http\Requests\DeleteTagCategoryRequest;
use App\Http\Requests\UpdateTagCategoryRequest;
use App\Http\Requests\CountTagCategoryTagsRequest;
use App\Http\Resources\TagCategoryResourceCollection;


class TagCategoryController extends BaseAPIController
{

    public function list(Request $request)
    {
        $tagCategs = resolve(TagCategoryService::class)->findAll();
        $rs = (new TagCategoryResourceCollection($tagCategs))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($rs);
    }


    public function getOne(TagCategory $tagCategory, GetTagCategoryRequest $request)
    {
        return $this->getSuccessResponse((new TagCategoryResource($tagCategory))->loadOptionsFromRequest($request));
    }


    public function create(CreateTagCategoryRequest $request)
    {
        $tagCategory = resolve(TagCategoryService::class)->create($request->validatedAttributes());
        return $this->getSuccessResponse((new TagCategoryResource($tagCategory))->loadOptionsFromRequest($request));
    }


    public function update(TagCategory $tagCategory, UpdateTagCategoryRequest $request)
    {
        $tagCategory = resolve(TagCategoryService::class)->update($tagCategory, $request->validatedAttributes());
        return $this->getSuccessResponse((new TagCategoryResource($tagCategory))->loadOptionsFromRequest($request));
    }


    public function delete(TagCategory $tagCategory, DeleteTagCategoryRequest $request)
    {
        $tagCategory = resolve(TagCategoryService::class)->delete($tagCategory);
        return $this->getSuccessResponse((new TagCategoryResource($tagCategory))->loadOptionsFromRequest($request));
    }


    public function tagsCount(TagCategory $tagCategory, CountTagCategoryTagsRequest $request)
    {
        $tagsCount = resolve(TagCategoryService::class)->getTagsCount($tagCategory);
        return $this->getSuccessResponse($tagsCount);
    }
}
