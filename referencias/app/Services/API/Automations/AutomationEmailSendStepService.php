<?php

namespace App\Services\API\Automations;

use Exception;
use Throwable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Models\AutomationEmailSend;
use App\Models\AutomationEmailSendStep;
use App\Services\Traits\GetClientFromRequest;
use App\DTO\Automations\AutomationEmailSendStepDTO;
use App\Repositories\Automations\AutomationEmailSendStepRepository as StepRepo;
use App\Exceptions\Services\Automations\AutomationEmailSendStepServiceException;
use App\Repositories\Cache\AutomationEmailSendStepRepositoryCache as StepRepoCache;


class AutomationEmailSendStepService
{

    use GetClientFromRequest;


    public function __construct(
        protected readonly StepRepo | StepRepoCache $automationEmailSendStepRepository,
        protected readonly AutomationLogService $automationLogService
    ) {
    }


    public function findByAutomationEmailSend(AutomationEmailSend $automationEmailSend): Collection
    {
        return $this->automationEmailSendStepRepository->findByAutomationEmailSend($automationEmailSend);
    }


    public function create(AutomationEmailSendStepDTO $dto): AutomationEmailSendStep
    {
        return $this->automationEmailSendStepRepository->create($dto);
    }


    public function update(AutomationEmailSendStep $step, AutomationEmailSendStepDTO $dto): AutomationEmailSendStep
    {
        if (!$this->parametersChanged($step, $dto)) {
            return $step;
        }

        // If rule was never applied, I can update the row.
        $existentAppliedRuleLog = $this->automationLogService->findOneByAutomationEmailSendStep($step);
        if (!$existentAppliedRuleLog) {
            $step = $this->automationEmailSendStepRepository->update($step, $dto);
            return $step;
        }

        try {
            DB::beginTransaction();
            // If rule was applied at least once, I soft-delete it and create a new one.
            $this->automationEmailSendStepRepository->delete($step);
            $step = $this->automationEmailSendStepRepository->create($dto);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw new AutomationEmailSendStepServiceException($e->getMessage(), (int) $e->getCode());
        }

        return $step;
    }


    public function parametersChanged(
        AutomationEmailSendStep $step,
        AutomationEmailSendStepDTO $dto
    ): bool {
        // Comparar tags: convertir ambos a arrays de IDs para comparar
        $currentTagIds = $step->add_tags_ids ?? [];
        $newTagIds = $dto->tagsToAdd->pluck('id')->toArray();
        sort($currentTagIds);
        sort($newTagIds);
        $tagsChanged = $currentTagIds !== $newTagIds;

        // Comparar status
        $statusChanged = $step->add_status_id !== $dto->statusToAdd?->id;

        if (
            $step->sendEmailTemplate->id === $dto->sendEmailTemplate->id &&
            $step->send_delay_days === $dto->sendDelayDays &&
            $step->send_delay_minutes === $dto->sendDelayMinutes &&
            $step->send_hour == $dto->sendHour &&
            !$tagsChanged &&
            !$statusChanged
        ) {
            return false;
        }
        return true;
    }


    public function delete(AutomationEmailSendStep $step): AutomationEmailSendStep
    {
        return $this->automationEmailSendStepRepository->delete($step);
    }

    
    public function cloneAutomationEmailSendSteps(
        AutomationEmailSend $originalAutomation,
        AutomationEmailSend $newAutomation
    ): Collection {
        $newSteps = new Collection();
        $originalSteps = $originalAutomation->automationEmailSendSteps;
        foreach ($originalSteps as $originalStep) {
            $newStepData = [
                'client' => $originalStep->client,
                'tagsToAdd' => $originalStep->tagsToAdd,
                'statusToAdd' => $originalStep->statusToAdd,
                'automationEmailSend' => $newAutomation,
                'send_hour' => $originalStep->send_hour,
                'send_delay_days' => $originalStep->send_delay_days,
                'sendEmailTemplate' => $originalStep->sendEmailTemplate,
                'send_delay_minutes' => $originalStep->send_delay_minutes,
            ];
            $newStepDto = AutomationEmailSendStepDTO::build($newStepData);
            $newStep = $this->create($newStepDto);
            $newSteps->push($newStep);
        }
        return $newSteps;
    }

    
    public function deleteAllByAutomationEmailSend(AutomationEmailSend $automation): bool
    {
        return $this->automationEmailSendStepRepository->deleteAllByAutomationEmailSend($automation);
    }

}
