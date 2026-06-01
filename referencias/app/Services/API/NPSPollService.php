<?php

namespace App\Services\API;

use Exception;
use App\Models\User;
use App\Models\Client;
use App\Models\NPSPoll;
use App\Models\NPSPollAnswer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Repositories\NPSPollRepository;
use App\Services\Traits\GetClientFromRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Cache\NPSPollRepositoryCache;
use App\Repositories\Criteria\Sort\News\SortByCreated;
use App\Repositories\Criteria\Filter\NPSPolls\DateEndCriteria;
use App\Services\API\Dispatchers\ClientEventsDispatcherService;
use App\Repositories\Criteria\Filter\NPSPolls\DateStartCriteria;
use App\Repositories\Criteria\Filter\NPSPolls\VisibleClientCriteria;


class NPSPollService
{

    use GetClientFromRequest;


    public function __construct(
        private readonly NPSPollRepository|NPSPollRepositoryCache $NPSPollRepository,
        private readonly NPSPollAnswerService $NPSPollAnswerService,
    ) {
    }


    public function list(array $opts = []): Collection
    {
        $options = [
            'with' => $opts['with'] ?? [],
            'order' => $this->getSortCriteriasByName($opts['sort'] ?? ''),
            'filters' => $this->getFilterCriteriasByName($opts['filters'] ?? []),
        ];
        $response = $this->NPSPollRepository->list($options);
        return $response;
    }


    public function findOneOpenedByClient(Client $client): ?NPSPoll
    {
        $NPSPoll = $this->NPSPollRepository->findOneOpenedByClient($client);
        return $NPSPoll;
    }


    public function findCurrentUnscoredByUser(User $user): ?NPSPoll
    {
        $NPSPoll = $this->NPSPollRepository->findCurrentUnscoredByUser($user);
        return $NPSPoll;
    }


    // Trae la Poll y las respuestas que APUNTEN a cierto Client.
    // Recordar que client_id de Poll es siempre 2 (el ABM es de Clienty).
    public function findLastByTargetedClient(Client $client, array $opts = []): ?NPSPoll
    {
        $NPSPoll = $this->NPSPollRepository->findLastByTargetedClient($client, $opts);
        return $NPSPoll;
    }


    public function createWithAnswers(Client $client, array $NPSPollData, array $NPSPollAnswersData): NPSPoll
    {
        try {
            DB::beginTransaction();
            $NPSPollData['client_id'] = $client->id;
            $NPSPoll = $this->create($NPSPollData);
            $clientIds = new Collection($NPSPollAnswersData['client_id']);
            $this->NPSPollAnswerService->createByPollAndClientIds($NPSPoll, $clientIds);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $NPSPoll;
    }


    public function updateWithAnswers(
        Client $client,
        NPSPoll $NPSPoll,
        array $NPSPollData,
        array $NPSPollAnswersData
    ): NPSPoll {
        try {
            DB::beginTransaction();
            $updatedNPSPoll = $this->update($NPSPoll, $NPSPollData);
            $clientIds = new Collection($NPSPollAnswersData['client_id']);
            $this->NPSPollAnswerService->updateByPollAndClientIds($updatedNPSPoll, $clientIds);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $updatedNPSPoll->fresh();
    }


    public function deleteWithAnswers(NPSPoll $NPSPoll): NPSPoll
    {
        try {
            DB::beginTransaction();

            $deletedNPSPoll = $this->delete($NPSPoll);
            $this->NPSPollAnswerService->deleteAllByNPSPoll($NPSPoll);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $deletedNPSPoll;
    }


    public function closeWithAnswers(NPSPoll $NPSPoll, array $dataToUpdate): NPSPoll
    {
        try {
            DB::beginTransaction();
            $NPSPollData = $dataToUpdate['nps_poll_data'];
            $NPSPollAnswerData = $dataToUpdate['nps_poll_answer_data'];
            $deletedNPSPoll = $this->update($NPSPoll, $NPSPollData);
            $this->NPSPollAnswerService->updateAllByNPSPoll($NPSPoll, $NPSPollAnswerData);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $deletedNPSPoll;
    }


    public function saveUserScore(NPSPollAnswer $NPSPollAnswer, int $score): NPSPollAnswer
    {
        $NPSPollAnswer = $this->NPSPollAnswerService->saveUserScore($NPSPollAnswer, $score);
        $this->NPSPollRepository->clearCacheForClient($NPSPollAnswer->client_id);
        return $NPSPollAnswer;
    }


    public function saveUserComments(NPSPollAnswer $NPSPollAnswer, string $comments): NPSPollAnswer
    {
        $NPSPollAnswer = $this->NPSPollAnswerService->update($NPSPollAnswer, ['comments' => $comments]);
        $this->NPSPollRepository->clearCacheForClient($NPSPollAnswer->client_id);
        return $NPSPollAnswer;
    }
    

    public function create(array $data): NPSPoll
    {
        $NPSPoll = $this->NPSPollRepository->create($data);
        $this->NPSPollRepository->clearCacheForAllClients();
        return $NPSPoll;
    }


    public function update(NPSPoll $NPSPoll, array $data): NPSPoll
    {
        $updated = $this->NPSPollRepository->update($NPSPoll, $data);
        $this->NPSPollRepository->clearCacheForAllClients();
        return $updated;
    }


    public function delete(NPSPoll $NPSPoll): NPSPoll
    {
        $deleted = $this->NPSPollRepository->delete($NPSPoll);
        $this->NPSPollRepository->clearCacheForAllClients();
        return $deleted;
    }


    protected function getFilterCriteriasByName(array $filters): array
    {
        $nfilters = [];
        $criterias = [
            'client_id' => VisibleClientCriteria::class,
            'created_date_end' => DateEndCriteria::class,
            'created_date_start' => DateStartCriteria::class,
        ];

        foreach ($filters as $key => $value) {
            if (in_array($key, array_keys($criterias)) && $value !== null) {
                $nfilters[$key] = new $criterias[$key]($value);
            } else {
                $nfilters[$key] = $value;
            }
        }

        return $nfilters;
    }


    private function getSortCriteriasByName($sortsName)
    {
        $sortTypes = ['date_asc' => new SortByCreated('asc'), 'date_desc' => new SortByCreated('desc')];
        return $sortsName ? $sortTypes[$sortsName] : $sortsName;
    }

}
