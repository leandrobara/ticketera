<?php

namespace App\Models;

use Illuminate\Support\Collection;
use App\Models\Interfaces\Automation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\ModelCache\BaseModelRelationCache;
use App\Models\Traits\ModelCache\ClientModelRelationCache;


class AutomationNewLead extends Model implements Automation
{

    use SoftDeletes, BaseModelRelationCache, ClientModelRelationCache, HasFactory;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'AutomationsNewLead';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'integer',
            'client_id' => 'integer',
            'add_tags_ids' => 'array',
            'add_new_note' => 'boolean',
            'new_note_text' => 'string',
            'add_new_task' => 'boolean',
            'assign_user_ids' => 'array',
            'new_task_title' => 'string',
            'assign_quality' => 'integer',
            'application_order' => 'integer',
            'grouped_email_body' => 'string',
            'do_not_send_email' => 'boolean',
            'send_grouped_email' => 'boolean',
            'new_task_description' => 'string',
            'triggering_lead_type' => 'string',
            'grouped_email_subject' => 'string',
            'triggering_landing_ids' => 'array',
            'new_task_days_to_expire' => 'integer',
            'auto_reply_send_min_hour' => 'string',
            'auto_reply_send_max_hour' => 'string',
            'add_acquisition_channel_id' => 'integer',
            'trigger_if_email_repeatead' => 'boolean',
            'trigger_if_phone_repeatead' => 'boolean',
            'grouped_whatsapp_message_text' => 'string',
            'auto_reply_email_template_id' => 'integer',
            'do_not_send_whatsapp_message' => 'boolean',
            'send_grouped_whatsapp_message' => 'boolean',
            'auto_reply_do_not_send_out_of_hour' => 'boolean',
            'auto_reply_ask_phone_email_template_id' => 'integer',
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


    public function automationLog()
    {
        return $this->hasOne(AutomationLog::class, 'automation_new_lead_id');
    }


    public function formFieldsToMatch()
    {
        return $this->hasMany(AutomationNewLeadFormField::class, 'automation_new_lead_id');
    }


    public function utmParametersToMatch()
    {
        return $this->hasMany(AutomationNewLeadUtmParameter::class, 'automation_new_lead_id');
    }


    public function trackingParametersToMatch()
    {
        return $this->hasMany(AutomationNewLeadTrackingParameter::class, 'automation_new_lead_id');
    }


    public function leadCustomFieldsMapping()
    {
        return $this->hasMany(AutomationNewLeadCustomFieldMapping::class);
    }


    public function leadCustomFieldsMatch()
    {
        return $this->hasMany(AutomationNewLeadCustomFieldMatch::class, 'automation_new_lead_id');
    }


    public function statusToAssign()
    {
        return $this->belongsTo(Status::class, 'status_id_to_assign');
    }


    public function getTriggeringLandingsAttribute(): Collection
    {
        $landingIds = $this->triggering_landing_ids;
        if (!$landingIds) {
            return collect([]);
        }
        $landings = Landing::whereIn('id', $landingIds)->get();
        return $landings;
    }


    public function getTagsToAddAttribute(?array $opts = []): Collection
    {
        $tagIds = $this->add_tags_ids;
        if (!$tagIds) {
            return collect([]);
        }
        $query = Tag::whereIn('id', $tagIds);
        if ($opts['withTrashed'] ?? false) {
            $query->withTrashed();
        }
        $tags = $query->get();
        return $tags;
    }


    public function getUsersToAssignAttribute(?array $opts = []): Collection
    {
        $userIds = $this->assign_user_ids;
        if (!$userIds) {
            return collect([]);
        }
        $query = User::whereIn('id', $userIds);
        if ($opts['withTrashed'] ?? false) {
            $query->withTrashed();
        }
        $users = $query->get();
        return $users;
    }


    public function acquisitionChannelToAdd()
    {
        return $this->belongsTo(AcquisitionChannel::class, 'add_acquisition_channel_id');
    }


    public function askPhoneEmailTemplate()
    {
        return $this->belongsTo(EmailTemplate::class, 'auto_reply_ask_phone_email_template_id');
    }


    public function autoReplyEmailTemplate()
    {
        return $this->belongsTo(EmailTemplate::class, 'auto_reply_email_template_id');
    }

}
