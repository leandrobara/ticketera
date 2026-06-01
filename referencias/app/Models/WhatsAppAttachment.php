<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class WhatsAppAttachment extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'WhatsAppAttachments';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'size' => 'int',
            'hash' => 'string',
            'client_id' => 'int',
            'extension' => 'string',
            'mime_type' => 'string',
            'bucket_name' => 'string',
            'meta_handle_id' => 'string',
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


    public function getMetaMediaType(): string
    {
        $mimeType = $this->mime_type;
        if (Str::startsWith($mimeType, 'image/')) {
            return 'image';
        }
        if (Str::startsWith($mimeType, 'video/')) {
            return 'video';
        }
        if (Str::startsWith($mimeType, 'audio/')) {
            return 'audio';
        }
        return 'document';
    }

}
