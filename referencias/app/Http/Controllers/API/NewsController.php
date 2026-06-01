<?php

namespace App\Http\Controllers\API;

use App\Models\News;
use App\Services\API\NewsService;
use App\Http\Resources\NewsResource;
use App\Http\Requests\UpdateNewsRequest;
use App\Http\Requests\CreateNewsRequest;
use App\Http\Requests\DeleteNewsRequest;


class NewsController extends BaseAPIController
{

    public function create(CreateNewsRequest $request)
    {
        $news = resolve(NewsService::class)->create($request->validated());
        return $this->getSuccessResponse((new NewsResource($news))->loadOptionsFromRequest($request));
    }


    public function update(News $news, UpdateNewsRequest $request)
    {
        $news = resolve(NewsService::class)->update($news, $request->validated());
        return $this->getSuccessResponse((new NewsResource($news))->loadOptionsFromRequest($request));
    }


    public function delete(News $news, DeleteNewsRequest $request)
    {
        $news = resolve(NewsService::class)->delete($news);
        return $this->getSuccessResponse((new NewsResource($news))->loadOptionsFromRequest($request));
    }

}
