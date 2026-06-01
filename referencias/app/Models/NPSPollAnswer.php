<?php

namespace App\Models;

use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class NPSPollAnswer extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'NPSPollsAnswers';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'score' => 'int',
            'user_id' => 'int',
            'client_id' => 'int',
            'comments' => 'string',
            'nps_poll_id' => 'int',
            'close_reason' => 'string',
            'deleted_at_ts' => 'timestamp',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format'),
            'closed_date' => 'datetime:'  . config('app.datetime_format'),
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


    public function NPSPoll()
    {
        return $this->belongsTo(NPSPoll::class, 'nps_poll_id');
    }

}
