<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class BusinessAreaChild extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'BusinessAreasChildren';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'name' => 'string',
            'business_area_id' => 'int',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format')
        ];

        parent::__construct($attributes);
    }


    public static function buildHash(string $name): string
    {
        return md5(trim(strtolower($name)));
    }


    public function clientyConfigEmailTemplates()
    {
        return $this->hasMany(ClientyConfigEmailTemplate::class, 'business_area_id');
    }


    public function businessArea()
    {
        return $this->belongsTo(BusinessArea::class, 'business_area_id');
    }

}
