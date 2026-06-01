<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Notification extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'Notifications';

    const TYPE_USER_WAPI_NOT_SYNCED = 'user_wapi_not_synced';
    const TYPE_USER_EMAIL_SENDING_NOT_ENABLED = 'user_email_sending_not_enabled';
    const TYPE_USER_WHATSAPP_META_API_NOT_SYNCED = 'user_whatsapp_meta_api_not_synced';

    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'integer',
            'type' => 'string',
            'user_id' => 'integer',
            'lead_id' => 'integer',
            'client_id' => 'integer',
            'deleted_by_system' => 'boolean',
            'automation_log_id' => 'integer',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format')
        ];
        parent::__construct($attributes);
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }


}
