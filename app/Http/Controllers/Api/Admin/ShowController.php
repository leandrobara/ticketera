<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Show;
use App\Http\Controllers\Controller;
use App\Services\Api\Admin\ShowService;
use App\Http\Requests\Admin\GetShowRequest;
use App\Http\Requests\Admin\ListShowRequest;
use App\Http\Requests\Admin\CreateShowRequest;
use App\Http\Requests\Admin\DeleteShowRequest;
use App\Http\Requests\Admin\UpdateShowRequest;
use App\Http\Controllers\Api\BaseAPIController;


class ShowController extends BaseAPIController
{

    public function list(ListShowRequest $req): array
    {
        $showList = resolve(ShowService::class)->list($req->validated());
        return $this->getSuccessResponse($showList);
    }


    public function create(CreateShowRequest $req): array
    {
        $show = resolve(ShowService::class)->create($req->validated());
        return $this->getSuccessResponse($show);
    }


    public function show(Show $show, GetShowRequest $req): array
    {
        $show = resolve(ShowService::class)->getOne($show);
        return $this->getSuccessResponse($show);
    }


    public function update(Show $show, UpdateShowRequest $req): array
    {
        $show = resolve(ShowService::class)->update($show, $req->validated());
        return $this->getSuccessResponse($show);
    }


    public function delete(Show $show, DeleteShowRequest $req): array
    {
        resolve(ShowService::class)->delete($show);
        return $this->getSuccessResponse($show);
    }
}
