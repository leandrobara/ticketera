<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class AutomationNewLeadUtmParameter extends Model
{

    use SoftDeletes;

    protected $table = 'AutomationsNewLeadUtmParameters';

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'integer',
            'utm_name' => 'string',
            'utm_values' => 'array',
            'client_id' => 'integer',
            'expression' => 'string',
            'automation_new_lead_id' => 'integer',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format'),
        ];
        parent::__construct($attributes);
    }


    public function client()
    {
        return $this->belongsTo(Client::class, 'client');
    }


    public function automationNewLead()
    {
        return $this->belongsTo(AutomationNewLead::class, 'automation_new_lead_id');
    }


    public function getIsGreaterThanEqualAttribute(): bool
    {
        return $this->expression === 'gte';
    }


    public function getIsLessThanEqualAttribute(): bool
    {
        return $this->expression === 'lte';
    }


    public function getIsEqualAttribute(): bool
    {
        return $this->expression === 'eq';
    }


    public function getIsNotEqualAttribute(): bool
    {
        return $this->expression === 'neq';
    }

}
