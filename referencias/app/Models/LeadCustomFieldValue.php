<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class LeadCustomFieldValue extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'LeadsCustomFieldsValues';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'hash' => 'string',
            'lead_id' => 'int',
            'value' => 'string',
            'client_id' => 'int',
            'lead_custom_field_id' => 'int',
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


    public function leadCustomField()
    {
        return $this->belongsTo(LeadCustomField::class);
    }


    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }


    public static function buildHash(string $value): string
    {
        return md5($value);
    }

}
