<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\ModelCache\BaseModelRelationCache;
use App\Models\Traits\ModelCache\ClientModelRelationCache;


class WapSalesAgentBot extends Model
{

    use SoftDeletes, BaseModelRelationCache, ClientModelRelationCache;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $connection = 'mysql';
    protected $table = 'WapSalesAgentBots';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'user_id' => 'int',
            'client_id' => 'int',
            'is_enabled' => 'boolean',
            'is_log_enabled' => 'boolean',
            'meta_phone_number_id' => 'string',
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


    public function whatsAppMetaAPIConnection()
    {
        return $this->belongsTo(WhatsAppMetaAPIConnection::class, 'meta_phone_number_id', 'phone_number_id');
    }

}
