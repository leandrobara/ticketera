<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class AutomationNewLeadTrackingParameter extends Model
{

    use SoftDeletes, HasFactory;

    protected $table = 'AutomationsNewLeadTrackingParameters';

    public $timestamps = true;
    protected $guarded = ['id'];
    protected $casts = [];


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'integer',
            'expression' => 'string',
            'tracking_parameter_name' => 'string',
            'tracking_parameter_values' => 'array',
            'automation_new_lead_id' => 'integer',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format')
        ];
        parent::__construct($attributes);
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
