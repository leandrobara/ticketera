<?php

namespace App\Models\MongoDB;

use App\Helpers\StringHelper;
use MongoDB\Laravel\Eloquent\Model;


//
// @DEPRECATED 29/04/2025, borrar cuando pueda
//
class MondayChurnBoardClient extends Model
{

    protected $guarded = [];
    public $timestamps = false;
    protected $table = 'churn_board';
    protected $connection = 'mongodb_monday';

    protected $casts = [];


    public function getName(): string
    {
        return $this->formattedValues['name']
            ? $this->formattedValues['name']
            : $this->formattedValues['alternativeName']
        ;
    }

    public function getAlternativeName(): ?string
    {
        return $this->formattedValues['alternativeName'] ? $this->formattedValues['alternativeName'] : null;
    }


    public function buildHash(): string
    {
        $secret = config('app.eventLogs.secret');
        $attributes = $this->attributes;
        unset($attributes['_id']);
        unset($attributes['hash']);
        $convertedAttributesData = resolve(StringHelper::class)->convertArrayFieldsToString($attributes);
        return md5($secret . serialize($convertedAttributesData));
    }

}
