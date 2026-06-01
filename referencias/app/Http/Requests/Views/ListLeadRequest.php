<?php

namespace App\Http\Requests\Views;

use DateTime;
use DateTimeZone;
use Illuminate\Validation\Rule;
use App\Http\Requests\APIBaseRequest;
use App\Rules\IsRequiredIntegerOrArray;
use App\Services\API\LeadCustomFieldService;
use App\Rules\IsRequiredNullableIntegerOrArray;


class ListLeadRequest extends APIBaseRequest
{

    public function rules()
    {
        $specialFilters = [
            'sent_emails',
            'opened_emails',
            'sent_wapi_msgs',
            'removed_emails',
            'bounced_emails',
            'invalid_emails',
            'repeated_emails',
            'repeated_phones',
            'sync_with_google',
            'not_opened_emails',
            'empty_lead_phones',
            'empty_lead_emails',
            'complained_emails',
            'unsubscribed_emails',
            'not_sync_with_google',
            'sent_wapi_msgs_with_error',
        ];
        $leadType = [
            'make_app',
            'zapier_app',
            'method_chat',
            'method_form',
            'bulk_created',
            'facebook_form',
            'whatsapp_form',
            'integration_api',
            'manually_created',
        ];
        $tagFilterTypeRules = ['or', 'and', 'or_exclusive', 'and_exclude', 'or_exclude'];
        $utmRules = ['utm_source', 'utm_medium', 'utm_content', 'utm_campaign', 'utm_keywords'];
        $sortRules = ['date_asc', 'date_desc', 'last_status_changed_date_asc', 'last_status_changed_date_desc'];

        return [
            'page' => ['sometimes', 'int'],
            'limit' => ['sometimes', 'int'],
            'sort' => ['sometimes', 'nullable', Rule::in($sortRules)],
            'filters' => ['sometimes', 'array'],
            'filters.search' => ['sometimes', 'string'],
            'filters.user_id' => ['sometimes', 'array'],
            'filters.quality' => ['sometimes', new IsRequiredIntegerOrArray()],
            'filters.status_id' => ['sometimes', new IsRequiredIntegerOrArray()],
            'filters.created_date_end' => ['sometimes', 'date_format:Y-m-d\TH:i:sP'],
            'filters.tag_filter_type' => ['sometimes', Rule::in($tagFilterTypeRules)],
            'filters.tag_id' => ['sometimes', new IsRequiredNullableIntegerOrArray()],
            'filters.created_date_start' => ['sometimes', 'date_format:Y-m-d\TH:i:sP'],
            'filters.landing_id' => ['sometimes', new IsRequiredNullableIntegerOrArray()],
            'filters.acquisition_channel_id' => ['sometimes', new IsRequiredNullableIntegerOrArray()],
            'filters.special_filter' => ['sometimes', 'array'],
            'filters.special_filter.*' => ['sometimes', 'string', Rule::in($specialFilters)],
            'filters.id' => ['sometimes', 'array', 'max:1500'],
            'filters.id.*' => ['required', 'integer'],
            'filters.utm' => ['sometimes', 'array'],
            'filters.utm.*.value' => ['required', 'string'],
            'filters.utm.*.name' => ['required', 'string', Rule::in($utmRules)],
            'filters.custom_field' => ['sometimes', 'array'],
            'filters.custom_field.*.id' => ['required', 'int'],
            'filters.custom_field.*.value' => ['required', 'string'],
            'filters.lead_type' => ['sometimes', 'array'],
            'filters.lead_type.*' => ['sometimes', 'string', 'distinct', Rule::in($leadType)],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $filters = request()->input('filters');

                $searchTerm = $filters['search'] ?? null;
                if ($searchTerm && $this->searchTermHasFindByLeadIdsClause($searchTerm)) {
                    $stringLeadIds = str_replace('id:', '', strtolower(trim($searchTerm)));
                    $leadIds = explode(",", rtrim($stringLeadIds, ","));
                    if (count($leadIds) > 1500) {
                        $validator->errors()->add('filters', 'filters_search_max_allowed_lead_ids_exceeded');
                        return false;
                    }
                }

                $utmFilters = collect($filters['utm'] ?? []);
                if ($utmFilters) {
                    $utmFilterNames = $utmFilters->pluck('name')->unique();
                    if ($utmFilters->count() != $utmFilterNames->count()) {
                        $validator->errors()->add('filters', 'filters_utm_can_not_be_repeated');
                        return false;
                    }
                }

                $customFieldFilters = collect($filters['custom_field'] ?? []);
                if ($customFieldFilters->isNotEmpty()) {
                    $customFieldUniqueIds = $customFieldFilters->pluck('id')->unique();
                    if ($customFieldFilters->count() != $customFieldUniqueIds->count()) {
                        $validator->errors()->add('filters', 'filters_lead_custom_fields_can_not_be_repeated');
                        return false;
                    }
                    $clientLeadCustomFields = resolve(LeadCustomFieldService::class)->findAllByClient();
                    $existentCustomFieldsCount = $clientLeadCustomFields
                        ->whereIn('id', $customFieldUniqueIds)
                        ->count()
                    ;
                    if ($customFieldFilters->count() != $existentCustomFieldsCount) {
                        $validator->errors()->add('filters', 'some_custom_fields_does_not_exists');
                        return false;
                    }
                }

                $filterSpecialFilters = collect($filters['special_filter'] ?? []);
                if ($filterSpecialFilters->isNotEmpty()) {
                    $specialFiltersUnique = $filterSpecialFilters->unique();
                    if ($specialFiltersUnique->count() != $filterSpecialFilters->count()) {
                        $validator->errors()->add('filters', 'special_filters_can_not_be_repeated');
                        return false;
                    }

                    $containRepeatedPhones = $filterSpecialFilters->contains('repeated_phones');
                    $containEmptyLeadPhones = $filterSpecialFilters->contains('empty_lead_phones');
                    if ($containRepeatedPhones && $containEmptyLeadPhones) {
                        $validator->errors()->add('filters', 'special_filters_invalid_phone_filters_applied');
                        return false;
                    }

                    if ($filterSpecialFilters->contains('empty_lead_emails')) {
                        foreach ($filterSpecialFilters as $filter) {
                            $specialFilterNotAllowed = collect([
                                'removed_emails',
                                'bounced_emails',
                                'invalid_emails',
                                'repeated_emails',
                                'complained_emails',
                                'unsubscribed_emails'
                            ])->contains($filter);
                            if ($specialFilterNotAllowed) {
                                $validator->errors()->add('filters', 'special_filters_invalid_email_filters_applied');
                                return false;
                            }
                        }
                    }
                }
            });
        }
    }


    public function validated($key = null, $default = null)
    {
        $val = parent::validated();

        if ($val['filters']['created_date_end'] ?? null) {
            $date = (new DateTime($val['filters']['created_date_end']))->setTimezone(new DateTimeZone('UTC'));
            $val['filters']['created_date_end'] = $date->format('Y-m-d\TH:i:sP');
        }
        if ($val['filters']['created_date_start'] ?? null) {
            $date = (new DateTime($val['filters']['created_date_start']))->setTimezone(new DateTimeZone('UTC'));
            $val['filters']['created_date_start'] = $date->format('Y-m-d\TH:i:sP');
        }

        // lowcase if 'null' exists in acquisition channel
        if ($val['filters']['acquisition_channel_id'] ?? null) {
            $acquisitionChannelValue =  $val['filters']['acquisition_channel_id'];
            if (is_array($acquisitionChannelValue)) {
                $val['filters']['acquisition_channel_id'] = array_map(function ($v) {
                    if ($v === null) {
                        return 'null';
                    }
                    return !is_int($v) ? strtolower($v) : $v;
                }, $acquisitionChannelValue);
            } else {
                $val['filters']['acquisition_channel_id'] = strtolower($acquisitionChannelValue) == 'null'
                    ? strtolower($acquisitionChannelValue)
                    : $acquisitionChannelValue
                ;
            }
        }

        //To eager load relations
        $val['with'] = [
            'tags',
            'user',
            'notes',
            'status',
            'client',
            'landing',
            'leadContacts',
            'acquisitionChannel',
            'leadCustomFieldsValues',
            'client.leadsCustomFields',
            'leadContacts.leadContactEmails',
            'leadContacts.leadContactPhones',
        ];
        if (!isset($val['sort'])) {
            $val['sort'] = 'date_desc';
        }
        return $val;
    }


    private function searchTermHasFindByLeadIdsClause(string $searchTerm): bool
    {
        return strpos(strtolower(trim($searchTerm)), 'id:') === 0;
    }

}
