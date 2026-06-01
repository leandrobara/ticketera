<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class TagCategory extends Model
{

    use SoftDeletes, HasFactory;


    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'TagCategories';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'client_id' => 'int',
            'name' => 'string',
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


    public function tags()
    {
        return $this->hasMany(Tag::class);
    }


    public function getTagsCountAttribute()
    {
        return $this->tags()->count();
    }


    public static function buildHash(string $name): string
    {
        return md5($name);
    }

}
