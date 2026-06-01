<?php

namespace App\Models\MongoDB;

use App\Helpers\StringHelper;
use MongoDB\Laravel\Eloquent\Model;


class CalendlyScheduledEvent extends Model
{

    protected $guarded = [];
    public $timestamps = false;
    protected $table = 'scheduled_events';
    protected $connection = 'mongodb_calendly';

    protected $casts = [
        'uri' => 'string',
        'hash' => 'string',
        'createdAtTs' => 'int',
        'createdAt' => 'datetime',
        //'scheduledEvent' => 'array', // No castear, se guarda como arr y viene como arr, casteado funciona mal
    ];


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
