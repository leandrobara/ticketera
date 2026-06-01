<?php

namespace App\Http\Requests;
use Illuminate\Http\UploadedFile;
use App\Services\API\WhatsAppAttachmentService;


class StoreWhatsAppAttachmentRequest extends APIBaseRequest
{
    
    public function rules()
    {
        return [
            'attachment' => [
                'required',
                'file',
            ],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $attachment = request()->attachment;
                $client = request()->input('client');

                $allowedExtensions = [
                    'jpeg', 'jpg', 'webp', 'csv', 'xlx', 'xlsx', 'ppt',
                    'pptx', 'bmp', 'png', 'pdf', 'docx', 'html', 'htm', 'mp4', 'txt'
                ];

                $extension = strtolower($attachment->getClientOriginalExtension());
                if (!in_array($extension, $allowedExtensions)) {
                    $validator->errors()->add(
                        'attachment', 'the_attachment_must_be_a_file_of_type:_' . implode(',_', $allowedExtensions)
                    );
                    return false;
                }

                // Validar tamaño: 15MB para mp4, 8MB para otros
                $maxSize = ($extension === 'mp4') ? 15000000 : 8000000;
                if ($attachment->getSize() > $maxSize) {
                    $maxSizeMB = $maxSize / 1000000;
                    $validator->errors()->add('attachment', "El archivo no puede superar los {$maxSizeMB} MB");
                    return false;
                }

                // $wapAttachment = resolve(WhatsAppAttachmentService::class)
                //     ->findExactOneByClientAndFile($client, $attachment)
                // ;
                // if ($wapAttachment) {
                //     $validator->errors()->add('whatsapp_attachment', 'whatsapp_attachment_name_already_exists');
                //     return false;
                // }
            }
        });
    }


    public function validated($key = null, $default = null)
    {
        $data = parent::all();
        return $data;
    }


    public function getUploadedFile(): UploadedFile
    {
        $validated = parent::validated();
        return $validated['attachment'];
    }

}
