<?php

namespace App\Http\Controllers\API\Actions;

use App\Models\NPSPoll;
use App\Services\API\NPSPollService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Actions\DoNPSPollScoreRequest;
use App\Http\Requests\Actions\DoNPSPollCommentsRequest;


class NPSPollController extends BaseAPIController
{

    public function doScore(NPSPoll $NPSPoll, DoNPSPollScoreRequest $req)
    {
        $NPSPollAnswer = resolve(NPSPollService::class)->saveUserScore(
            $req->NPSPollAnswer, $req->input('score')
        );
        return $this->getSuccessResponse($NPSPollAnswer);
    }


    public function doComments(NPSPoll $NPSPoll, DoNPSPollCommentsRequest $req)
    {
        $NPSPollAnswer = resolve(NPSPollService::class)->saveUserComments(
            $req->NPSPollAnswer, $req->input('comments')
        );
        return $this->getSuccessResponse($NPSPollAnswer);
    }
}
