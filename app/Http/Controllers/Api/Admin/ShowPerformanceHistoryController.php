<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Admin\CreateShowPerformanceHistoryRequest;
use App\Http\Requests\Admin\DeleteShowPerformanceHistoryRequest;
use App\Http\Requests\Admin\GetShowPerformanceHistoryRequest;
use App\Http\Requests\Admin\ListShowPerformanceHistoryRequest;
use App\Http\Requests\Admin\UpdateShowPerformanceHistoryRequest;
use App\Models\ShowPerformanceHistory;
use App\Services\Api\Admin\ShowPerformanceHistoryService;

class ShowPerformanceHistoryController extends BaseAPIController
{
    public function list(ListShowPerformanceHistoryRequest $request): array
    {
        $history = resolve(ShowPerformanceHistoryService::class)->list($request->validated());

        return $this->getSuccessResponse($history);
    }

    public function create(CreateShowPerformanceHistoryRequest $request): array
    {
        $history = resolve(ShowPerformanceHistoryService::class)->create($request->validated());

        return $this->getSuccessResponse($history);
    }

    public function show(
        ShowPerformanceHistory $showPerformanceHistory,
        GetShowPerformanceHistoryRequest $request,
    ): array {
        $history = resolve(ShowPerformanceHistoryService::class)->getOne($showPerformanceHistory);

        return $this->getSuccessResponse($history);
    }

    public function update(
        ShowPerformanceHistory $showPerformanceHistory,
        UpdateShowPerformanceHistoryRequest $request,
    ): array {
        $history = resolve(ShowPerformanceHistoryService::class)->update(
            $showPerformanceHistory,
            $request->validated(),
        );

        return $this->getSuccessResponse($history);
    }

    public function delete(
        ShowPerformanceHistory $showPerformanceHistory,
        DeleteShowPerformanceHistoryRequest $request,
    ): array {
        resolve(ShowPerformanceHistoryService::class)->delete($showPerformanceHistory);

        return $this->getSuccessResponse($showPerformanceHistory);
    }
}
