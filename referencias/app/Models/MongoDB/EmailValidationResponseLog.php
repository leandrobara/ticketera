<?php

namespace App\Models\MongoDB;

use App\Helpers\StringHelper;
use MongoDB\Laravel\Eloquent\Model;


class EmailValidationResponseLog extends Model
{

    protected $guarded = [];
    public $timestamps = false;
    protected $table = 'logs';
    protected $connection = 'mongodb_email_validation_reponses';

    protected $casts = [
        'hash' => 'string',
        'event' => 'string',
        'system' => 'string',
        'createdAtTs' => 'int',
        'createdAt' => 'datetime',
    ];


    public static function buildHash(string $system, string $event, array $logData): string
    {
        $secret = config('app.eventLogs.secret');
        $convertedLogData = resolve(StringHelper::class)->convertArrayFieldsToString($logData);
        return md5($secret . $system . $event . serialize($convertedLogData));
    }

}
