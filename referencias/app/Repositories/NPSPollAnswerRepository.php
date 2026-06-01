<?php

namespace App\Repositories;

use DateTime;
use Exception;
use App\Models\NPSPoll;
use App\Models\NPSPollAnswer;
use Illuminate\Support\Collection;
use App\Repositories\Traits\VoidClearCache;


class NPSPollAnswerRepository implements Repository
{
    
    use VoidClearCache;


    public function create($data): NPSPollAnswer
    {
        $NPSPollAnswer = new NPSPollAnswer($data);
        $NPSPollAnswer->saveOrFail();
        return $NPSPollAnswer->fresh();
    }


    public function update(NPSPollAnswer $NPSPollAnswer, array $data): NPSPollAnswer
    {
        $NPSPollAnswer->fill($data);
        $NPSPollAnswer->saveOrFail();
        return $NPSPollAnswer->fresh();
    }


    public function delete(NPSPollAnswer $NPSPollAnswer): NPSPollAnswer
    {
        $NPSPollAnswer->delete();
        return $NPSPollAnswer->fresh();
    }


    public function deleteByPollAndClientIds(NPSPoll $NPSPoll, Collection $clientIds): bool
    {
        $dateNow = new DateTime();
        $response = NPSPollAnswer::whereIn('client_id', $clientIds)
            ->where('nps_poll_id', $NPSPoll->id)
            ->update([
                'deleted_at_ts' => $dateNow->getTimestamp(),
                'deleted_at' => $dateNow->format('Y-m-d H:i:s'),
            ])
        ;
        return $response;
    }


    public function deleteAllByNPSPoll(NPSPoll $NPSPoll): bool
    {
        $dateNow = new DateTime();
        $response = NPSPollAnswer::where('nps_poll_id', $NPSPoll->id)->update([
            'deleted_at_ts' => $dateNow->getTimestamp(),
            'deleted_at' => $dateNow->format('Y-m-d H:i:s'),
        ]);
        return $response;
    }


    public function updateAllByNPSPoll(NPSPoll $NPSPoll, array $data): bool
    {
        $dateNow = new DateTime();
        $response = NPSPollAnswer::where('nps_poll_id', $NPSPoll->id)->update($data);
        return $response;
    }


    public function bulkInsert($data): bool
    {
        return NPSPollAnswer::insert($data);
    }

}
