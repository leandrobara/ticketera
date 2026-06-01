<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class TaskNotificationWhatsAppMessage extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'TasksNotificationWhatsAppMessages';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'type' => 'string',
            'task_id' => 'integer',
            'user_id' => 'integer',
            'success' => 'boolean',
            'exception' => 'string',
            'client_id' => 'integer',
            'do_not_send' => 'boolean',
            'sent_date' => 'datetime:' . config('app.datetime_format'),
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format'),
            'dispatched_date' => 'datetime:' . config('app.datetime_format'),
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


    public function task()
    {
        return $this->belongsTo(Task::class);
    }

}
