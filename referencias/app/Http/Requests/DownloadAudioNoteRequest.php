<?php

namespace App\Http\Requests;


class DownloadAudioNoteRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->note->client_id != request()->input('client')->id) {
                $validator->errors()->add('client_id', 'note_client_does_not_match_with_authenticated_client');
            }
        });
    }

}
