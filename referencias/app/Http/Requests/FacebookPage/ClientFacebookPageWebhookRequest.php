<?php

namespace App\Http\Requests\FacebookPage;

use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;
use App\DTO\FacebookPage\ClientFacebookPageLeadGenDTO;
use App\Services\API\Dispatchers\FacebookLogDispatcherService;


class ClientFacebookPageWebhookRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }


    public function validatedDTO(): Collection
    {
        $message = request()->all();
        resolve(FacebookLogDispatcherService::class)->logLeadGenDataReceived($message);

        $leads = new Collection();
        if ($message['object'] === 'page') {
            foreach ($message['entry'] as $entry) {
                foreach ($entry['changes'] as $changes) {
                    if ($changes['field'] == 'leadgen') {
                        $leads->add(ClientFacebookPageLeadGenDTO::build($changes['value']));
                    }
                }
            }
        }
        return $leads;
    }

}
