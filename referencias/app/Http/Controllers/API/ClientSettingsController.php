<?php

namespace App\Http\Controllers\API;

use App\Services\API\ClientSettingsService;
use App\Http\Resources\ClientSettingsResource;
use App\Http\Requests\ClientSettings\UpdateClientSettingsRequest;
use App\Http\Requests\ClientSettings\GetClientSettingsRequest;


class ClientSettingsController extends BaseAPIController
{

    public function get(GetClientSettingsRequest $req)
    {
        $settings = resolve(ClientSettingsService::class)->getClientSettings();
        return $this->getSuccessResponse(
            (new ClientSettingsResource($settings))->loadOptionsFromRequest($req)
        );
    }


    public function update(UpdateClientSettingsRequest $req)
    {
        $service = resolve(ClientSettingsService::class);
        $settings = $service->update($service->getClientSettings(), $req->validatedAttributes());
        return $this->getSuccessResponse(
            (new ClientSettingsResource($settings))->loadOptionsFromRequest($req)
        );
    }

}
