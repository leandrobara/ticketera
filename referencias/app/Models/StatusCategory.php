<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class StatusCategory extends Model
{

    use SoftDeletes;


    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'StatusCategories';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'order' => 'int',
            'name' => 'string',
            'client_id' => 'int',
            'sale_probability' => 'int',
            'is_irrelevant' => 'boolean',
            'deleted_at_ts' => 'integer',
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


    public function status()
    {
        return $this->hasMany(Status::class);
    }


    public function getStatusCountAttribute()
    {
        return $this->status()->where('client_id', $this->client_id)->count();

    }


    public static function buildHash(string $name): string
    {
        return md5($name);
    }

}
