<?php

namespace App\Services\API;

use App\Models\Lead;
use App\Models\ProposalInfo;
use App\Services\Traits\GetUserFromRequest;
use App\Repositories\ProposalInfoRepository;
use App\Services\Traits\GetClientFromRequest;


class ProposalInfoService
{

    use GetClientFromRequest, GetUserFromRequest;

    private $proposalInfoRepository;


    public function __construct(ProposalInfoRepository $proposalInfoRepository)
    {
        $this->proposalInfoRepository = $proposalInfoRepository;
    }


    public function find(int $id)
    {
        return ProposalInfo::findOrFail($id);
    }


    public function create(Lead $lead, array $data): ProposalInfo
    {
        $data['lead_id'] = $lead->id;
        $data['email_ids'] = $data['email_ids'] ?? null;
        $data['user_id'] = $data['user_id'] ?? $this->getUser()->id;
        $data['client_id'] = $data['client_id'] ?? $this->getClient()->id;
        $data['whatsapp_sending_id'] = $data['whatsapp_sending_id'] ?? null;
        $data['whatsapp_sending_message_ids'] = $data['whatsapp_sending_message_ids'] ?? null;

        $proposalInfo = $this->proposalInfoRepository->create($data);
        return $proposalInfo->fresh();
    }


    public function update(ProposalInfo $proposalInfo, $data): ProposalInfo
    {
        return $this->proposalInfoRepository->update($proposalInfo, $data);
    }


    public function delete(ProposalInfo $proposalInfo): ProposalInfo
    {
        return $this->proposalInfoRepository->delete($proposalInfo);
    }


    public function findLastProposalFromLead(Lead $lead): ?ProposalInfo
    {
        return $this->proposalInfoRepository->findLastProposalFromLead($lead);
    }


    public function findLastProposalFromLeadId(int $leadId): ?ProposalInfo
    {
        return $this->proposalInfoRepository->findLastProposalFromLeadId($leadId);
    }


    public function findLastProposalFromLeadIdAndWhatsAppSendingId(int $leadId, int $whatsAppSendingId): ?ProposalInfo
    {
        return $this->proposalInfoRepository->findLastProposalFromLeadIdAndWhatsAppSendingId(
            $leadId, $whatsAppSendingId
        );
    }

}
