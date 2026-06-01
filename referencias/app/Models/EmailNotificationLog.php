<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class EmailNotificationLog extends Model
{

    use SoftDeletes;

    protected $table = 'EmailNotificationLogs';
    
    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];

    const OPEN_EVENT = 'open';
    const BOUNCE_EVENT = 'bounce';
    const COMPLAINT_EVENT = 'complaint';
    const UNSUBSCRIBE_EVENT = 'unsubscribe';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'event' => 'string',
            'email_id' => 'int',
            'client_id' => 'int',
            'affected_lead_contact_email_ids' => 'array',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format'),
            'reopened_proposal_email_notification_date' => 'datetime:' . config('app.datetime_format'),
            'reopened_proposal_browser_notification_date' => 'datetime:' . config('app.datetime_format'),
        ];
        parent::__construct($attributes);
    }


    public function email()
    {
        return $this->belongsTo(Email::class);
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function getAffectedLeadContactEmailsAttribute()
    {
        return LeadContactEmail::where('client_id', $this->client_id)
            ->whereIn('id', $this->affected_lead_contact_email_ids)
            ->get()
        ;
    }

}
