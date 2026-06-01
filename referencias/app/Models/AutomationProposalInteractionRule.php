<?php

namespace App\Models;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AutomationProposalInteractionRule extends Model
{

    use SoftDeletes, HasFactory;

    public $timestamps = true;
    protected $table = 'AutomationsProposalInteractionRule';
    protected $guarded = ['id'];
    protected $casts = [];


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'integer',
            'client_id' => 'integer',
            'add_tags_ids' => 'array',
            'trigger_type' => 'string',
            'remove_tags_ids' => 'array',
            'deleted_at_ts' => 'timestamp',
            'assign_status_id' => 'integer',
            'cancelling_tags_ids' => 'array',
            'cancelling_status_ids' => 'array',
            'automation_proposal_id' => 'integer',
            'send_notification_email_to_user' => 'boolean',
            'notify_only_if_lead_quality_is_gt' => 'integer',
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format')
        ];
        parent::__construct($attributes);
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function automationLogs()
    {
        return $this->hasMany(AutomationLog::class, 'automation_proposal_interaction_rule_id');
    }


    public function automationProposal()
    {
        return $this->belongsTo(AutomationProposal::class, 'automation_proposal_id');
    }


    public function statusToAssign()
    {
        return $this->belongsTo(Status::class, 'assign_status_id');
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


    public function getTagsToRemoveAttribute(?array $opts = []): Collection
    {
        $tagIds = $this->remove_tags_ids;
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


    public function getCancellingTagsAttribute(): Collection
    {
        $tagIds = $this->cancelling_tags_ids;
        if (!$tagIds) {
            return collect([]);
        }
        $tags = Tag::whereIn('id', $tagIds)->get();
        return $tags;
    }


    public function getCancellingStatusListAttribute(): Collection
    {
        $statusIds = $this->cancelling_status_ids;
        if (!$statusIds) {
            return collect([]);
        }
        $status = Status::whereIn('id', $statusIds)->get();
        return $status;
    }

}
