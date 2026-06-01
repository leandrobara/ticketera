<?php

namespace App\Models;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\ModelCache\BaseModelRelationCache;
use App\Models\Traits\ModelCache\ClientModelRelationCache;


class AutomationProposalResendRule extends Model
{

    use SoftDeletes, BaseModelRelationCache, ClientModelRelationCache, HasFactory;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'AutomationsProposalResendRule';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'integer',
            'enabled' => 'boolean',
            'send_hour' => 'string',
            'client_id' => 'integer',
            'deleted_at_ts' => 'timestamp',
            'send_delay_days' => 'integer',
            'cancelling_tags_ids' => 'array',
            'cancelling_enabled' => 'boolean',
            'cancelling_status_ids' => 'array',
            'send_email_template_id' => 'integer',
            'automation_proposal_id' => 'integer',
            'add_original_attachments' => 'boolean',
            'cancel_if_proposal_was_opened' => 'boolean',
            'cancel_if_proposal_was_already_sent' => 'boolean',
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


    public function automationLogs()
    {
        return $this->hasMany(AutomationLog::class, 'automation_proposal_resend_rule_id');
    }


    public function automationProposal()
    {
        return $this->belongsTo(AutomationProposal::class, 'automation_proposal_id');
    }


    public function sendEmailTemplate()
    {
        return $this->belongsTo(EmailTemplate::class, 'send_email_template_id');
    }


    public function getCancellingTagsAttribute(): Collection
    {
        $tagIds = $this->cancelling_tags_ids;
        if (!$tagIds) {
            return collect([]);
        }
        $query = Tag::whereIn('id', $tagIds);
        $tags = $query->get();
        return $tags;
    }


    public function getCancellingStatusListAttribute(): Collection
    {
        $statusIds = $this->cancelling_status_ids;
        if (!$statusIds) {
            return collect([]);
        }
        $query = Status::whereIn('id', $statusIds);
        $statusList = $query->get();
        return $statusList;
    }

}
