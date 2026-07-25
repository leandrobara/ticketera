<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Admin\CreateShowCreditRequest;
use App\Http\Requests\Admin\DeleteShowCreditRequest;
use App\Http\Requests\Admin\GetShowCreditRequest;
use App\Http\Requests\Admin\ListShowCreditRequest;
use App\Http\Requests\Admin\UpdateShowCreditRequest;
use App\Models\ShowCredit;
use App\Services\Api\Admin\ShowCreditService;

class ShowCreditController extends BaseAPIController
{
    public function list(ListShowCreditRequest $req): array
    {
        $showCredits = resolve(ShowCreditService::class)->list($req->validated());
        return $this->getSuccessResponse($showCredits);
    }

    public function create(CreateShowCreditRequest $req): array
    {
        $showCredit = resolve(ShowCreditService::class)->create($req->validated());
        return $this->getSuccessResponse($showCredit);
    }

    public function show(ShowCredit $showCredit, GetShowCreditRequest $req): array
    {
        $showCredit = resolve(ShowCreditService::class)->getOne($showCredit);
        return $this->getSuccessResponse($showCredit);
    }

    public function update(ShowCredit $showCredit, UpdateShowCreditRequest $req): array
    {
        $showCredit = resolve(ShowCreditService::class)->update($showCredit, $req->validated());
        return $this->getSuccessResponse($showCredit);
    }

    public function delete(ShowCredit $showCredit, DeleteShowCreditRequest $req): array
    {
        resolve(ShowCreditService::class)->delete($showCredit);
        return $this->getSuccessResponse($showCredit);
    }
}
