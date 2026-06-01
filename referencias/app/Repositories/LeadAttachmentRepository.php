<?php

namespace App\Repositories;

use App\Models\Lead;
use App\Models\Client;
use App\Models\LeadAttachment;
use Illuminate\Database\Eloquent\Collection;


class LeadAttachmentRepository
{

    public function save(array $leadAttachmentData): LeadAttachment
    {
        $leadAttachment = new LeadAttachment($leadAttachmentData);
        $leadAttachment->save();
        return $leadAttachment->fresh();
    }


    public function findAllByLead(Lead $lead): Collection
    {
        return LeadAttachment::query()
            ->where('lead_id', $lead->id)
            ->where('client_id', $lead->client->id)
            ->get()
        ;
    }


    public function findOneByClientAndFileHash(Client $client, string $hash, array $opts = []): ?LeadAttachment
    {
        $withTrashed = $opts['withTrashed'] ?? null;
        $builder = LeadAttachment::where('client_id', $client->id)->where('hash', $hash);
        
        if ($withTrashed) {
            $builder->withTrashed();
        }

        return $builder->first();
    }


    public function findOneByLeadAndFileHash(Lead $lead, string $hash): ?LeadAttachment
    {
        return LeadAttachment::query()
            ->where('lead_id', $lead->id)
            ->where('hash', $hash)
            ->first()
        ;
    }


    public function findOneByLeadAndFileName(Lead $lead, string $fileName): ?LeadAttachment
    {
        return LeadAttachment::query()
            ->where('lead_id', $lead->id)
            ->where('original_filename', $fileName)
            ->first()
        ;
    }


    public function delete(LeadAttachment $leadAttachment): LeadAttachment
    {
        $leadAttachment->delete();
        return $leadAttachment->fresh();
    }

}
