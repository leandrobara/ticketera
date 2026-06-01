<?php

namespace App\Http\Controllers\API\Actions\ClientyConfigurations;

use App\Models\NPSPoll;
use App\Models\NPSPollAnswer;
use App\Services\API\NPSPollService;
use App\Http\Resources\NPSPollsResource;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Actions\ClientyConfigurations\CloseNPSPollWithAnswersRequest;
use App\Http\Requests\Actions\ClientyConfigurations\CreateNPSPollWithAnswersRequest;
use App\Http\Requests\Actions\ClientyConfigurations\UpdateNPSPollWithAnswersRequest;
use App\Http\Requests\Actions\ClientyConfigurations\DeleteNPSPollWithAnswersRequest;


class NPSPollController extends BaseAPIController
{

    public function createWithAnswers(CreateNPSPollWithAnswersRequest $req)
    {
        $poll = resolve(NPSPollService::class)->createWithAnswers(
            $req->client, $req->validatedNPSPollData(), $req->validatedNPSPollAnswersData()
        );
        return $this->getSuccessResponse((new NPSPollsResource($poll))->loadOptionsFromRequest($req));
    }


    public function updateWithAnswers(NPSPoll $NPSPoll, UpdateNPSPollWithAnswersRequest $req)
    {
        $poll = resolve(NPSPollService::class)->updateWithAnswers(
            $req->client, $NPSPoll, $req->validatedNPSPollData(), $req->validatedNPSPollAnswersData()
        );
        return $this->getSuccessResponse((new NPSPollsResource($poll))->loadOptionsFromRequest($req));
    }


    public function closeWithAnswers(NPSPoll $NPSPoll, CloseNPSPollWithAnswersRequest $req)
    {
        $dataToClose = $req->dataToClose();
        $poll = resolve(NPSPollService::class)->closeWithAnswers($NPSPoll, $dataToClose);
        return $this->getSuccessResponse((new NPSPollsResource($poll))->loadOptionsFromRequest($req));
    }


    public function deleteWithAnswers(NPSPoll $NPSPoll, DeleteNPSPollWithAnswersRequest $req)
    {
        $poll = resolve(NPSPollService::class)->deleteWithAnswers($NPSPoll);
        return $this->getSuccessResponse((new NPSPollsResource($poll))->loadOptionsFromRequest($req));
    }

}
