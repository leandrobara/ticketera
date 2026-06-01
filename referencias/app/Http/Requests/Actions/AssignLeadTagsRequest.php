<?php

namespace App\Http\Requests\Actions;

use App\Services\API\TagService;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class AssignLeadTagsRequest extends APIBaseRequest
{

    protected $tags;

    public function rules()
    {
        return [
            'tag_id' => ['present', 'array'],
            'tag_id.*' => ['sometimes', 'integer'],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $tagIds = request()->input('tag_id');
                $client = request()->input('client');

                if (request()->lead->client_id != $client->id) {
                    $validator->errors()->add('client_id', 'lead_client_does_not_match_with_authenticated_client');
                    return false;
                }

                $tags = $tagIds ? resolve(TagService::class)->findByClientAndIds($client, $tagIds) : collect([]);
                if ($tags->count() != count($tagIds)) {
                    $validator->errors()->add('tag_id', 'tag_id_does_not_match_with_authenticated_client');
                    return false;
                }
                $this->tags = $tags;
            });
        }
    }


    public function getTags(): Collection
    {
        return $this->tags;
    }

}
