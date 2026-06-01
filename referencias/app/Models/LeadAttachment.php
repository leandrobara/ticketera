<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class LeadAttachment extends Model
{

    use SoftDeletes;

    protected $table = 'LeadsAttachments';
    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'size' => 'int',
            'hash' => 'string',
            'lead_id' => 'int',
            'client_id' => 'int',
            'extension' => 'string',
            'bucket_name' => 'string',
            'bucket_filepath' => 'string',
            'deleted_at_ts' => 'timestamp',
            'original_filename' => 'string',
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
    
}
