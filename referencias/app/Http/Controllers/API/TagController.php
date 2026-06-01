<?php

namespace App\Http\Controllers\API;

use App\Models\Tag;
use App\Services\API\TagService;
use App\Http\Resources\TagResource;
use App\Http\Requests\GetTagRequest;
use App\Http\Requests\ListTagRequest;
use App\Http\Requests\CreateTagRequest;
use App\Http\Requests\DeleteTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Http\Requests\CountTagLeadsRequest;
use App\Http\Resources\TagResourceCollection;


class TagController extends BaseAPIController
{

    public function list(ListTagRequest $request)
    {
        $tags = resolve(TagService::class)->findAll();
        $rs = (new TagResourceCollection($tags))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($rs);
    }


    public function getOne(Tag $tag, GetTagRequest $request)
    {
        return $this->getSuccessResponse((new TagResource($tag))->loadOptionsFromRequest($request));
    }


    public function leadsCount(Tag $tag, CountTagLeadsRequest $request)
    {
        $leadsCount = resolve(TagService::class)->getLeadsCount($tag);
        return $this->getSuccessResponse($leadsCount);
    }


    public function create(CreateTagRequest $request)
    {
        $tag = resolve(TagService::class)->create($request->validatedAttributes());
        return $this->getSuccessResponse((new TagResource($tag))->loadOptionsFromRequest($request));
    }


    public function update(Tag $tag, UpdateTagRequest $request)
    {
        $tag = resolve(TagService::class)->update($tag, $request->validatedAttributes());
        return $this->getSuccessResponse((new TagResource($tag))->loadOptionsFromRequest($request));
    }


    public function delete(Tag $tag, DeleteTagRequest $request)
    {
        $tag = resolve(TagService::class)->delete($tag);
        return $this->getSuccessResponse((new TagResource($tag))->loadOptionsFromRequest($request));
    }

}
