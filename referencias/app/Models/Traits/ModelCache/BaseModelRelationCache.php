<?php

namespace App\Models\Traits\ModelCache;

use Exception;
use Illuminate\Support\Str;
use App\Helpers\RedisHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


trait BaseModelRelationCache
{

    public function getModelRelationFromCache(
        string $relationAttribute,
        string $relationModelName,
        ?int $relationModelId,
        ?int $clientId = null
    ): ?Model {
        // Sólo permito para relaciones de un solo modelo (no colecciones).
        $this->validateSingleModelRelation($relationAttribute);

        // Si el ID a relacionar con otro modelo es null, entonces el modelo resultante es también null.
        if (!$relationModelId) {
            return null;
        }

        // Solo es válido para modelos que tengan client_id.
        $clientId = $this->client_id ?? $clientId ?? null;
        $redisHelper = resolve(RedisHelper::class)->setClientId($clientId);
        if ($redisHelper->redisIsDown()) {
            return $this->getRelationValue($relationAttribute);
        }

        if ($this->relationLoaded($relationAttribute)) {
            // dump('relationLoaded');
            return $this->getRelationValue($relationAttribute);
        }

        $redisKey = "{$relationModelName}:Model-{$relationModelId}";
        $model = $redisHelper->get($redisKey);
        if ($model) {
            $this->setRelation($relationAttribute, $model);
            return $model;
        }

        $model = $this->getRelationValue($relationAttribute);
        // dump('elementFromDB');
        if ($model) {
            $redisHelper->store($redisKey, $model);
            $this->setRelation($relationAttribute, $model);
        }
        return $model;
    }


    public function validateSingleModelRelation(string $relationAttribute): void
    {
        if (!method_exists($this, $relationAttribute)) {
            throw new Exception("ModelCache trait | {$relationAttribute} relation does not exist as method");
        }
        $methodResponse = $this->$relationAttribute();
        if (!is_object($methodResponse)) {
            throw new Exception("ModelCache trait | {$relationAttribute} relation is not a valid method");
        }
        $relationClassName = get_class($methodResponse);
        if (!Str::contains($relationClassName, 'BelongsTo') && !Str::contains($relationClassName, 'HasOne')) {
            throw new Exception("ModelCache trait | {$relationAttribute} relation is not a valid relation object");
        }
    }

}
