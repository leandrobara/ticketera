<?php

namespace App\Models\MongoDB;

use App\Helpers\StringHelper;
use MongoDB\Laravel\Eloquent\Model;


//
// @deprecated - 13 Marzo 2024
// NO se usa más. Nunca se usaron estos logs y ocupan mucho
//
class WAPIMessageLog extends Model
{

    protected $guarded = [];
    public $timestamps = false;
    protected $table = 'logs';
    protected $connection = 'mongodb_wapi_log';

    protected $casts = [
        'createdAtTs' => 'int',
        'createdAt' => 'datetime',
    ];

}
