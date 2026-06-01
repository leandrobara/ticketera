<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Venue;
use App\Services\Api\Admin\VenueService;
use App\Http\Requests\Admin\GetVenueRequest;
use App\Http\Requests\Admin\ListVenueRequest;
use App\Http\Requests\Admin\UpdateVenueRequest;
use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Admin\CreateVenueRequest;
use App\Http\Requests\Admin\DeleteVenueRequest;


class VenueController extends BaseAPIController
{
    public function list(ListVenueRequest $req): array
    {
        $venueList = resolve(VenueService::class)->list($req->validated());
        return $this->getSuccessResponse($venueList);
    }

    public function create(CreateVenueRequest $req): array
    {
        $venue = resolve(VenueService::class)->create($req->validated());
        return $this->getSuccessResponse($venue);
    }

    public function show(Venue $venue, GetVenueRequest $req): array
    {
        $venue = resolve(VenueService::class)->getOne($venue);
        return $this->getSuccessResponse($venue);
    }

    public function update(Venue $venue, UpdateVenueRequest $req): array
    {
        $venue = resolve(VenueService::class)->update($venue, $req->validated());
        return $this->getSuccessResponse($venue);
    }

    public function delete(Venue $venue, DeleteVenueRequest $req): array
    {
        resolve(VenueService::class)->delete($venue);
        return $this->getSuccessResponse($venue);
    }
}
