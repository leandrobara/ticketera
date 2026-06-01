<?php

namespace App\Models\MongoDB;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class ClientUsageLog extends Model
{

    use SoftDeletes;

    protected $guarded = [];
    public $timestamps = true;
    protected $connection = 'mongodb_logs';
    protected $table = 'client-usage-log';

    protected $casts = [
        'user_id' => 'int',
        'client_id' => 'int',
        // 'data' => 'embedded',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

}
