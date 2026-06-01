<?php

namespace App\Services\Traits;

use App\Exceptions\Services\Traits\GetClientFromRequestTraitException;
use App\Models\Client;

trait GetClientFromRequest
{

    private $client = null;


    protected function getClient(): Client
    {
        if ($this->client) {
            return $this->client;
        }

        $client = request()->input('client');
        if ($client) {
            $this->setClient($client);
            return $this->client;
        }
        throw new GetClientFromRequestTraitException(
            'GetClientFromRequest::getClient | Client not found in request'
        );
    }


    protected function getRequestClientOrNull(): ?Client
    {
        if (!request()->has('client')) {
            return null;
        }
        $client = request()->input('client');
        $this->setClient($client);
        return $this->client;
    }


    public function setClient(Client $client)
    {
        $this->client = $client;
        return $this;
    }

}
