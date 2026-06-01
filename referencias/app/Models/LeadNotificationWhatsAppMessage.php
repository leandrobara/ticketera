<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class LeadNotificationWhatsAppMessage extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'LeadsNotificationWhatsAppMessages';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'reason' => 'string',
            'message' => 'string',
            'lead_id' => 'integer',
            'success' => 'boolean',
            'exception' => 'string',
            'client_id' => 'integer',
            'is_grouped' => 'boolean',
            'do_not_send' => 'boolean',
            'automation_new_lead_id' => 'integer',
            'send_date' => 'datetime:' . config('app.datetime_format'),
            'sent_date' => 'datetime:' . config('app.datetime_format'),
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


    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }


    public function automationNewLead()
    {
        return $this->belongsTo(AutomationNewLead::class, 'automation_new_lead_id');
    }

}
