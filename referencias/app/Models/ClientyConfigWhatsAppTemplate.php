<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class ClientyConfigWhatsAppTemplate extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'ClientyConfigWhatsAppTemplates';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'user_id' => 'int',
            'body' => 'string',
            'title' => 'string',
            'client_id' => 'int',
            'business_area_id' => 'int',
            'business_area_child_id' => 'int',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format'),
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


    public function businessArea()
    {
        return $this->belongsTo(BusinessArea::class, 'business_area_id');
    }


    public function businessAreaChild()
    {
        return $this->belongsTo(BusinessAreaChild::class, 'business_area_child_id');
    }

}
