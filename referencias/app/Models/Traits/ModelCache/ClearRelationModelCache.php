<?php

namespace App\Models\Traits\ModelCache;

use Exception;
use Illuminate\Support\Str;
use App\Helpers\RedisHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


trait ClearRelationModelCache
{

    public function clearRelationModelCache(?int $clientId = null): bool
    {
        $clientId = $this->client_id ?? $clientId ?? null;
        $redisHelper = resolve(RedisHelper::class)->setClientId($clientId);
        if ($redisHelper->redisIsDown()) {
            return true;
        }

        $modelName = get_class($this);
        $modelName = Str::afterLast($modelName, '\\');
        $redisKey = "{$modelName}:Model-{$this->id}";
        return $redisHelper->deleteByKeyMatching($redisKey, $clientId);
    }

}
