<?php

namespace App\Http\Requests\Actions;

use App\Models\NPSPollAnswer;
use App\Http\Requests\APIBaseRequest;


class DoNPSPollCommentsRequest extends APIBaseRequest
{

    public NPSPollAnswer $NPSPollAnswer;


    public function rules()
    {
        return [
            'comments' =>  ['required', 'string']
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $loginUser = request()->user;
                $comments = request()->comments;
                $NPSPoll = request()->NPSPoll;
                $loginClientId = request()->client->id;

                if (!$NPSPoll) {
                    $validator->errors()->add('nps_poll', 'nps_poll_does_not_exist');
                    return false;
                }

                $NPSPollAnswer = $NPSPoll->getNPSPollAnswerByUser($loginUser);
                if (!$NPSPollAnswer) {
                    $validator->errors()->add('user_id', 'user_or_client_does_not_match_with_authenticated_user');
                    return false;
                }
                if ($NPSPollAnswer->comments) {
                    $validator->errors()->add(
                        'nps_poll_answer_comments', 'nps_poll_answer_was_already_commented'
                    );
                    return false;
                }
                if (!$NPSPollAnswer->score) {
                    $validator->errors()->add('nps_poll_answer_score', 'nps_poll_answer_score_must_be_completed_first');
                    return false;
                }
                $this->NPSPollAnswer = $NPSPollAnswer;
            });
        }
    }

}
