<?php

namespace App\Services\API\Import;

use Carbon\Carbon;
use App\Models\Lead;
use App\Models\Client;
use App\DTO\Import\LeadsLeadDTO;
use App\Services\API\LeadService;
use Illuminate\Support\Collection;
use App\Services\API\ClientService;
use App\DTO\Import\ImportedLeadDTO;
use Illuminate\Support\Facades\Http;


class ImportLeadService
{

    protected $leadService;
    protected $clientService;
    protected $leadsAPISecret;
    protected $leadsAPIEndpoint;


    public function __construct(
        string $leadsAPIEndpoint,
        string $leadsAPISecret,
        LeadService $leadService,
        ClientService $clientService
    ) {
        $this->leadService = $leadService;
        $this->clientService = $clientService;
        $this->leadsAPISecret = $leadsAPISecret;
        $this->leadsAPIEndpoint = $leadsAPIEndpoint;
    }


    public function getLeadsLeads(array $opts = []): Collection
    {
        $timestamp = Carbon::now()->setTimezone('America/Argentina/Buenos_Aires')->getTimestamp();
        $params = [
            'timestamp' => $timestamp,
            'only_clienty_crm' => true,
            'limit' => $opts['limit'] ?? 500,
            'offset' => $opts['offset'] ?? 0,
            'hash' => md5($this->leadsAPISecret . $timestamp),
        ];
        if ($opts['leads_lead_id'] ?? null) {
            $params['query_id'] = $opts['leads_lead_id'];
        }
        if ($opts['leads_leads_ids'] ?? null) {
            $params['ids'] = $opts['leads_leads_ids'];
        }
        if ($opts['leads_client_id'] ?? null) {
            $params['client_id'] = $opts['leads_client_id'];
        }
        $response = Http::asJson()
            ->timeout(90)
            ->withOptions(['verify' => false])
            ->get($this->leadsAPIEndpoint . '/queries/list_secure.php', $params)
        ;

        $leadsLeads = collect($response->json());
        $leadsLeads = $leadsLeads->map(function ($leadsLeadData) {
            return LeadsLeadDTO::buildFromLeadsLeadData($leadsLeadData);
        });
        return collect($leadsLeads);
    }


    public function importLeadsLead(LeadsLeadDTO $leadsLeadDTO): ImportedLeadDTO
    {
        $client = $this->findClient($leadsLeadDTO);
        if (!$client) {
            $importedLeadDTO = new ImportedLeadDTO(null, $leadsLeadDTO, ImportedLeadDTO::STATUS_NONEXISTENT_CLIENT);
            return $importedLeadDTO;
        }

        if (!$client->enabled) {
            $importedLeadDTO = new ImportedLeadDTO(
                null, $leadsLeadDTO, ImportedLeadDTO::STATUS_NON_ENABLED_CLIENT
            );
            return $importedLeadDTO;
        }

        if (!$client->enabled_to_receive_leads) {
            $importedLeadDTO = new ImportedLeadDTO(
                null, $leadsLeadDTO, ImportedLeadDTO::STATUS_NON_ENABLED_TO_RECEIVE_LEADS_CLIENT
            );
            return $importedLeadDTO;
        }

        if (!$leadsLeadDTO->hasContactInfo()) {
            $importedLeadDTO = new ImportedLeadDTO(
                null, $leadsLeadDTO, ImportedLeadDTO::STATUS_NONEXISTENT_CONTACT_INFO
            );
            return $importedLeadDTO;
        }

        $existentLead = $this->findExistentLead($leadsLeadDTO, $client);
        if ($existentLead) {
            $importedLeadDTO = new ImportedLeadDTO($existentLead, $leadsLeadDTO, ImportedLeadDTO::STATUS_EXISTENT);
            return $importedLeadDTO;
        }

        $lead = $this->leadService->persistLeadsImportedLead($client, $leadsLeadDTO);
        $importedLeadDTO = new ImportedLeadDTO($lead, $leadsLeadDTO, ImportedLeadDTO::STATUS_IMPORTED);
        return $importedLeadDTO;
    }


    public function findClient(LeadsLeadDTO $leadsLeadDTO): ?Client
    {
        $clientAttrs = $leadsLeadDTO->getClientAttrs();
        $client = $this->clientService->findClientByLeadsId($clientAttrs['leads_client_id']);
        return $client;
    }


    public function findExistentLead(LeadsLeadDTO $leadsLeadDTO, Client $client): ?Lead
    {
        $leadAttrs = $leadsLeadDTO->getLeadAttrs();
        $existentLead = $this->findExistentLeadByLeadsId($leadAttrs['leads_query_id'], ['withTrashed' => true]);
        if ($existentLead) {
            return $existentLead;
        }

        $mainLeadContactAttrs = $leadsLeadDTO->getMainLeadContactAttrs();
        $hash = Lead::buildHash($leadAttrs, $mainLeadContactAttrs);
        $existentLead = $this->findExistentLeadByClientAndHash($client, $hash);
        if (!$existentLead) {
            $deletedHash = Lead::buildDeletedHash($hash);
            $existentLead = $this->findExistentLeadByClientAndHash($client, $deletedHash, ['withTrashed' => true]);
        }
        return $existentLead;
    }


    protected function findExistentLeadByLeadsId(int $leadsLeadId, array $opts = []): ?Lead
    {
        return $this->leadService->findOneByLeadsId($leadsLeadId, $opts);
    }


    protected function findExistentLeadByClientAndHash(Client $client, string $hash, array $opts = []): ?Lead
    {
        return $this->leadService->findOneByClientAndHash($client, $hash, $opts);
    }

}
