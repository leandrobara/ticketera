<?php

namespace App\Repositories;

use Closure;
use DateTime;
use Exception;
use App\Models\Lead;
use App\Models\Client;
use App\Models\ProposalInfoTmp;
use App\Models\WhatsAppSending;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class ProposalInfoTmpRepository
{

    public function create($data): ProposalInfoTmp
    {
        $proposalInfoTmp = new ProposalInfoTmp($data);
        $proposalInfoTmp->saveOrFail();
        return $proposalInfoTmp->fresh();
    }


    public function findOneByWhatsAppSending(WhatsAppSending $wapSending): ?ProposalInfoTmp
    {
        $proposalInfoTmp = ProposalInfoTmp::where('whatsapp_sending_id', $wapSending->id)
            ->where('client_id', $wapSending->client_id)
            ->first()
        ;
        return $proposalInfoTmp;
    }

}
