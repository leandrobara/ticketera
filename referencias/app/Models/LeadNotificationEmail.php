<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class LeadNotificationEmail extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'LeadsNotificationEmails';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'reason' => 'string',
            'lead_id' => 'integer',
            'client_id' => 'integer',
            'is_grouped' => 'boolean',
            'do_not_send' => 'boolean',
            'grouped_subject' => 'string',
            'grouped_body_part' => 'string',
            'external_email_id' => 'integer', // QuickEmail
            'automation_new_lead_id' => 'integer',
            'send_date' => 'datetime:' . config('app.datetime_format'),
            'sent_date' => 'datetime:' . config('app.datetime_format'),
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format'),
            'scheduled_date' => 'datetime:' . config('app.datetime_format'),
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


    public function automationNewLead()
    {
        return $this->belongsTo(AutomationNewLead::class, 'automation_new_lead_id');
    }

}
