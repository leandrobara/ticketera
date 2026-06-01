<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class GoogleAPIUserToken extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'GoogleAPIUserTokens';

    const GMAIL_API_TYPE = 'gmail_api';
    const PEOPLE_API_TYPE = 'people_api';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'type' => 'string',
            'user_id' => 'integer',
            'auth_code' => 'string',
            'client_id' => 'integer',
            'linked_email' => 'string',
            'deleted_at_ts' => 'integer',
            'json_token_string' => 'string',
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


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function getDecodedTokenAttribute(): ?array
    {
        return $this->json_token_string ? json_decode($this->json_token_string, true) : null;
    }


    public function isGmailAPIType()
    {
        return $this->type == self::GMAIL_API_TYPE;
    }


    public function isPeopleAPIType()
    {
        return $this->type == self::PEOPLE_API_TYPE;
    }

}
