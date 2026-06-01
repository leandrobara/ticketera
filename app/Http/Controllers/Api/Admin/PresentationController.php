<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Presentation;
use App\Http\Controllers\Api\BaseAPIController;
use App\Services\Api\Admin\PresentationService;
use App\Http\Requests\Admin\GetPresentationRequest;
use App\Http\Requests\Admin\ListPresentationRequest;
use App\Http\Requests\Admin\UpdatePresentationRequest;
use App\Http\Requests\Admin\CreatePresentationRequest;
use App\Http\Requests\Admin\DeletePresentationRequest;

class PresentationController extends BaseAPIController
{
    public function list(ListPresentationRequest $req): array
    {
        $presentationList = resolve(PresentationService::class)->list($req->validated());
        return $this->getSuccessResponse($presentationList);
    }

    public function create(CreatePresentationRequest $req): array
    {
        $presentation = resolve(PresentationService::class)->create($req->validated());
        return $this->getSuccessResponse($presentation);
    }

    public function show(Presentation $presentation, GetPresentationRequest $req): array
    {
        $presentation = resolve(PresentationService::class)->getOne($presentation);
        return $this->getSuccessResponse($presentation);
    }

    public function update(Presentation $presentation, UpdatePresentationRequest $req): array
    {
        $presentation = resolve(PresentationService::class)->update($presentation, $req->validated());
        return $this->getSuccessResponse($presentation);
    }

    public function delete(Presentation $presentation, DeletePresentationRequest $req): array
    {
        resolve(PresentationService::class)->delete($presentation);
        return $this->getSuccessResponse($presentation);
    }
}
