<?php

namespace App\Services\API;

use DateTime;
use Exception;
use DateTimeZone;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Client;
use App\Models\NPSPoll;
use App\Models\NPSPollAnswer;
use App\Models\NewsNotification;
use App\Repositories\Repository;
use App\Services\API\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Services\API\ClientService;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\NewsNotificationRepository;
use App\Services\API\Dispatchers\ClientEventsDispatcherService;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;


class NPSPollAnswerService
{

    use GetClientFromRequest, GetUserFromRequest;


    public function __construct(
        private readonly UserService $userService,
        private readonly ClientService $clientService,
        private readonly Repository $NPSPollAnswerRepository,
    ) {
    }


    public function createByPollAndClientIds(NPSPoll $NPSPoll, Collection $clientIds): bool
    {
        if ($clientIds->isEmpty()) {
            $clientIds = $this->clientService->findAllEnabled()->pluck('id');
        }
        $clientIds = collect($clientIds);

        $dateNow = new Datetime();
        $bulkData = $this->userService
            ->findAllEnabledByClientIds($clientIds, ['fields' => ['id', 'client_id']])
            ->map(function ($u) use ($NPSPoll, $dateNow) {
                return [
                    'user_id' => $u->id,
                    'nps_poll_id' => $NPSPoll->id,
                    'created_at' => $dateNow,
                    'updated_at' => $dateNow,
                    'client_id' => $u->client_id,
                ];
            })
        ;
        $this->NPSPollAnswerRepository->bulkInsert($bulkData->toArray());
        return true;
    }


    public function updateByPollAndClientIds(NPSPoll $NPSPoll, Collection $newClientIds): bool
    {
        if ($newClientIds->isEmpty()) {
            $newClientIds = $this->clientService->findAllEnabled()->pluck('id');
        }
        $existentClientIds = $NPSPoll->NPSPollAnswers->pluck('client_id')->unique();

        $clientIdsToAdd = $newClientIds->diff($existentClientIds);
        $clientIdsToRemove = $existentClientIds->diff($newClientIds);

        $this->createByPollAndClientIds($NPSPoll, $clientIdsToAdd);
        $this->deleteByPollAndClientIds($NPSPoll, $clientIdsToRemove);

        return true;
    }


    public function deleteByPollAndClientIds(NPSPoll $NPSPoll, Collection $clientIds): bool
    {
        $this->NPSPollAnswerRepository->deleteByPollAndClientIds($NPSPoll, $clientIds);
        return true;
    }


    public function deleteAllByNPSPoll(NPSPoll $NPSPoll): bool
    {
        $this->NPSPollAnswerRepository->deleteAllByNPSPoll($NPSPoll);
        return true;
    }


    public function updateAllByNPSPoll(NPSPoll $NPSPoll, array $data): bool
    {
        $this->NPSPollAnswerRepository->updateAllByNPSPoll($NPSPoll, $data);
        return true;
    }


    public function saveUserScore(NPSPollAnswer $NPSPollAnswer, int $score): NPSPollAnswer
    {
        $data['score'] = $score;
        $data['closed_date'] = new DateTime();
        $data['close_reason'] = 'user_scored';
        return $this->update($NPSPollAnswer, $data);
    }


    public function update(NPSPollAnswer $NPSPollAnswer, array $data): NPSPollAnswer
    {
        return $this->NPSPollAnswerRepository->update($NPSPollAnswer, $data);
    }

}
