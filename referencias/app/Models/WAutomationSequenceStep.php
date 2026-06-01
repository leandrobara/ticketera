<?php

namespace App\Models;

use DateTime;
use App\Services\API\TagService;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\ModelCache\BaseModelRelationCache;
use App\Models\Traits\ModelCache\ClientModelRelationCache;


class WAutomationSequenceStep extends Model
{

    use SoftDeletes, BaseModelRelationCache, ClientModelRelationCache, HasFactory;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'WAutomationsSequenceSteps';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'integer',
            'send_hour' => 'string',
            'client_id' => 'integer',
            'add_tags_ids' => 'array',
            'deleted_at_ts' => 'integer',
            'add_status_id' => 'integer',
            'send_delay_days' => 'integer',
            'send_delay_minutes' => 'integer',
            'wautomation_sequence_id' => 'integer',
            'send_whatsapp_template_id' => 'integer',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format')
        ];
        parent::__construct($attributes);
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function wAutomationSequence()
    {
        return $this->belongsTo(WAutomationSequence::class, 'wautomation_sequence_id');
    }


    public function wAutomationLog()
    {
        return $this->hasOne(WAutomationLog::class, 'wautomation_sequence_step_id');
    }


    public function sendWhatsAppTemplate()
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'send_whatsapp_template_id');
    }


    public function isToSendSameDay(): bool
    {
        return $this->send_delay_minutes !== null;
    }

    
    public function statusToAdd()
    {
        return $this->belongsTo(Status::class, 'add_status_id');
    }


    public function getTagsToAddAttribute(?array $opts = []): Collection
    {
        // @todo Sacar el Service de acá y meter alguna solución de cache de modelos relacionados.
        $tags = new Collection();
        $tagIds = $this->add_tags_ids;
        if ($tagIds) {
            $tags = resolve(TagService::class)->findByClientIdAndIds($this->client_id, $tagIds);
        }
        return $tags;
    }

}
