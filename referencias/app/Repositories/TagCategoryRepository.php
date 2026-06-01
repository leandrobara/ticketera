<?php

namespace App\Repositories;

use Exception;
use App\Models\Client;
use App\Models\TagCategory;
use App\Exceptions\DatabaseException;


class TagCategoryRepository
{

    public function findAllByClient(Client $client)
    {
        return TagCategory::where('client_id', $client->id)->get();
    }


    public function create(array $data): TagCategory
    {
        $data['hash'] = TagCategory::buildHash($data['name']);
        $tagCategory = new TagCategory($data);
        $tagCategory->saveOrFail();
        return $tagCategory->fresh();
    }


    public function update(TagCategory $tagCategory, array $data): TagCategory
    {
        if (isset($data['name'])) {
            $data['hash'] = TagCategory::buildHash($data['name']);
        }
        $tagCategory->fill($data);
        $tagCategory->saveOrFail();
        return $tagCategory->fresh();
    }
    

    public function delete(TagCategory $tagCategory): TagCategory
    {
        $tagCategory->tags()->update(['tag_category_id' => null]);
        $tagCategory->delete();
        return $tagCategory->fresh();
    }
}
