<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class AutomationNewLeadCustomFieldMapping extends Model
{

    use SoftDeletes;


    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'AutomationsNewLeadCustomFieldsMapping';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'integer',
            'form_field_name' => 'string',
            'lead_custom_field_id' => 'integer',
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


    public function leadCustomField()
    {
        return $this->belongsTo(LeadCustomField::class, 'lead_custom_field_id');
    }

}
