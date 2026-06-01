<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Note extends Model
{

    use SoftDeletes, HasFactory;

    protected $casts = [];
    public $timestamps = true;
    protected $table = 'Notes';
    protected $guarded = ['id'];


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'user_id' => 'int',
            'client_id' => 'int',
            'lead_id' => 'int',
            'text' => 'string',
            'audionote_bucket_hash' => 'string',
            'audionote_bucket_name' => 'string',
            'audionote_transcription' => 'string',
            'audionote_bucket_file_size' => 'int',
            'audionote_bucket_filepath' => 'string',
            'audionote_bucket_file_extension' => 'string',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format')
        ];

        parent::__construct($attributes);
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
