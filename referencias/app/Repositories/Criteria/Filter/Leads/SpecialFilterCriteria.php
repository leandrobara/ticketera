<?php

namespace App\Repositories\Criteria\Filter\Leads;

use App\Models\User;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class SpecialFilterCriteria implements SQLFilterCriteria
{
    
    private ?User $user;
    private array $specialFilters;


    public function __construct(array $specialFilters, ?User $user = null)
    {
        $this->user = $user;
        $this->specialFilters = $specialFilters;
    }


    public function filterSQLQuery(object $builder): object
    {
        foreach ($this->specialFilters as $filter) {
            if ($filter === 'sent_emails') {
                $builder->whereHas('emails');
            }
            if ($filter === 'empty_lead_emails') {
                $builder->whereDoesntHave('leadContactEmails');
            }
            if ($filter === 'empty_lead_phones') {
                $builder->whereDoesntHave('leadContactPhones');
            }
            if ($filter === 'opened_emails') {
                $builder->whereHas('emails', function ($query) {
                    $query->whereNotNull('opened_date');
                });
            }
            if ($filter === 'not_opened_emails') {
                $builder->whereHas('emails') // al menos un email enviado
                    ->whereDoesntHave('emails', function ($query) {
                        $query->whereNotNull('opened_date'); // ninguno abierto
                    })
                ;
            }
            if ($filter === 'repeated_phones') {
                $builder->whereHas('leadContactPhones', function ($query) {
                    $query->whereNotNull('lead_ids_where_repeated');
                });
            }
            
            if ($filter === 'sent_wapi_msgs') {
                $builder->whereHas('whatsAppSendingMessages', function ($query) {
                    $query->where('success', true);
                });
            }
            if ($filter === 'sent_wapi_msgs_with_error') {
                $builder->whereHas('whatsAppSendingMessages', function ($query) {
                    $query->where(function ($subQuery) {
                        $subQuery->where('success', false)
                            ->whereNotNull('error_message')
                            ->whereNotNull('sent_date')
                        ;
                    });
                });
            }

            if ($filter === 'sync_with_google') {
                $builder->whereHas('googleAPIUserContacts', function ($query) {
                    $query->where('user_id', $this->user?->id);
                });
            }
            if ($filter === 'not_sync_with_google') {
                $builder->whereDoesntHave('googleAPIUserContacts', function ($query) {
                    $query->where('user_id', $this->user?->id);
                });
            }

            if ($this->isEmailFilter($filter)) {
                $builder->whereHas('leadContactEmails', function ($query) use ($filter) {
                    if ($filter === 'removed_emails') {
                        $query->where(function ($subQuery) {
                            $subQuery->orWhere('bounced', true)
                                ->orWhere('is_valid', false)
                                ->orWhere('complained', true)
                                ->orWhere('unsubscribed', true)
                            ;
                        });
                    }
                    if ($filter === 'bounced_emails') {
                        $query->where('bounced', true);
                    }
                    if ($filter === 'invalid_emails') {
                        $query->where('is_valid', false);
                    }
                    if ($filter === 'complained_emails') {
                        $query->where('complained', true);
                    }
                    if ($filter === 'unsubscribed_emails') {
                        $query->where('unsubscribed', true);
                    }
                    if ($filter === 'repeated_emails') {
                        $query->whereNotNull('lead_ids_where_repeated');
                    }
                });
            }
        }
        return $builder;
    }


    private function isEmailFilter($filter): bool
    {
        return in_array($filter, [
            'removed_emails',
            'bounced_emails',
            'invalid_emails',
            'repeated_emails',
            'complained_emails',
            'unsubscribed_emails'
        ]);
    }

}
