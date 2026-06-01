<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class GmailEmailNotification extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'GmailEmailsNotifications';

    const SENT_TYPE = 'sent';
    const RESPONSE_TYPE = 'response';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'type' => 'string',
            'user_id' => 'int',
            'client_id' => 'int',
            'gmail_id' => 'string',
            'email_subject' => 'string',
            'email_name_from' => 'string',
            'email_address_from' => 'string',
            'is_notification_viewed' => 'boolean',
            'email_sent_date' => 'datetime:' . config('app.datetime_format'),
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format'),
        ];
        parent::__construct($attributes);
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
