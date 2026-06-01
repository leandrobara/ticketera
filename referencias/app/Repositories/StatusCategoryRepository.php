<?php

namespace App\Repositories;

use Exception;
use App\Models\Client;
use App\Models\StatusCategory;
use App\Exceptions\DatabaseException;


class StatusCategoryRepository
{

    public function find(int $id)
    {
        return StatusCategory::find($id);
    }


    public function findAllByClient(Client $client)
    {
        return StatusCategory::where('client_id', $client->id)->orderBy('order')->get();
    }


    public function findMaxOrderByClient(Client $client)
    {
        return StatusCategory::where('is_irrelevant', false)->where('client_id', $client->id)->max('order');
    }


    public function create(array $data): StatusCategory
    {
        $data['hash'] = StatusCategory::buildHash($data['name']);
        $statusCategory = new StatusCategory($data);
        $statusCategory->saveOrFail();
        return $statusCategory->fresh();
    }


    public function update(StatusCategory $statusCategory, array $data): StatusCategory
    {
        if (isset($data['name'])) {
            $data['hash'] = StatusCategory::buildHash($data['name']);
        }
        $statusCategory->fill($data);
        $statusCategory->saveOrFail();
        return $statusCategory->fresh();
    }
    

    public function delete(StatusCategory $statusCategory): StatusCategory
    {
        // $statusCategory->tags()->update(['tag_status_id' => null]);
        $statusCategory->delete();
        return $statusCategory->fresh();
    }
}
