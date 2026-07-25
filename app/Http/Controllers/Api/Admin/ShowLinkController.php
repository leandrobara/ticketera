<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Admin\CreateShowLinkRequest;
use App\Http\Requests\Admin\DeleteShowLinkRequest;
use App\Http\Requests\Admin\GetShowLinkRequest;
use App\Http\Requests\Admin\ListShowLinkRequest;
use App\Http\Requests\Admin\UpdateShowLinkRequest;
use App\Models\ShowLink;
use App\Services\Api\Admin\ShowLinkService;

class ShowLinkController extends BaseAPIController
{
    public function list(ListShowLinkRequest $request): array
    {
        $showLinks = resolve(ShowLinkService::class)->list($request->validated());

        return $this->getSuccessResponse($showLinks);
    }

    public function create(CreateShowLinkRequest $request): array
    {
        $showLink = resolve(ShowLinkService::class)->create($request->validated());

        return $this->getSuccessResponse($showLink);
    }

    public function show(ShowLink $showLink, GetShowLinkRequest $request): array
    {
        $showLink = resolve(ShowLinkService::class)->getOne($showLink);

        return $this->getSuccessResponse($showLink);
    }

    public function update(ShowLink $showLink, UpdateShowLinkRequest $request): array
    {
        $showLink = resolve(ShowLinkService::class)->update($showLink, $request->validated());

        return $this->getSuccessResponse($showLink);
    }

    public function delete(ShowLink $showLink, DeleteShowLinkRequest $request): array
    {
        resolve(ShowLinkService::class)->delete($showLink);

        return $this->getSuccessResponse($showLink);
    }
}
