<?php

namespace App\Repositories;

use App\Models\Client;
use App\Models\WhatsAppAttachment;
use Illuminate\Database\Eloquent\Collection;


class WhatsAppAttachmentRepository implements Repository
{

    public function findOrFail(int $id): WhatsAppAttachment
    {
        return WhatsAppAttachment::findOrFail($id);
    }


    public function findExactOneByClientAndFile(Client $client, string $hash, array $opts = []): ?WhatsAppAttachment
    {
        $withTrashed = $opts['withTrashed'] ?? false;
        $builder = WhatsAppAttachment::where('client_id', $client->id)->where('hash', $hash);
        if ($withTrashed) {
            $builder->withTrashed();
        }
        return $builder->first();
    }


    public function findOneByClientAndHashAndFilename(
        Client $client,
        string $hash,
        string $filename,
        array $opts = []
    ): ?WhatsAppAttachment {
        $withTrashed = $opts['withTrashed'] ?? false;
        $builder = WhatsAppAttachment::where('client_id', $client->id)
            ->where('hash', $hash)
            ->where('original_filename', $filename)
        ;
        if ($withTrashed) {
            $builder->withTrashed();
        }
        return $builder->first();
    }


    public function delete(WhatsAppAttachment $wapAttachment): WhatsAppAttachment
    {
        $wapAttachment->delete();
        return $wapAttachment->fresh();
    }

    
    public function create(array $wapAttachmentData): WhatsAppAttachment
    {
        $wapAttachment = new WhatsAppAttachment($wapAttachmentData);
        $wapAttachment->saveOrFail();
        return $wapAttachment->fresh();
    }

}
