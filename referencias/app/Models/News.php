<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class News extends Model
{

    use SoftDeletes;

    protected $casts = [];
    protected $table = 'News';
    public $timestamps = true;
    protected $guarded = ['id'];

    
    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'type' => 'string',
            'title' => 'string',
            'client_id' => 'int',
            'subtitle' => 'string',
            'force_modal_show' => 'boolean',
            'apply_to_future_clients' => 'bool',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format'),
        ];

        parent::__construct($attributes);
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function newsNotifications()
    {
        return $this->hasMany(NewsNotification::class);
    }

}
