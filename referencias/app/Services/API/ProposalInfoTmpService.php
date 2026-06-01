<?php

namespace App\Services\API;

use App\Models\Lead;
use App\Models\ProposalInfoTmp;
use App\Models\WhatsAppSending;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\DTO\WAPI\WAPINewSendingParametersDTO;
use App\Repositories\ProposalInfoTmpRepository;
use App\DTO\WAPSender\WAPSenderNewSendingParametersDTO;
use App\DTO\WhatsAppMetaAPI\WhatsAppMetaAPINewSendingParametersDTO;


class ProposalInfoTmpService
{

    use GetClientFromRequest, GetUserFromRequest;


    public function __construct(private readonly ProposalInfoTmpRepository $proposalInfoTmpRepository)
    {
    }


    public function find(int $id)
    {
        return ProposalInfoTmp::findOrFail($id);
    }


    public function findOneByWhatsAppSending(WhatsAppSending $wapSending): ?ProposalInfoTmp
    {
        return $this->proposalInfoTmpRepository->findOneByWhatsAppSending($wapSending);
    }


    public function createNewByWAPSendingAndDTO(
        WhatsAppSending $wapSending,
        WAPINewSendingParametersDTO | WAPSenderNewSendingParametersDTO | WhatsAppMetaAPINewSendingParametersDTO $dto,
    ): ProposalInfoTmp {
        if (!$wapSending->is_proposal) {
            throw new Exception('WhatsAppSending is not a proposal');
        }
        $data = [];
        $data['user_id'] = $wapSending->user_id;
        $data['amount'] = $dto->proposalAmount ?? 0;
        $data['client_id'] = $wapSending->client_id;
        $data['whatsapp_sending_id'] = $wapSending->id;
        $data['description'] = $dto->proposalDescription ?? null;
        $data['whatsapp_sending_message_ids'] = $wapSending->whatsAppSendingMessages->pluck('id');
        $proposalInfoTmp = $this->create($data);
        return $proposalInfoTmp;
    }


    public function create(array $data): ProposalInfoTmp
    {
        $proposalInfoTmp = $this->proposalInfoTmpRepository->create($data);
        return $proposalInfoTmp->fresh();
    }

}
