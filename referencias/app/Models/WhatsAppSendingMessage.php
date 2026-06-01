<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class WhatsAppSendingMessage extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'WhatsAppSendingMessages';

    public const WAPI_TYPE = WhatsAppSending::WAPI_TYPE;
    public const WAP_WEB_TYPE = WhatsAppSending::WAP_WEB_TYPE;
    public const WAP_SENDER_TYPE = WhatsAppSending::WAP_SENDER_TYPE;
    public const WAP_SENDER_JOB_TYPE = WhatsAppSending::WAP_SENDER_JOB_TYPE;
    public const WHATSAPP_META_API_TYPE = WhatsAppSending::WHATSAPP_META_API_TYPE;


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'lead_id' => 'int',
            'type' => 'string',
            'user_id' => 'int',
            'client_id' => 'int',
            'meta_id' => 'string',
            'success' => 'boolean',
            'variables' => 'array',
            'send_attempts' => 'int',
            'is_massive' => 'boolean',
            'meta_status' => 'string',
            'is_proposal' => 'boolean',
            'meta_id_hash' => 'string',
            'phone_number' => 'string',
            'meta_webhook_ts' => 'int',
            'error_message' => 'string',
            'wautomation_log_id' => 'int',
            'whatsapp_sending_id' => 'int',
            'lead_contact_phone_id' => 'int',
            'send_date' => 'datetime:' . config('app.datetime_format'),
            'sent_date' => 'datetime:' . config('app.datetime_format'),
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format'),
            'paused_date' => 'datetime:' . config('app.datetime_format'),
            'cancelled_date' => 'datetime:' . config('app.datetime_format'),
            'dispatched_date' => 'datetime:' . config('app.datetime_format'),
            'last_dispatched_date' => 'datetime:' . config('app.datetime_format'),
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


    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }


    public function leadContactPhone()
    {
        return $this->belongsTo(LeadContactPhone::class);
    }


    public function whatsAppSending()
    {
        return $this->belongsTo(WhatsAppSending::class, 'whatsapp_sending_id');
    }


    public function wAutomationLog()
    {
        return $this->belongsTo(WAutomationLog::class, 'wautomation_log_id');
    }


    public function isWapSenderJobType()
    {
        return $this->type == self::WAP_SENDER_JOB_TYPE;
    }

    public function isWapSenderType()
    {
        return $this->type == self::WAP_SENDER_TYPE;
    }

    public function isWapiType()
    {
        return $this->type == self::WAPI_TYPE;
    }

    public function isWapWebType()
    {
        return $this->type == self::WAP_WEB_TYPE;
    }

    public function isWhatsAppMetaAPIType()
    {
        return $this->type == self::WHATSAPP_META_API_TYPE;
    }


    public static function buildMetaIdHash(string $metaId): string
    {
        $hash = md5($metaId);
        return substr($hash, 0, 20);
    }

}
