<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class LeadSale extends Model
{

    use SoftDeletes, HasFactory;

    protected $table = 'LeadsSales';
    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'lead_id' => 'int',
            'user_id' => 'int',
            'amount' => 'float',
            'client_id' => 'int',
            'description' => 'string',
            'is_manually_created' => 'bool',
            'sale_date' => 'datetime:'  . config('app.datetime_format'),
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


    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
