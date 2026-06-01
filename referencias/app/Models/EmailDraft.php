<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


/**
 * Info: It does not use SoftDeletes.
 */
class EmailDraft extends Model
{

    use HasFactory;

    protected $table = 'EmailDrafts';
    
    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'client_id' => 'int',
            'lead_id' => 'int',
            'subject' => 'string',
            'body' => 'string',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
        ];

        parent::__construct($attributes);
    }


    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }

}
