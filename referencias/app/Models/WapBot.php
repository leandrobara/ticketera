<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\MongoDB\WapBot\WapBotConversation;
use App\Models\Traits\ModelCache\BaseModelRelationCache;
use App\Models\Traits\ModelCache\ClientModelRelationCache;


class WapBot extends Model
{

    use SoftDeletes, BaseModelRelationCache, ClientModelRelationCache;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'WapBots';
    protected $connection = 'mysql';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'prompt' => 'string',
            'client_id' => 'int',
            'enabled' => 'boolean',
            'prompt_model_params' => 'array',
            'followup_1_message' => 'string',
            'meta_phone_number_id' => 'string',
            'followup_1_delay_minutes' => 'int',
            'reactivation_interval_days' => 'int',
            'auto_create_lead_after_minutes' => 'int',
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


    public function whatsAppMetaAPIConnection()
    {
        return $this->belongsTo(WhatsAppMetaAPIConnection::class, 'meta_phone_number_id', 'phone_number_id');
    }


    public function getSeedConversationsCountAttribute()
    {
        return WapBotConversation::where('type', WapBotConversation::TYPE_HISTORY_SEED)
            ->where('clientId', $this->client_id)
            ->where('botMetaPhoneNumberId', $this->meta_phone_number_id)
            ->count()
        ;
    }

}