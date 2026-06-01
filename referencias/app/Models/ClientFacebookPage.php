<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class ClientFacebookPage extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'ClientsFacebookPages';
    protected $hidden = ['page_token', 'user_token'];


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'client_id' => 'int',
            'page_id' => 'string',
            'user_token' => 'string',
            'page_token' => 'string',
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

}
