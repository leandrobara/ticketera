<?php

namespace App\Http\Requests\Web;

use Illuminate\Http\Request;
use App\Helpers\GoogleAPIHelper;
use App\Http\Requests\APIBaseRequest;


// Medio hardcode, pero funciona para recibir el parámetro opcional de info precargada (desde importacion de WhatsApp)
class LeadsBulkUploadPageRequest extends APIBaseRequest
{

    private array | null $preLoadedLeadsPreviewArr;


    public function rules()
    {
        return [
            'preLoadedLeadsPreviewArr' => ['sometimes', 'string', 'nullable'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $preLoadedLeadsPreviewArr = request()->input('preLoadedLeadsPreviewArr');
                if (!$preLoadedLeadsPreviewArr) {
                    $this->preLoadedLeadsPreviewArr = null;
                } else {
                    $this->preLoadedLeadsPreviewArr = json_decode($preLoadedLeadsPreviewArr, true);
                }
            }
        });
    }


    public function getPreLoadedLeadsPreviewArr(): array | null
    {
        return $this->preLoadedLeadsPreviewArr;
    }

}
