<?php

namespace App\Repositories;

use Exception;
use App\Models\Lead;
use App\Models\Client;
use App\Models\LeadContact;
use App\Models\LeadContactEmail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Exceptions\DatabaseException;
use App\Repositories\Traits\ChunkedQueries;


class LeadContactEmailRepository
{
    
    use ChunkedQueries;


    public function findFirstOneByClientAndEmail(Client $client, string $email): ?LeadContactEmail
    {
        $hash = LeadContactEmail::buildHash($email);
        return LeadContactEmail::where('client_id', $client->id)->where('hash', $hash)->first();
    }


    public function findByClientAndEmail(Client $client, string $email, array $fields = []): Collection
    {
        $hash = LeadContactEmail::buildHash($email);
        $queryBuilder = LeadContactEmail::where('client_id', $client->id)->where('hash', $hash);
        if ($fields) {
            $queryBuilder->select($fields);
        }
        return $queryBuilder->get();
    }


    public function findRawByClientAndEmail(Client $client, string $email, array $fields): Collection
    {
        $hash = LeadContactEmail::buildHash($email);
        return DB::table('LeadsContactsEmails')
            ->select($fields)
            ->where('hash', $hash)
            ->whereNull('deleted_at')
            ->where('client_id', $client->id)
            ->get()
        ;
    }


    public function findOneByLeadAndEmail(Lead $lead, string $email): ?LeadContactEmail
    {
        $hash = LeadContactEmail::buildHash($email);
        return LeadContactEmail::where('lead_id', $lead->id)->where('hash', $hash)->first();
    }


    public function findOtherFromSameClient(LeadContactEmail $leadContactEmail): ?LeadContactEmail
    {
        $hash = LeadContactEmail::buildHash($leadContactEmail->email);
        return LeadContactEmail::where('client_id', $leadContactEmail->client_id)
            ->where('hash', $hash)
            ->where('id', '!=', $leadContactEmail->id)
            ->first()
        ;
    }


    public function findByClientAndLeadIds(Client $client, Collection $leadIds): Collection
    {
        $queryBuilder = LeadContactEmail::where('client_id', $client->id);
        $results = $this->chunkQuery($queryBuilder, $leadIds, 'lead_id', 200);
        return $results;
    }


    public function findByClientAndIds(Client $client, Collection $ids, array $opts = []): Collection
    {
        $queryBuilder = LeadContactEmail::where('client_id', $client->id);
        
        $relationshipsToEagerLoad = $opts['with'] ?? [];
        if ($relationshipsToEagerLoad) {
            $queryBuilder->with($relationshipsToEagerLoad);
        }

        $results = $this->chunkQuery($queryBuilder, $ids, 'id', 200);
        return $results;
    }


    public function findWithTrashedByContactAndEmail(LeadContact $leadContact, string $emailAddr)
    {
        return LeadContactEmail::withTrashed()->where([
            'lead_contact_id' => $leadContact->id,
            'hash' => LeadContactEmail::buildHash($emailAddr)
        ])->first();
    }


    public function findOneWithTrashedByLeadAndEmail(Lead $lead, string $emailAddr): ?LeadContactEmail
    {
        return LeadContactEmail::withTrashed()->where([
            'lead_id' => $lead->id,
            'hash' => LeadContactEmail::buildHash($emailAddr)
        ])->first();
    }


    public function countRepeatedEmailsInOtherLeads(Lead $lead): int
    {
        $hashes = $lead->leadContactEmails->map(function ($leadContactEmail) {
            return LeadContactEmail::buildHash($leadContactEmail->email);
        });
        return LeadContactEmail::where('client_id', $lead->client_id)
            ->whereIn('hash', $hashes)
            ->where('lead_id', '!=', $lead->id)
            ->count()
        ;
    }


    public function create(array $data): LeadContactEmail
    {
        $data['email'] = trim(strtolower($data['email']));
        $data['hash'] = LeadContactEmail::buildHash($data['email']);
        $leadContactEmail = new LeadContactEmail($data);
        $leadContactEmail->saveOrFail();
        return $leadContactEmail->fresh();
    }


    public function update(LeadContactEmail $leadContactEmail, array $data): LeadContactEmail
    {
        if (isset($data['email'])) {
            $data['email'] = trim(strtolower($data['email']));
            $data['hash'] = LeadContactEmail::buildHash($data['email']);
        }
        $leadContactEmail->fill($data);
        $leadContactEmail->save();
        return $leadContactEmail->fresh();
    }


    public function updateMultiple(Collection $leadContactEmails, array $fieldsToUpdate): int
    {
        $ids = $leadContactEmails->pluck('id');
        $updatedCount = LeadContactEmail::whereIn('id', $ids)->update($fieldsToUpdate);
        return $updatedCount;
    }


    public function updateValidAndSubscribedStatusByEmail(
        Client $client,
        string $email,
        bool $isValid,
        bool $isSubscribed
    ): int {
        $updatedCount = LeadContactEmail::where('client_id', $client->id)
            ->where('hash', LeadContactEmail::buildHash($email))
            ->update(['is_valid' => $isValid, 'unsubscribed' => !$isSubscribed])
        ;
        return $updatedCount;
    }


    // $emailsData: Collection de ['email' => string, 'isValid' => bool, 'isSubscribed' => bool]
    public function updateMultipleValidAndSubscribedStatus(Client $client, Collection $emailsData): int
    {
        if ($emailsData->isEmpty()) {
            return 0;
        }
        $totalUpdated = 0;
        foreach ($emailsData as $row) {
            $email = trim(strtolower($row['email']));
            $isValid = (bool) $row['isValid'];
            $isSubscribed = (bool) $row['isSubscribed'];
            $totalUpdated += $this->updateValidAndSubscribedStatusByEmail($client, $email, $isValid, $isSubscribed);
        }
        return $totalUpdated;
    }


    public function delete(LeadContactEmail $leadContactEmail)
    {
        $leadContactEmail->order = 0;
        $leadContactEmail->lead_ids_where_repeated = null;
        $leadContactEmail->save();
        $leadContactEmail->delete();
        return $leadContactEmail->fresh();
    }


    public function bulkInsert(Collection $leadContactEmailAttrs): bool
    {
        $result = LeadContactEmail::insert($leadContactEmailAttrs->toArray());
        return $result;
    }


    public function findTrashedByLead(Lead $lead): Collection
    {
        $result = LeadContactEmail::onlyTrashed()->where('lead_id', $lead->id)->get();
        return $result;
    }

}
