<?php

namespace App\Models;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Status extends Model
{

    use SoftDeletes, HasFactory;


    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'Status';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'order' => 'int',
            'client_id' => 'int',
            'sale_probability' => 'int',
            'status_category_id' => 'int',
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


    public function statusCategory()
    {
        return $this->belongsTo(StatusCategory::class);
    }


    public function leads()
    {
        return $this->hasMany(Lead::class);
    }


    public function getLeadsCountAttribute()
    {
        return $this->leads()->count();
    }


    public static function buildHash(string $name): string
    {
        return md5(strtolower($name));
    }


    public function automationsProposalModifyLeadAfterSendRule()
    {
        return $this->hasMany(AutomationProposalModifyLeadAfterSendRule::class, 'assign_status_id');
    }


    public function automationsProposalInteractionRule()
    {
        return $this->hasMany(AutomationProposalInteractionRule::class, 'assign_status_id');
    }


    public function getAutomationsEmailSendAttribute(): Collection
    {
        $automations = AutomationEmailSend::where('client_id', $this->client_id)
            ->whereJsonContains('triggering_status_ids', $this->id)
            ->get()
        ;
        return $automations;
    }


    public function getWAutomationsSequenceAttribute(): Collection
    {
        $automations = WAutomationSequence::where('client_id', $this->client_id)
            ->whereJsonContains('triggering_status_ids', $this->id)
            ->get()
        ;
        return $automations;
    }

}
