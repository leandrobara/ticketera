<?php

namespace App\Models\MongoDB\WhatsAppMetaAPI;

use Exception;
use Carbon\Carbon;
use App\Models\Client;
use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use MongoDB\Laravel\Eloquent\HybridRelations;
use App\DTO\WapBot\WhatsAppMetaAPIWebhookPayloadDTO;


class WhatsAppConversationMessage extends Model
{

    use SoftDeletes, HybridRelations;

    protected $guarded = ['id'];
    protected $connection = 'mongodb_whatsapp_meta';
    protected $table  = 'WhatsAppConversationMessages';
    public $timestamps = false; // usamos createdAt/updatedAt (camelCase)

    // Personaliza el nombre del campo de soft delete
    protected const DELETED_AT = 'deletedAt'; // Usa camelCase en lugar de deleted_at

    const DIRECTION_INCOMING = 'incoming';
    const DIRECTION_OUTGOING = 'outgoing';

    const SOURCE_APP_MESSAGE = 'app_message';
    const SOURCE_API_MESSAGE = 'api_message';
    const SOURCE_WAP_BOT_MESSAGE = 'wapbot_message';
    const SOURCE_HISTORY_MESSAGE = 'history_message';


    protected $casts = [
        'source' => 'string', // app_message | api_message | history_message
        'clientId' => 'integer', // mmm creo que no eh, a lo sumo pero descriptivo.
        'direction' => 'string', // incoming | outgoing
        'hash' => 'string', // sirve?
        'metaStatus' => 'string',
        'messageType' => 'string', // text | audio | voice | image | document | sticker | video | location | contacts
        'metaMessageId' => 'string',
        'customerPhoneNumber' => 'string',
        'metaConnectedPhoneNumberId' => 'string',
        'metaReceivedMessageTimestamp' => 'datetime',
        // 'media' => 'array', // Ojo no castear, lo convierte en string si no
        // 'metaError' => 'array', // MIXED Ojo no castear, lo convierte en string si no
        // 'metaRawPayload' => 'array', // Raw log recibido. Ojo no castear, lo convierte en string si no
        'createdAt' => 'datetime',
        'deletedAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];


    protected static function boot()
    {
        parent::boot();
        static::creating(function (self $model) {
            $now = Carbon::now();
            if (empty($model->createdAt)) {
                $model->createdAt = $now;
            }
            if (empty($model->updatedAt)) {
                $model->updatedAt = $now;
            }
        });
        static::updating(function (self $model) {
            $model->updatedAt = Carbon::now();
        });
    }


    // public function client()
    // {
    //     return $this->belongsTo(Client::class, 'clientId', 'id');
    // }


    public static function fillFromMetaPayloadDTO(WhatsAppMetaAPIWebhookPayloadDTO $dto): WhatsAppConversationMessage
    {
        $model = new WhatsAppConversationMessage();
        $model->media = $dto->getMediaData(); // null si no es attachment
        $model->source = self::SOURCE_APP_MESSAGE; // @todo cambiar cuando sume history

        $model->messageType = $dto->getMessageType();
        $model->metaMessageId = $dto->getMessageId();
        $model->metaReceivedMessageTimestamp = $dto->getTimestamp();
        $model->metaConnectedPhoneNumberId = $dto->getPhoneNumberId();
        $model->direction = $dto->isOutgoingEchoMessage() ? self::DIRECTION_OUTGOING : self::DIRECTION_INCOMING;
        $model->customerPhoneNumber = $dto->isOutgoingEchoMessage() ? $dto->getToNumber() : $dto->getFromNumber();
        
        $model->metaRawPayload = $dto->getMetaRawPayload();
        $model->hash = $model->buildHash();
        return $model;
    }


    public function hasDownloadableMedia(): bool
    {
        return is_array($this->media) && !empty($this->media['id']);
    }


    public function buildHash(): string
    {
        if (!$this->metaRawPayload) {
            throw new Exception('WhatsAppConversationMessage buildHash() | metaRawPayload is missing');
        }
        return md5(json_encode($this->metaRawPayload));
    }

}

/*
    Collection "WapBotConversations":
    {
        "_id": "ObjectId",
        "createdAt": "ISODate(\"2025-09-12T12:55:00Z\")",
        "updatedAt": "ISODate(\"2025-09-12T13:00:00Z\")"
        "deletedAt": null | "ISODate(\"2025-09-12T13:00:00Z\")"
    }
*/