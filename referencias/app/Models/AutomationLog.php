<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\ModelCache\BaseModelRelationCache;
use App\Models\Traits\ModelCache\ClientModelRelationCache;


class AutomationLog extends Model
{

    use SoftDeletes, BaseModelRelationCache, ClientModelRelationCache;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'AutomationsLogs';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'integer',
            'lead_id' => 'integer',
            'email_id' => 'integer',
            'client_id' => 'integer',
            'event_log_ids' => 'array',
            'lead_sale_id' => 'integer',
            'automation_new_lead_id' => 'integer',
            'automation_proposal_id' => 'integer',
            'automation_email_send_id' => 'integer',
            'automation_email_send_step_id' => 'integer',
            'automation_new_lead_assigned_user_id' => 'integer',
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


    public function automationNewLeadAssignedUser()
    {
        return $this->belongsTo(User::class, 'automation_new_lead_assigned_user_id');
    }


    public function automationProposal()
    {
        return $this->belongsTo(AutomationProposal::class, 'automation_proposal_id');
    }


    public function automationNewLead()
    {
        return $this->belongsTo(AutomationNewLead::class, 'automation_new_lead_id');
    }


    public function automationEmailSend()
    {
        return $this->belongsTo(AutomationEmailSend::class, 'automation_email_send_id');
    }


    public function automationEmailSendStep()
    {
        return $this->belongsTo(AutomationEmailSendStep::class, 'automation_email_send_step_id');
    }

    public function automationTask()
    {
        return $this->belongsTo(AutomationTask::class, 'automation_task_id');
    }

    public function leadSale()
    {
        return $this->belongsTo(LeadSale::class, 'lead_sale_id');
    }

}
