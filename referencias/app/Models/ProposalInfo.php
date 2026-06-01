<?php

namespace App\Models;

use App\DTO\MailerEmailDTO;
use App\DTO\MailerMassiveEmailDTO;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class ProposalInfo extends Model
{

    use SoftDeletes;

    protected $table = 'ProposalsInfo';
    
    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'string',
            'amount' => 'float',
            'status' => 'string',
            'user_id' => 'integer',
            'lead_id' => 'integer',
            'email_ids' => 'array',
            'client_id' => 'integer',
            'description' => 'string',
            'whatsapp_message_id' => 'integer',
            'whatsapp_sending_message_ids' => 'array',
            'sent_date'  => 'datetime:' . config('app.datetime_format'),
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format'),
            'email_ids_fixed_at' => 'datetime:' . config('app.datetime_format'),
        ];

        parent::__construct($attributes);
    }


    public function email()
    {
        return $this->belongsTo(Email::class);
    }


    public function getWhatsAppSendingMessagesAttribute(): Collection
    {
        if (empty($this->whatsapp_sending_message_ids)) {
            return new Collection();
        }
        return WhatsAppSendingMessage::whereIn('id', $this->whatsapp_sending_message_ids)->get();
    }


    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function hasEmailId(int $emailId): bool
    {
        $emailIdsArr = $this->email_ids;
        $emailId = "$emailId";
        return in_array($emailId, $emailIdsArr);
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
