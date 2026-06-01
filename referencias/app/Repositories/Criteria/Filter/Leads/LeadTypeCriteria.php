<?php

namespace App\Repositories\Criteria\Filter\Leads;

use App\Models\User;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class LeadTypeCriteria implements SQLFilterCriteria
{

    private array $leadTypes;


    public function __construct(array $leadTypes)
    {
        $this->leadTypes = $leadTypes;
    }


    public function filterSQLQuery(object $builder): object
    {
        $builder->where(function ($query) {
            foreach ($this->leadTypes as $type) {
                if ($type == 'method_chat') {
                    $query->orWhere('method', 'chat');
                }
                if ($type == 'make_app') {
                    $query->orWhere('is_from_make_app', true);
                }
                if ($type == 'facebook_form') {
                    $query->orWhere('is_facebook_form', true);
                }
                if ($type == 'bulk_created') {
                    $query->orWhere('is_bulk_created', true);
                }
                if ($type == 'whatsapp_form') {
                    $query->orWhere('is_whatsapp_form', true);
                }
                if ($type == 'zapier_app') {
                    $query->orWhere('is_from_zapier_app', true);
                }
                if ($type == 'manually_created') {
                    $query->orWhere('is_manually_created', true);
                }
                if ($type == 'integration_api') {
                    $query->orWhere('is_from_integration_api', true);
                }
                if ($type == 'method_form') {
                    $query->orWhere(function ($q) {
                        $q->where('method', 'form')
                            ->where('is_from_make_app', false)
                            ->where('is_facebook_form', false)
                            ->where('is_whatsapp_form', false)
                            ->where('is_from_zapier_app', false)
                            ->where('is_manually_created', false)
                            ->where('is_from_integration_api', false)
                        ;
                    });
                }
            }
        });
        
        return $builder;
    }

}
