<?php

namespace App\Services\API\WAutomations;

use Exception;
use Throwable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Models\WAutomationSequence;
use App\Models\WAutomationSequenceStep;
use App\Services\Traits\GetClientFromRequest;
use App\DTO\WAutomations\WAutomationSequenceStepDTO;
use App\Repositories\Cache\WAutomationSequenceStepRepositoryCache;
use App\Repositories\WAutomations\WAutomationSequenceStepRepository;


class WAutomationSequenceStepService
{

    use GetClientFromRequest;

    private $wAutomationLogService;
    private $wAutomationSequenceStepRepository;


    public function __construct(
        WAutomationSequenceStepRepository | WAutomationSequenceStepRepositoryCache $wAutomationSequenceStepRepository,
        WAutomationLogService $wAutomationLogService
    ) {
        $this->wAutomationLogService = $wAutomationLogService;
        $this->wAutomationSequenceStepRepository = $wAutomationSequenceStepRepository;
    }


    public function findByWAutomationSequence(WAutomationSequence $wAutomationSequence): Collection
    {
        return $this->wAutomationSequenceStepRepository->findByWAutomationSequence($wAutomationSequence);
    }


    public function create(WAutomationSequenceStepDTO $dto): WAutomationSequenceStep
    {
        return $this->wAutomationSequenceStepRepository->create($dto);
    }


    public function update(WAutomationSequenceStep $step, WAutomationSequenceStepDTO $dto): WAutomationSequenceStep
    {
        if (!$this->parametersChanged($step, $dto)) {
            return $step;
        }

        // If rule was never applied, I can update the row.
        $existentLog = $this->wAutomationLogService->findOneByWAutomationSequenceStep($step);
        if (!$existentLog) {
            $step = $this->wAutomationSequenceStepRepository->update($step, $dto);
            return $step;
        }

        try {
            DB::beginTransaction();
            // If rule was applied at least once, I soft-delete it and create a new one.
            $this->wAutomationSequenceStepRepository->delete($step);
            $step = $this->wAutomationSequenceStepRepository->create($dto);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $step;
    }


    public function parametersChanged(WAutomationSequenceStep $step, WAutomationSequenceStepDTO $dto): bool
    {
        $tagsToAdd = $step->tagsToAdd->pluck('id')->toArray();
        if (
            $step->send_hour == $dto->sendHour &&
            $step->add_tags_ids === $dto->tagsToAdd &&
            $step->send_delay_days === $dto->sendDelayDays &&
            $step->send_delay_minutes === $dto->sendDelayMinutes &&
            $step->sendWhatsAppTemplate->id === $dto->sendWhatsAppTemplate->id
        ) {
            return false;
        }
        return true;
    }


    public function delete(WAutomationSequenceStep $step): WAutomationSequenceStep
    {
        return $this->wAutomationSequenceStepRepository->delete($step);
    }

    
    public function cloneWAutomationSequenceSteps(
        WAutomationSequence $originalWAutomationSequence,
        WAutomationSequence $newWAutomationSequence
    ): Collection {
        $newSteps = new Collection();
        $originalSteps = $originalWAutomationSequence->wAutomationSequenceSteps;
        foreach ($originalSteps as $originalStep) {
            $newStepData = [
                'client' => $originalStep->client,
                'send_hour' => $originalStep->send_hour,
                'wAutomationSequence' => $newWAutomationSequence,
                'send_delay_days' => $originalStep->send_delay_days,
                'send_delay_minutes' => $originalStep->send_delay_minutes,
                'sendWhatsAppTemplate' => $originalStep->sendWhatsAppTemplate,
            ];
            $newStepDto = WAutomationSequenceStepDTO::build($newStepData);
            $newStep = $this->create($newStepDto);
            $newSteps->push($newStep);
        }
        return $newSteps;
    }

    
    public function deleteAllByWAutomationSequence(WAutomationSequence $wAutomation): bool
    {
        return $this->wAutomationSequenceStepRepository->deleteAllByWAutomationSequence($wAutomation);
    }

}
