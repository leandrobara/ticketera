<?php

namespace App\Http\Requests\Actions;

use App\Models\Tag;
use App\Models\Lead;
use App\Rules\InLeadReturnFields;
use App\Http\Requests\APIBaseRequest;


class SetLeadsTagsRequest extends APIBaseRequest
{

    protected $tags = [];
    protected $leads = [];


    public function rules()
    {
        return [
            'lead_id' => ['required', 'array'],
            'lead_id.*' => ['required', 'integer'],
            'tag_id' => ['present', 'array'],
            'tag_id.*' => ['sometimes', 'integer'],
            'type' => ['required', 'string', 'in:add,replace,remove'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $clientId = request()->input('client')->id;
               
                $leadIds = request()->input('lead_id');
                $leads = Lead::where('client_id', $clientId)->whereIn('id', $leadIds)->with(['tags'])->get();
                $leadIdsCount = count($leadIds);
                $leadsCount = $leads->count();
                if ($leadIdsCount != $leadsCount) {
                    $validator->errors()->add('lead_id', 'Some leads do not exist');
                    return false;
                }
                $this->leads = $leads;

                $tagIds = request()->input('tag_id');
                if ($tagIds) {
                    $tags = Tag::where('client_id', $clientId)->whereIn('id', $tagIds)->get();
                    $tagIdsCount = count($tagIds);
                    $tagsCount = $tags->count();
                    if ($tagIdsCount != $tagsCount) {
                        $validator->errors()->add('tag_id', 'Some tags do not exist');
                        return false;
                    }
                    $this->tags = $tags;
                }
            }
        });
    }


    public function validatedAttributes()
    {
        $validated = parent::validated();
        unset($validated['lead_id']);
        $validated['leads'] = collect($this->leads);

        unset($validated['tag_id']);
        $validated['tags'] = collect($this->tags);
        return $validated;
    }

}
