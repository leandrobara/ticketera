<?php

namespace App\Models;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\ModelCache\BaseModelRelationCache;
use App\Models\Traits\ModelCache\ClientModelRelationCache;


class WAutomationProposal extends Model
{

    use SoftDeletes, BaseModelRelationCache, ClientModelRelationCache, HasFactory;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'WAutomationsProposal';


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


    public function wAutomationLogs()
    {
        return $this->hasMany(WAutomationLog::class, 'wautomation_proposal_id');
    }


    public function modifyLeadAfterSendRule()
    {
        return $this->hasOne(WAutomationProposalModifyLeadAfterSendRule::class, 'wautomation_proposal_id');
    }


    public function resendRule()
    {
        return $this->hasOne(WAutomationProposalResendRule::class, 'wautomation_proposal_id');
    }

}
