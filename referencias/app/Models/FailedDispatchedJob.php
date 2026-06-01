<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class FailedDispatchedJob extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'failed_dispatched_jobs';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'queue' => 'string',
            'client_id' => 'int',
            'exception' => 'string',
            'serialized_job' => 'string',
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


    public function getUnserializedJobAttribute()
    {
        return unserialize($this->serialized_job);
    }

}
