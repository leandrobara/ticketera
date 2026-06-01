<?php

namespace App\Repositories;

use Exception;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Exceptions\DatabaseException;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class EmailTemplateRepository
{

    public function list(Client $client, array $options = [])
    {
        $filters = $options['filters'] ?? [];
        $queryBuilder = EmailTemplate::where('client_id', $client->id);

        foreach ($filters as $key => $value) {
            if (isset($filters[$key])) {
                if (is_array($value)) {
                    $queryBuilder->whereIn($key, $value);
                } elseif ($filters[$key] instanceof SQLFilterCriteria) {
                    $queryBuilder = $filters[$key]->filterSQLQuery($queryBuilder);
                } else {
                    $queryBuilder->where($key, $value);
                }
            }
        }
        return $queryBuilder->get();
    }


    public function findByIdAndClient(int $emailTemplateId, Client $client)
    {
        return EmailTemplate::find($emailTemplateId)->where('client_id', $client->id)->firs();
    }


    public function create(array $data): EmailTemplate
    {
        $emailTemplate = new EmailTemplate($data);
        $emailTemplate->saveOrFail();
        return $emailTemplate->fresh();
    }


    public function update(EmailTemplate $emailTemplate, array $data): EmailTemplate
    {
        $emailTemplate->fill($data);
        $emailTemplate->saveOrFail();
        return $emailTemplate->fresh();
    }


    public function delete(EmailTemplate $emailTemplate): EmailTemplate
    {
        $emailTemplate->delete();
        return $emailTemplate->fresh();
    }

}
