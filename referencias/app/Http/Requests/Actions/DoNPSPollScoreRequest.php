<?php

namespace App\Http\Requests\Actions;

use App\Models\NPSPollAnswer;
use App\Http\Requests\APIBaseRequest;


class DoNPSPollScoreRequest extends APIBaseRequest
{

    public NPSPollAnswer $NPSPollAnswer;


    public function rules()
    {
        return [
            'score' =>  ['required', 'integer', 'min:1', 'max:10']
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $score = request()->score;
                $NPSPoll = request()->NPSPoll;
                $loginUser = request()->user;
                $loginClientId = request()->client->id;

                if (!$NPSPoll) {
                    $validator->errors()->add('nps_poll', 'nps_poll_does_not_exists');
                    return false;
                }
                if ($NPSPoll->closed_date) {
                    $validator->errors()->add('nps_poll', 'nps_poll_is_closed');
                    return false;
                }
                
                $NPSPollAnswer = $NPSPoll->getNPSPollAnswerByUser($loginUser);
                if (!$NPSPollAnswer) {
                    $validator->errors()->add('nps_poll_answer', 'nps_poll_answer_does_not_exists');
                    return false;
                }
                if ($NPSPollAnswer->client_id != $loginClientId) {
                    $validator->errors()->add('user_id', 'client_does_not_match_with_authenticated_client');
                    return false;
                }
                if ($NPSPollAnswer->closed_date) {
                    $validator->errors()->add('nps_poll_answer', 'nps_poll_answer_is_closed');
                    return false;
                }
                if ($NPSPollAnswer->score) {
                    $validator->errors()->add('nps_poll_answer_score', 'nps_poll_answer_score_already_was_scored');
                    return false;
                }
                $this->NPSPollAnswer = $NPSPollAnswer;
            });
        }
    }


    public function getNPSPollAnswer(): NPSPollAnswer
    {
        return $this->NPSPollAnswer;
    }

}
