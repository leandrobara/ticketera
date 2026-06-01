<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class NPSPoll extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'NPSPolls';

    
    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'type' => 'string',
            'client_id' => 'int',
            'score_title' => 'string',
            'comments_title' => 'string',
            'show_once_per_day' => 'boolean',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format'),
            'date_closed' => 'datetime:'  . config('app.datetime_format'),
        ];

        parent::__construct($attributes);
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function NPSPollAnswers()
    {
        return $this->hasMany(NPSPollAnswer::class, 'nps_poll_id');
    }

    public function NPSPollAnswersForClient(Client $client)
    {
        return $this->hasMany(NPSPollAnswer::class, 'nps_poll_id')->where('client_id', $client->id)->get();
    }


    public function getNPSPollAnswerByUser(User $user): ?NPSPollAnswer
    {
        return $this->hasOne(NPSPollAnswer::class, 'nps_poll_id')->where('user_id', $user->id)->first();
    }

}
