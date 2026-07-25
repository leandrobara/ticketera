<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Admin\CreatePersonRequest;
use App\Http\Requests\Admin\DeletePersonRequest;
use App\Http\Requests\Admin\FindPersonCandidatesRequest;
use App\Http\Requests\Admin\GetPersonRequest;
use App\Http\Requests\Admin\ListPersonRequest;
use App\Http\Requests\Admin\UpdatePersonRequest;
use App\Models\Person;
use App\Services\Api\Admin\PersonService;

class PersonController extends BaseAPIController
{
    public function list(ListPersonRequest $req): array
    {
        $people = resolve(PersonService::class)->list($req->validated());
        return $this->getSuccessResponse($people);
    }

    public function candidates(FindPersonCandidatesRequest $req): array
    {
        $people = resolve(PersonService::class)->findCandidates($req->validated());
        return $this->getSuccessResponse($people);
    }

    public function create(CreatePersonRequest $req): array
    {
        $person = resolve(PersonService::class)->create($req->validated());
        return $this->getSuccessResponse($person);
    }

    public function show(Person $person, GetPersonRequest $req): array
    {
        $person = resolve(PersonService::class)->getOne($person);
        return $this->getSuccessResponse($person);
    }

    public function update(Person $person, UpdatePersonRequest $req): array
    {
        $person = resolve(PersonService::class)->update($person, $req->validated());
        return $this->getSuccessResponse($person);
    }

    public function delete(Person $person, DeletePersonRequest $req): array
    {
        resolve(PersonService::class)->delete($person);
        return $this->getSuccessResponse($person);
    }
}
