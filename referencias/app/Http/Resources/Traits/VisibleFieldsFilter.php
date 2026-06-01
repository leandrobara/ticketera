<?php

namespace App\Http\Resources\Traits;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


trait VisibleFieldsFilter
{

    private $options = [];


    protected function filterVisibleFields(array $response): array
    {
        $fieldsToShow = $this->getFieldsToShow();
        if (!$fieldsToShow) {
            return $response;
        }
        // $visibleFields = collect($response)->only($fieldsToShow)->toArray();
        $visibleFields = array_intersect_key($response, array_flip($fieldsToShow));
        return $visibleFields;
    }


    protected function getFieldsToShow(): array
    {
        return $this->options['visibleFields'] ?? [];
    }


    public function setVisibleFields(array $visibleFields): JsonResource
    {
        $this->options['visibleFields'] = $visibleFields;
        return $this;
    }


    public function setOptions(array $options): JsonResource
    {
        $this->options = $options;
        return $this;
    }


    public function loadOptionsFromRequest(Request $request): JsonResource
    {
        $onlyVisibleFieldsFilter = $request->input('fields', []);
        if ($onlyVisibleFieldsFilter) {
            $this->setVisibleFields($onlyVisibleFieldsFilter);
        }
        return $this;
    }

}
