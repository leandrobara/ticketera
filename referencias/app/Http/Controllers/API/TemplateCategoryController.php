<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Models\TemplateCategory;
use App\Services\API\TemplateCategoryService;
use App\Http\Resources\TemplateCategoryResource;
use App\Http\Requests\GetTemplateCategoryRequest;
use App\Http\Requests\DeleteTemplateCategoryRequest;
use App\Http\Requests\UpdateTemplateCategoryRequest;
use App\Http\Requests\CreateTemplateCategoryRequest;
use App\Http\Resources\TemplateCategoryResourceCollection;
use App\Http\Requests\CountTemplateCategoryRelatedTemplatesRequest;


class TemplateCategoryController extends BaseAPIController
{

    public function list(Request $req)
    {
        $templateCategories = resolve(TemplateCategoryService::class)->findAllByClient();
        $rs = (new TemplateCategoryResourceCollection($templateCategories))->loadOptionsFromRequest($req);
        $rs->setVisibleFields([ 'id', 'name', 'hash', 'text_color', 'background_color']);
        return $this->getSuccessResponse($rs);
    }


    public function create(CreateTemplateCategoryRequest $req)
    {
        $templateCategory = resolve(TemplateCategoryService::class)->create($req->validated());
        return $this->getSuccessResponse(
            (new TemplateCategoryResource($templateCategory))->loadOptionsFromRequest($req)
        );
    }


    public function update(TemplateCategory $templateCategory, UpdateTemplateCategoryRequest $req)
    {
        $templateCategory = resolve(TemplateCategoryService::class)->update($templateCategory, $req->validated());
        return $this->getSuccessResponse(
            (new TemplateCategoryResource($templateCategory))->loadOptionsFromRequest($req)
        );
    }


    public function delete(TemplateCategory $templateCategory, DeleteTemplateCategoryRequest $req)
    {
        $templateCategory = resolve(TemplateCategoryService::class)->delete($templateCategory);
        return $this->getSuccessResponse(
            (new TemplateCategoryResource($templateCategory))->loadOptionsFromRequest($req)
        );
    }


    public function getOne(TemplateCategory $templateCategory, GetTemplateCategoryRequest $req)
    {
        return $this->getSuccessResponse(
            (new TemplateCategoryResource($templateCategory))->loadOptionsFromRequest($req)
        );
    }


    public function relatedTemplatesCount(
        TemplateCategory $templateCategory,
        CountTemplateCategoryRelatedTemplatesRequest $req
    ) {
        $relatedTemplatesCountInfo = resolve(TemplateCategoryService::class)->getRelatedTemplatesCountInfo(
            $templateCategory
        );
        return $this->getSuccessResponse(['relatedTemplatesCountInfo' => $relatedTemplatesCountInfo]);
    }

}
