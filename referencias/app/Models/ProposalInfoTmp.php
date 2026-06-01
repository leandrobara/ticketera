<?php

namespace App\Models;

use App\DTO\MailerEmailDTO;
use App\DTO\MailerMassiveEmailDTO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class ProposalInfoTmp extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'ProposalsInfoTmp';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'string',
            'amount' => 'float',
            'user_id' => 'integer',
            'email_ids' => 'array',
            'client_id' => 'integer',
            'description' => 'string',
            'whatsapp_sending_id' => 'integer',
            'whatsapp_sending_message_ids' => 'array',
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


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function whatsAppSending()
    {
        return $this->belongsTo(whatsappSending::class);
    }


    public function hasWhatsAppSendingMessageId(int $whatsAppSendingMessageId): bool
    {
        $wapMsgIdsArr = $this->whatsapp_sending_message_ids;
        $whatsAppSendingMessageId = "$whatsAppSendingMessageId";
        return in_array($whatsAppSendingMessageId, $wapMsgIdsArr);
    }


    public function hasWhatsAppSendingMessage(WhatsAppSendingMessage $wapSendingMsg): bool
    {
        return $this->hasWhatsAppSendingMessageId($wapSendingMsg->id);
    }

}
