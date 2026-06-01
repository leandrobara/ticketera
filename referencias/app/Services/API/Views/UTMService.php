<?php

namespace App\Services\API\Views;

use Exception;
use Throwable;
use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Repositories\LeadRepository;
use App\Services\Traits\GetClientFromRequest;


class UTMService
{

    use GetClientFromRequest;


    public function __construct(
        protected readonly LeadRepository $leadRepository,
    ) {
    }


    public function findAllUTMCampaigns(Client $client): Collection
    {
        $utmCampaigns = $this->leadRepository->findAllUTMCampaigns($client);
        $utmCampaigns = $utmCampaigns->filter(fn ($c) => $c)->values();
        return $utmCampaigns;
    }

}
