<?php

namespace App\Models;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\ModelCache\BaseModelRelationCache;
use App\Models\Traits\ModelCache\ClientModelRelationCache;


class AutomationProposalModifyLeadAfterSendRule extends Model
{

    use SoftDeletes, BaseModelRelationCache, ClientModelRelationCache, HasFactory;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'AutomationsProposalModifyLeadAfterSendRule';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'integer',
            'client_id' => 'integer',
            'add_tags_ids' => 'array',
            'remove_tags_ids' => 'array',
            'deleted_at_ts' => 'timestamp',
            'assign_status_id' => 'integer',
            'automation_proposal_id' => 'integer',
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


    public function automationLogs()
    {
        return $this->hasMany(AutomationLog::class, 'automation_proposal_modify_lead_after_send_rule_id');
    }


    public function automationProposal()
    {
        return $this->belongsTo(AutomationProposal::class, 'automation_proposal_id');
    }


    public function statusToAssign()
    {
        return $this->belongsTo(Status::class, 'assign_status_id');
    }


    public function getTagsToAddAttribute(): Collection
    {
        $tagIds = $this->add_tags_ids;
        if (!$tagIds) {
            return collect([]);
        }
        $tags = Tag::whereIn('id', $tagIds)->get();

        return $tags;
    }


    public function getTagsToRemoveAttribute(): Collection
    {
        $tagIds = $this->remove_tags_ids;
        if (!$tagIds) {
            return collect([]);
        }
        $tags = Tag::whereIn('id', $tagIds)->get();

        return $tags;
    }

}
