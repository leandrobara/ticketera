<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Validator;
use App\DTO\Attachments\SaveAttachmentDTO;


class SaveAttachmentRequest extends APIBaseRequest
{

    protected $rules = [
        'attachment' => [
            'required',
            'file',
            'mimes:jpeg,jpg,webp,webm,mp4,mp3,avi,csv,xlx,xlsx,ppt,pptx,bmp,png,pdf,docx,html,htm,txt',
        ],
    ];


    public function rules()
    {
        return [];
    }


    public function validatedDTO(): SaveAttachmentDTO
    {

        $data = parent::all();
        $attachment = $data['attachment'] ?? null;
        if (!$attachment) {
            throw new \Exception('no_attachment_file');
        }

        // Fix para ciertos casos raros de PDF.
        $validator = Validator::make(request()->all(), $this->rules);
        if ($validator->fails()) {
            $mime = $attachment->getClientMimeType();
            if ($mime != 'application/pdf') {
                throw new \Exception('uploaded_file_invalid_mime_type');
            }
        }

        $dto = SaveAttachmentDTO::build($attachment);
        return $dto;
    }

}
