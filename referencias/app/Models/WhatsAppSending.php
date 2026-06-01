<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class WhatsAppSending extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'WhatsAppSendings';

    public const WAPI_TYPE = 'wapi';
    public const WAP_SENDER_TYPE = 'wap_sender';
    public const WAP_WEB_TYPE = 'wap_web_standard';
    public const WAP_SENDER_JOB_TYPE = 'wap_sender_job';
    public const WHATSAPP_META_API_TYPE = 'whatsapp_meta_api';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'type' => 'string',
            'user_id' => 'int',
            'client_id' => 'int',
            'is_massive' => 'bool',
            'is_proposal' => 'bool',
            'is_automation' => 'bool',
            'fail_reason' => 'string',
            'pause_reason' => 'string',
            'whatsapp_attachment_id' => 'int',
            'whatsapp_sending_message_text_id' => 'int',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format'),
            'send_date' => 'datetime:'  . config('app.datetime_format'),
            'paused_date' => 'datetime:'  . config('app.datetime_format'),
            'failed_date' => 'datetime:'  . config('app.datetime_format'),
            'finished_date' => 'datetime:'  . config('app.datetime_format'),
            'cancelled_date' => 'datetime:'  . config('app.datetime_format'),
            'last_sent_message_date' => 'datetime:'  . config('app.datetime_format'),
            'first_sent_message_date' => 'datetime:'  . config('app.datetime_format'),
        ];

        parent::__construct($attributes);
    }


    public function proposalInfo()
    {
        return $this->hasOne(ProposalInfo::class, 'whatsapp_sending_id');
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function whatsAppTemplate()
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }


    public function whatsAppSendingMessageText()
    {
        return $this->belongsTo(WhatsAppSendingMessageText::class, 'whatsapp_sending_message_text_id');
    }


    public function whatsAppSendingMessages()
    {
        return $this->hasMany(WhatsAppSendingMessage::class, 'whatsapp_sending_id')->orderBy('id');
    }


    public function whatsAppAttachment()
    {
        return $this->belongsTo(WhatsAppAttachment::class, 'whatsapp_attachment_id');
    }


    public function getNotSentWhatsAppSendingMessagesCountAttribute(): int
    {
        return $this->whatsAppSendingMessages()->whereNull('sent_date')->whereNull('error_message')->count();
    }


    public function canBePaused(): bool
    {
        return !$this->cancelled_date && !$this->finished_date && !$this->paused_date;
    }

    public function canBeResumed(): bool
    {
        return !$this->cancelled_date && !$this->finished_date && $this->paused_date;
    }

    public function canBeCancelled(): bool
    {
        return !$this->cancelled_date && !$this->finished_date;
    }

    public function canBeFinished(): bool
    {
        return !$this->cancelled_date && !$this->finished_date && !$this->paused_date;
    }


    public function isWapSenderJobType()
    {
        return $this->type == 'wap_sender_job';
    }

    public function isWapSenderType()
    {
        return $this->type == 'wap_sender';
    }

    public function isWapiType()
    {
        return $this->type == 'wapi';
    }

    public function isWhatsAppMetaAPIType()
    {
        return $this->type == 'whatsapp_meta_api';
    }

    public function isWapWebType()
    {
        return $this->type == 'wap_web_standard';
    }

}
