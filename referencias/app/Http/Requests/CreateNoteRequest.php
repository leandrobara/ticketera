<?php

namespace App\Http\Requests;

use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Http\FormRequest;


class CreateNoteRequest extends FormRequest
{
    public function rules()
    {
        return [
            'text' => ['sometimes', 'string', 'nullable'],
            'audioBlob' => ['sometimes', 'nullable', 'file'/*, 'mimes:audio/mpeg,mpga,mp3,wav,aac,video/webm'*/],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $text = $this->input('text');
            $audioBlob = $this->file('audioBlob'); // Aquí se obtiene el archivo correctamente
            if ($this->lead->client_id != $this->input('client')->id) {
                $validator->errors()->add('client_id', 'lead_client_does_not_match_with_authenticated_client');
                return false;
            }
            if (!$text && !$audioBlob) {
                $validator->errors()->add('text', 'text_and_audio_are_empty');
                return false;
            }
        });
    }

    public function validatedAttributes()
    {
        $validated = parent::validated();
        if (isset($validated['fields'])) {
            unset($validated['fields']);
        }
        if (isset($validated['audioBlob'])) {
            unset($validated['audioBlob']);
        }
        return $validated;
    }


    public function getAudioBlob(): ?UploadedFile
    {
        return $this->file('audioBlob');
    }

}
