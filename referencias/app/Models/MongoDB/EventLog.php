<?php

namespace App\Models\MongoDB;

use App\Helpers\StringHelper;
use MongoDB\Laravel\Eloquent\Model;


class EventLog extends Model
{

    protected $guarded = [];
    public $timestamps = false;
    protected $table = 'logs';
    protected $connection = 'mongodb_logs';

    protected $casts = [
        'user_id' => 'int',
        'hash' => 'string',
        'event' => 'string',
        'system' => 'string',
        'createdAtTs' => 'int',
        'log.client_id' => 'int',
        'createdAt' => 'datetime',
        // 'log' => 'array', // Si NO casteo esto, se guarda como array y viene como array (si no, funciona mal).
    ];


    public static function buildHash(string $system, string $event, array $logData): string
    {
        $secret = config('app.eventLogs.secret');
        $convertedLogData = resolve(StringHelper::class)->convertArrayFieldsToString($logData);
        return md5($secret . $system . $event . serialize($convertedLogData));
    }

}
