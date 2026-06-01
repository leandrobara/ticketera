<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\ModelCache\BaseModelRelationCache;
use App\Models\Traits\ModelCache\ClientModelRelationCache;


class WhatsAppMetaAPIConnection extends Model
{

    use SoftDeletes, BaseModelRelationCache, ClientModelRelationCache;

    protected $casts = [];
    protected $hidden = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'WhatsAppMetaAPIConnections';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'user_id' => 'int',
            'client_id' => 'int',
            'waba_id' => 'string', // id de Meta
            'waba_name' => 'string',
            'access_token' => 'string',
            'phone_number' => 'string',
            'phone_number_id' => 'string', // id de Meta
            'phone_number_verified_name' => 'string',
            'access_token_last_generation_date' => 'datetime:' . config('app.datetime_format'),
            'access_token_expiration_date' => 'datetime:' . config('app.datetime_format'),
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format'),
        ];
        parent::__construct($attributes);
    }


    // Implementa cache a través de ClientModelRelationCache
    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function wapBot()
    {
        return $this->hasOne(WapBot::class, 'meta_phone_number_id', 'phone_number_id');
    }


    public function wapSalesAgentBot()
    {
        return $this->hasOne(WapSalesAgentBot::class, 'meta_phone_number_id', 'phone_number_id');
    }

}
