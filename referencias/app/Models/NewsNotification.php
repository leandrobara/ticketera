<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class NewsNotification extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'NewsNotifications';

    
    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'news_id' => 'int',
            'user_id' => 'int',
            'client_id' => 'int',
            'is_notification_viewed' => 'boolean',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format'),
        ];

        parent::__construct($attributes);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function news()
    {
        return $this->belongsTo(News::class);
    }

}
