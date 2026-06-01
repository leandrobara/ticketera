<?php

namespace App\Repositories;

use Exception;
use App\Models\Client;
use App\Models\TemplateCategory;
use App\Exceptions\DatabaseException;


class TemplateCategoryRepository
{

    public function find(int $id)
    {
        return TemplateCategory::find($id);
    }


    public function findAllByClient(Client $client)
    {
        return TemplateCategory::where('client_id', $client->id)->orderByDesc('id')->get();
    }


    public function create(array $data): TemplateCategory
    {
        $data['hash'] = TemplateCategory::buildHash($data['name']);
        $templateCategory = new TemplateCategory($data);
        $templateCategory->saveOrFail();
        return $templateCategory->fresh();
    }


    public function update(TemplateCategory $templateCategory, array $data): TemplateCategory
    {
        if (isset($data['name'])) {
            $data['hash'] = TemplateCategory::buildHash($data['name']);
        }
        $templateCategory->fill($data);
        $templateCategory->saveOrFail();
        return $templateCategory->fresh();
    }
    

    public function delete(TemplateCategory $templateCategory): TemplateCategory
    {
        $templateCategory->delete();
        return $templateCategory->fresh();
    }
}
