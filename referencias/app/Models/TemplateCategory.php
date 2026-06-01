<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class TemplateCategory extends Model
{

    use SoftDeletes;


    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'TemplateCategories';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'name' => 'string',
            'client_id' => 'int',
            'text_color' => 'string',
            'background_color' => 'string',
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


    public function emailTemplates()
    {
        return $this->hasMany(EmailTemplate::class);
    }


    public function whatsAppTemplates()
    {
        return $this->hasMany(WhatsAppTemplate::class);
    }


    public function taskTemplates()
    {
        return $this->hasMany(TaskTemplate::class);
    }


    public function getEmailTemplateCountAttribute()
    {
        return $this->emailTemplates()->where('client_id', $this->client_id)->count();
    }


    public function getWhatsAppTemplateCountAttribute()
    {
        return $this->whatsAppTemplates()->where('client_id', $this->client_id)->count();
    }


    public function getTaskTemplateCountAttribute()
    {
        return $this->taskTemplates()->where('client_id', $this->client_id)->count();
    }


    public static function buildHash(string $name): string
    {
        return md5($name);
    }

}
