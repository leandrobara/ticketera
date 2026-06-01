<?php

namespace App\Repositories;

use Exception;
use App\Models\User;
use App\Models\Client;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;
use App\Repositories\Traits\VoidClearCache;


class WhatsAppTemplateRepository implements Repository
{

    use VoidClearCache;


    public function findById(int $id): ?WhatsAppTemplate
    {
        return WhatsAppTemplate::find($id);
    }


    public function findAllByClient(Client $client): Collection
    {
        return WhatsAppTemplate::where('client_id', $client->id)->get();
    }


    public function findMetaTemplatesByClientAndWabaId(Client $client, string $wabaId): Collection
    {
        return WhatsAppTemplate::where('client_id', $client->id)->where('waba_id', $wabaId)->get();
    }


    public function findMatchingTemplateForWaba(WhatsAppTemplate $wapTpl, string $targetWabaId): ?WhatsAppTemplate
    {
        if (!$wapTpl->meta_name) {
            return null;
        }
        return WhatsAppTemplate::where('client_id', $wapTpl->client_id)
            ->where('waba_id', $targetWabaId)
            ->where('meta_name', $wapTpl->meta_name)
            ->first()
        ;
    }


    public function create(array $data): WhatsAppTemplate
    {
        $emailTemplate = new WhatsAppTemplate($data);
        $emailTemplate->saveOrFail();
        return $emailTemplate->fresh();
    }


    public function update(WhatsAppTemplate $emailTemplate, array $data): WhatsAppTemplate
    {
        $emailTemplate->fill($data);
        $emailTemplate->saveOrFail();
        return $emailTemplate->fresh();
    }


    public function delete(WhatsAppTemplate $emailTemplate): WhatsAppTemplate
    {
        $emailTemplate->delete();
        return $emailTemplate->fresh();
    }

}
