<?php

namespace App\Http\Controllers\API\Actions\ClientyConfigurations;

use App\Models\News;
use App\Models\NewsNotification;
use App\Services\API\NewsService;
use App\Http\Resources\NewsResource;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Actions\CreateNewsWithNotificationsRequest;
use App\Http\Requests\Actions\UpdateNewsWithNotificationsRequest;
use App\Http\Requests\Actions\DeleteNewsWithNotificationsRequest;


class NewsController extends BaseAPIController
{

    public function createWithNotifications(CreateNewsWithNotificationsRequest $req)
    {
        $news = resolve(NewsService::class)->createWithNotifications(
            $req->client, $req->validatedNewsData(), $req->validatedNewsNotificationsData()
        );
        return $this->getSuccessResponse((new NewsResource($news))->loadOptionsFromRequest($req));
    }


    public function updateWithNotifications(News $news, UpdateNewsWithNotificationsRequest $req)
    {
        $news = resolve(NewsService::class)->updateWithNotifications(
            $req->client, $news, $req->validatedNewsData(), $req->validatedNewsNotificationsData()
        );
        return $this->getSuccessResponse((new NewsResource($news))->loadOptionsFromRequest($req));
    }


    public function deleteWithNotifications(News $news, DeleteNewsWithNotificationsRequest $req)
    {
        $news = resolve(NewsService::class)->deleteWithNotifications($news);
        return $this->getSuccessResponse((new NewsResource($news))->loadOptionsFromRequest($req));
    }

}
