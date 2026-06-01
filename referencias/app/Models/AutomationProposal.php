<?php

namespace App\Models;

use App\Models\Interfaces\Automation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\ModelCache\BaseModelRelationCache;
use App\Models\Traits\ModelCache\ClientModelRelationCache;


class AutomationProposal extends Model implements Automation
{

    use SoftDeletes, BaseModelRelationCache, ClientModelRelationCache, HasFactory;


    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'AutomationsProposal';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'integer',
            'enabled' => 'boolean',
            'client_id' => 'integer',
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
        return $this->hasMany(AutomationLog::class, 'automation_proposal_id');
    }


    public function modifyLeadAfterSendRule()
    {
        return $this->hasOne(AutomationProposalModifyLeadAfterSendRule::class, 'automation_proposal_id');
    }


    public function interactionRule()
    {
        return $this->hasOne(AutomationProposalInteractionRule::class, 'automation_proposal_id');
    }


    public function resendRule()
    {
        return $this->hasOne(AutomationProposalResendRule::class, 'automation_proposal_id');
    }

}
