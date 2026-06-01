<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class TaskNotificationEmail extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'TasksNotificationEmails';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'type' => 'string',
            'task_id' => 'integer',
            'user_id' => 'integer',
            'client_id' => 'integer',
            'do_not_send' => 'boolean',
            'external_email_id' => 'integer',
            'massive_user_change_task_ids' => 'array',
            'send_date' => 'datetime:' . config('app.datetime_format'),
            'sent_date' => 'datetime:' . config('app.datetime_format'),
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format'),
            'scheduled_date' => 'datetime:' . config('app.datetime_format'),
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
