<?php

namespace App\Repositories;

use Exception;
use App\Models\LeadContact;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Exceptions\DatabaseException;


class LeadContactRepository
{

    public function create($data)
    {
        $leadContact = new LeadContact($data);
        $leadContact->saveOrFail();
        return $leadContact->fresh();
    }


    public function update(LeadContact $leadContact, array $data): LeadContact
    {
        $leadContact->fill($data);
        $leadContact->save();
        return $leadContact->fresh();
    }


    public function delete(LeadContact $leadContact): LeadContact
    {
        $leadContact->is_main = false;
        $leadContact->order = 0;
        $leadContact->save();
        $leadContact->delete();
        return $leadContact->fresh();
    }

}
