<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class LeadCustomField extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'LeadsCustomFields';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'order' => 'int',
            'name' => 'string',
            'type' => 'string',
            'client_id' => 'int',
            'type_values' => 'array',
            'default_value' => 'string',
            'is_shown_in_leads_row' => 'boolean',
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


    public function leadCustomFieldValues()
    {
        return $this->hasMany(LeadCustomFieldValue::class);
    }


    public function getLeadCustomFieldValueByLead(Lead $lead)
    {
        return $this->hasOne(LeadCustomFieldValue::class)->where('lead_id', $lead->id)->first();
    }

}
