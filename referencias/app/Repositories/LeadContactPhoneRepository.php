<?php

namespace App\Repositories;

use Exception;
use App\Models\Lead;
use App\Models\Client;
use App\Models\LeadContact;
use App\Models\LeadContactPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;
use Illuminate\Database\QueryException;
use App\Repositories\Traits\ChunkedQueries;


class LeadContactPhoneRepository
{

    use ChunkedQueries;


    public function findOneByClientAndPhone(Client $client, string $phone): ?LeadContactPhone
    {
        $hash = LeadContactPhone::buildHash($phone);
        return LeadContactPhone::where('client_id', $client->id)->where('hash', $hash)->first();
    }


    public function findOneByLeadAndPhone(Lead $lead, string $phone): ?LeadContactPhone
    {
        $hash = LeadContactPhone::buildHash($phone);
        return LeadContactPhone::where('lead_id', $lead->id)->where('hash', $hash)->first();
    }


    public function create(array $data, LeadContact $leadContact): LeadContactPhone
    {
        $data['hash'] = LeadContactPhone::buildHash($data['phone']);
        $leadContactPhone = new LeadContactPhone($data);
        if ($data['phone']) {
            $leadContactPhone->normalized_phone = $leadContactPhone->getWhatsAppFormattedPhone(
                $leadContact->client->country_code, $leadContact->client->clientSettings
            );
            $leadContactPhone->normalized_hash = LeadContactPhone::buildNormalizedHash(
                $leadContactPhone->normalized_phone
            );
        }
        $leadContactPhone->saveOrFail();
        return $leadContactPhone->fresh();
    }


    public function update(LeadContactPhone $leadContactPhone, array $data): LeadContactPhone
    {
        if (isset($data['phone'])) {
            $data['hash'] = LeadContactPhone::buildHash($data['phone']);
        }
        $leadContactPhone->fill($data);
        if (isset($data['phone'])) {
            $leadContactPhone->normalized_phone = $leadContactPhone->getWhatsAppFormattedPhone(
                $leadContactPhone->client->country_code, $leadContactPhone->client->clientSettings
            );
            $leadContactPhone->normalized_hash = LeadContactPhone::buildNormalizedHash(
                $leadContactPhone->normalized_phone
            );
        }
        $leadContactPhone->save();
        return $leadContactPhone->fresh();
    }


    public function updateMultiple(Collection $leadContactPhones, array $fieldsToUpdate): int
    {
        $ids = $leadContactPhones->pluck('id');
        $updatedCount = LeadContactPhone::whereIn('id', $ids)->update($fieldsToUpdate);
        return $updatedCount;
    }


    public function delete(LeadContactPhone $leadContactPhone)
    {
        $leadContactPhone->order = 0;
        $leadContactPhone->lead_ids_where_repeated = null;
        $leadContactPhone->save();
        $leadContactPhone->delete();
        return $leadContactPhone->fresh();
    }


    public function findWithTrashedByContactAndPhone(LeadContact $leadContact, string $phone)
    {
        return LeadContactPhone::withTrashed()->where([
            'lead_contact_id' => $leadContact->id,
            'hash' => LeadContactPhone::buildHash($phone)
        ])->first();
    }


    public function findOneWithTrashedByLeadAndPhone(Lead $lead, string $phone)
    {
        return LeadContactPhone::withTrashed()->where([
            'lead_id' => $lead->id,
            'hash' => LeadContactPhone::buildHash($phone)
        ])->first();
    }


    public function findByClientAndLeadIds(Client $client, Collection $leadIds): Collection
    {
        $queryBuilder = LeadContactPhone::where('client_id', $client->id);
        $results = $this->chunkQuery($queryBuilder, $leadIds, 'lead_id', 200);
        return $results;
    }


    public function findByClientAndIds(Client $client, Collection $ids, array $opts = []): Collection
    {
        $queryBuilder = LeadContactPhone::where('client_id', $client->id);
        
        $relationshipsToEagerLoad = $opts['with'] ?? [];
        if ($relationshipsToEagerLoad) {
            $queryBuilder->with($relationshipsToEagerLoad);
        }

        $results = $this->chunkQuery($queryBuilder, $ids, 'id', 200);
        return $results;
    }


    public function findRawByClientAndPhone(Client $client, string $phone, array $fields): Collection
    {
        $hash = LeadContactPhone::buildHash($phone);
        return DB::table('LeadsContactsPhones')
            ->select($fields)
            ->where('hash', $hash)
            ->whereNull('deleted_at')
            ->where('client_id', $client->id)
            ->get()
        ;
    }


    public function countRepeatedPhonesInOtherLeads(Lead $lead): int
    {
        $hashes = $lead->leadContactPhones->map(function ($leadContactPhone) {
            return LeadContactPhone::buildHash($leadContactPhone->phone);
        });
        return LeadContactPhone::where('client_id', $lead->client_id)
            ->whereIn('hash', $hashes)
            ->where('lead_id', '!=', $lead->id)
            ->count()
        ;
    }


    public function findTrashedByLead(Lead $lead): Collection
    {
        $result = LeadContactPhone::onlyTrashed()->where('lead_id', $lead->id)->get();
        return $result;
    }


    /**
     * Devuelve los normalized_phone (formato WhatsApp) de prospectos que cumplen
     * los filtros dados. Usado para restringir conversaciones de WhatsApp.
     */
    public function findNormalizedPhonesByLeadFilters(Client $client, array $leadFilters): array
    {
        $leadQuery = Lead::where('client_id', $client->id);
        
        if ($leadFilters['status_id'] ?? []) {
            $leadQuery->whereIn('status_id', $leadFilters['status_id']);
        }
        if ($leadFilters['tag_id'] ?? []) {
            // Lookup directa por tag_id en vez de subquery correlacionada (evita evaluar por cada lead)
            $leadIdsByTag = DB::table('Leads_Tags')->whereIn('tag_id', $leadFilters['tag_id'])->pluck('lead_id');
            if ($leadIdsByTag->isEmpty()) {
                return [];
            }
            $leadQuery->whereIn('id', $leadIdsByTag);
        }
        $leadIds = $leadQuery->pluck('id');
        if ($leadIds->isEmpty()) {
            return [];
        }

        // 2. Obtener normalized_phone en chunks para evitar queries pesados con whereIn masivos
        $phones = collect();
        foreach ($leadIds->chunk(200) as $chunk) {
            $chunkPhones = LeadContactPhone::where('client_id', $client->id)
                ->whereIn('lead_id', $chunk)
                ->pluck('normalized_phone')
            ;
            $phones = $phones->merge($chunkPhones);
        }
        return $phones->unique()->values()->toArray();
    }


    /**
     * Verifica si un número normalizado tiene al menos un prospecto asociado en el cliente.
     * Usa exists() para eficiencia (no carga datos, solo chequea índice).
     */
    public function normalizedLeadContactPhoneExists(Client $client, string $normalizedPhone): bool
    {
        return LeadContactPhone::where('client_id', $client->id)
            ->where('normalized_phone', $normalizedPhone)
            ->exists()
        ;
    }


    public function findNormalizedPhonesByLeadId(Client $client, int $leadId): array
    {
        return LeadContactPhone::where('client_id', $client->id)
            ->where('lead_id', $leadId)
            ->pluck('normalized_phone')
            ->unique()
            ->values()
            ->toArray()
        ;
    }

}
