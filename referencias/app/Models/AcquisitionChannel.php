<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class AcquisitionChannel extends Model
{

    use SoftDeletes, HasFactory;

    protected $table = 'AcquisitionChannels';
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $casts = [];

    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'order' => 'int',
            'name' => 'string',
            'hash' => 'string',
            'client_id' => 'int',
            'text_color' => 'string',
            'background_color' => 'string',
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


    public function leads()
    {
        return $this->hasMany(Lead::class);
    }


    public static function buildHash(string $name): string
    {
        return md5(strtolower($name));
    }


    public function getLeadsCountAttribute()
    {
        return $this->leads()->count();
    }

}
