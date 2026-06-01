<?php

namespace App\Repositories;

use App\Models\Client;
use App\Models\ClientSettings;


class ClientSettingsRepository
{

    public function update(ClientSettings $clientSettings, array $data): ClientSettings
    {
        $clientSettings->fill($data);
        $clientSettings->saveOrFail();
        return $clientSettings->fresh();
    }

}
