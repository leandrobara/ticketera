<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use App\DTO\GoogleAPI\GoogleAPIContactDTO;
use Illuminate\Database\Eloquent\SoftDeletes;


class GoogleAPIUserContact extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    public $googleAPIContactDTO = null;
    protected $table = 'GoogleAPIUserContacts';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'user_id' => 'integer',
            'lead_id' => 'integer',
            'client_id' => 'integer',
            'resource_name' => 'string',
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


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

}
