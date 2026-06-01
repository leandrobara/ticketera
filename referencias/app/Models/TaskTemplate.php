<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class TaskTemplate extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'TaskTemplates';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'client_id' => 'int',
            'title' => 'string',
            'description' => 'string',
            'limit_date_days' => 'int',
            'template_name' => 'string',
            'is_important' => 'boolean',
            'limit_date_hour' => 'string',
            'template_category_id' => 'int',
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


    public function automationsTask()
    {
        return $this->hasMany(AutomationTask::class, 'task_template_id');
    }


    public function templateCategory()
    {
        return $this->belongsTo(templateCategory::class);
    }

}
