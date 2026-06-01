<?php

namespace App\Http\Controllers\API\Views\ClientyConfigurations;

use App\Models\News;
use Illuminate\Http\Request;
use App\Services\API\NewsService;
use App\Http\Resources\NewsResource;
use App\Http\Requests\Views\ListNewsRequest;
use App\Http\Resources\NewsResourceCollection;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Views\ListClientNewsRequest;
use App\Http\Resources\Views\NewsModal\ClientyConfigNewsModalResource;
use App\Http\Requests\Views\ClientyConfigurations\News\ModalNewsWithNotificationsRequest;
use App\Http\Requests\Views\ClientyConfigurations\News\ListClientyConfigurationNewsRequest;


class NewsController extends BaseAPIController
{

    public function list(ListClientyConfigurationNewsRequest $req)
    {
        $news = resolve(NewsService::class)->listClientyConfigurationList($req->validated());
        return $this->getSuccessResponse(new NewsResourceCollection($news));
    }


    public function modal(News $news, ModalNewsWithNotificationsRequest $req)
    {
        return $this->getSuccessResponse(new ClientyConfigNewsModalResource($news));
    }

}