<?php

namespace App\Models\MongoDB\WapBot;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Lead;
use App\Models\WapBot;
use App\Models\Client;
use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use MongoDB\Laravel\Eloquent\HybridRelations;


class WapBotConversation extends Model
{

    use SoftDeletes, HybridRelations;

    protected $guarded = ['id'];
    protected $connection = 'mongodb_wap_bot';
    protected $table  = 'WapBotConversations';
    public $timestamps = false; // usamos createdAt/updatedAt (camelCase)

    // protected $primaryKey = '_id';
    // public $incrementing = false;
    // protected $keyType = 'string';
    // protected $fillable = ['_id'];


    // Personalizar el nombre del campo de soft delete
    protected const DELETED_AT = 'deletedAt'; // Usa camelCase en lugar de deleted_at

    public const TYPE_HISTORY_SEED = 'historySeed';
    public const TYPE_BOT_CONVERSATION = 'botConversation';


    protected $casts = [
        'type' => 'string',
        'userId' => 'integer',
        'leadId' => 'integer',
        'isEnded' => 'boolean',
        'clientId' => 'integer',
        'wapBotId' => 'integer',
        'botPhoneNumber' => 'string',
        'lastActivityAt' => 'datetime',
        'lastMetaMessageId' => 'string',
        'customerPhoneNumber' => 'string',
        'botMetaPhoneNumberId' => 'string',
        'whatsAppConnectionId' => 'string',
        'lastMetaMessageTimestamp' => 'string',
        'isPermanentSeedConversation' => 'boolean',
        'lastSentMessageToCustomerAt' => 'datetime',
        'isEndedByLeadAutoCreationCron' => 'boolean',
        // 'referralData' => 'array', // Ojo no castear, lo convierte en string si no.
        // 'followUpMessage1' => 'array', // Ojo no castear, lo convierte en string si no.
        // 'openaiHistory' => 'array', // Ojo no castear, lo convierte en string si no.
        // 'extractedParameters' => 'array', // Ojo no castear, lo convierte en string si no.
        'createdAt' => 'datetime',
        'deletedAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    
    // public function getRouteKeyName()
    // {
    //     return '_id';
    // }


    public function client()
    {
        return $this->belongsTo(Client::class, 'clientId', 'id');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'leadId', 'id');
    }

    public function wapBot()
    {
        return $this->belongsTo(WapBot::class, 'wapBotId', 'id');
    }


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
            if (empty($model->lastActivityAt)) {
                $model->lastActivityAt = $now;
            }
        });
        static::updating(function (self $model) {
            $model->updatedAt = Carbon::now();
        });
    }


    public function markAsSeedConversation(): self
    {
        $this->type = self::TYPE_HISTORY_SEED;
        return $this;
    }


    public function isSeedConversation(): bool
    {
        return $this->type == self::TYPE_HISTORY_SEED;
    }


    public function hasBeenActiveWithinLastDays(int $days): bool
    {
        if (!$this->lastActivityAt) {
            return false;
        }
        return $this->lastActivityAt->gte(now()->subDays($days));
    }


    public function customerHasBeenMessagedWithinLastDays(int $days): bool
    {
        if (!$this->lastSentMessageToCustomerAt) {
            return false;
        }
        return $this->lastSentMessageToCustomerAt->gte(now()->subDays($days));
    }


    public function mergeExtractedParameters(array $extractedParameters): WapBotConversation
    {
        $this->extractedParameters = array_replace_recursive(
            $this->extractedParameters ?? [], $extractedParameters
        );
        return $this;
    }


    public function markAsEnded(bool $isEnded = true): WapBotConversation
    {
        $this->isEnded = $isEnded;
        $this->mergeExtractedParameters(['isChatEnded' => $isEnded]);
        return $this;
    }


    public function getConversationTranscript(): string
    {
        return collect($this->openAIHistory)
            ->filter(function ($msg) {
                return in_array($msg['message_type'], [
                    'text',
                    'button_reply',
                    'list_reply',
                    'assistant_button',
                    'assistant_text'
                ]);
            })
            ->map(function ($msg) {
                $role = $msg['role'] === 'user' ? 'Cliente' : 'Asesor';
                return "{$role}: " . $msg['message'];
            })
            ->implode(PHP_EOL)
        ;
    }

}

/*
    Collection "WapBotConversations":
    {
        "_id": "ObjectId",
        "type": "historySeed|botConversation",

        "userId": 123,
        "leadId": 123,
        "clientId": 123,
        "wapBotId": 123,
        "botPhoneNumber": "5491159711575",
        "botMetaPhoneNumberId": "1234567999",
        "customerPhoneNumber": "5491159719999",
        "whatsAppConnectionId": 123, // Ojo, puede cambiar, tener solo de referencia.

        // Ideal: guardar DateTime() de cada paso.
        "openaiHistory": [
            { "role": "user", "content": "Quiero ver precios" },
            { "role": "assistant", "content": "¿Qué producto te interesa?" }
        ],

        "extractedParameters": {
            "isChatEnded": false,
            "wasLeadSentToClienty": false,
            "leadInfo": {
                "rubro": "Textil",
                "zona": "CABA",
                "nombre": "Juan Perez",
                "condicion_minima_aceptada": true
            },
        },

        // Info de referral de Meta (cuando viene de un anuncio de Facebook/Instagram).
        // Puede no existir o ser null
        "referralData": {
            "source_url": "https://fb.me/...",
            "source_id": "6944673055474",
            "source_type": "ad",
            "body": "Texto del anuncio",
            "headline": "Titulo del anuncio",
            "media_type": "video|image",
            "video_url": "...",
            "thumbnail_url": "...",
            "ctwa_clid": "...",
            "welcome_message": { "text": "..." }
        },

        "followUpMessage1": {
            "sentAt": "ISODate(\"2025-09-12T13:00:00Z\")",
            "metaMessageId": "wamid.HBgLxxxxxx",
            "metaStatus": "accepted",
            "metaError": null,
        },

        "isEnded": false, // Redundante, pero para no acoplar con los extrated params.
        "lastMetaMessageId": "wamid.HBgLxxxxxx",
        "lastMetaMessageTimestamp": "xxxxxxx",
        "lastActivityAt": "ISODate(\"2025-09-12T13:00:00Z\")",
        "createdAt": "ISODate(\"2025-09-12T12:55:00Z\")",
        "updatedAt": "ISODate(\"2025-09-12T13:00:00Z\")"
    }
*/