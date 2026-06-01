<?php

namespace App\Models;

use App\Services\API\TagService;
use Illuminate\Support\Collection;
use App\Services\API\StatusService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\ModelCache\BaseModelRelationCache;
use App\Models\Traits\ModelCache\ClientModelRelationCache;


class AutomationEmailSend extends Model
{

    use SoftDeletes, BaseModelRelationCache, ClientModelRelationCache, HasFactory;


    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'AutomationsEmailSend';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'integer',
            'name' => 'string',
            'enabled' => 'boolean',
            'client_id' => 'integer',
            'add_tags_ids' => 'array',
            'trigger_type' => 'string',
            'add_status_id' => 'integer',
            'cancelling_tags_ids' => 'array',
            'triggering_tags_ids' => 'array',
            'triggering_status_ids' => 'array',
            'cancelling_status_ids' => 'array',
            'do_not_send_weekends' => 'boolean',
            'cancel_if_sequence_was_sent' => 'boolean',
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


    public function automationEmailSendSteps()
    {
        return $this->hasMany(AutomationEmailSendStep::class, 'automation_email_send_id')
            ->orderBy('send_delay_days', 'asc')
            ->orderBy('send_delay_minutes', 'asc')
        ;
    }


    public function automationLog()
    {
        return $this->hasOne(AutomationLog::class, 'automation_email_send_id');
    }


    public function getIsAfterSaleTypeAttribute(): bool
    {
        return $this->trigger_type == 'after_sale';
    }


    public function getIsAfterSentProposalTypeAttribute(): bool
    {
        return $this->trigger_type == 'after_sent_proposal';
    }


    public function getIsAfterTagOrStatusChangeTypeAttribute(): bool
    {
        return $this->trigger_type == 'after_tag_status_change';
    }
    

    public function getIsTagTriggeredAttribute(): bool
    {
        return $this->triggering_tags_ids && $this->isAfterTagOrStatusChangeType;
    }


    public function getIsStatusTriggeredAttribute(): bool
    {
        return $this->triggering_status_ids && $this->isAfterTagOrStatusChangeType;
    }


    public function getTriggeringStatusAttribute(?array $opts = []): Collection
    {
        // $statusIds = $this->triggering_status_ids;
        // if (!$statusIds) {
        //     return new Collection();
        // }
        // $query = Status::whereIn('id', $statusIds);
        // if ($opts['withTrashed'] ?? false) {
        //     $query->withTrashed();
        // }
        // $statusList = $query->get();
        // return $statusList;


        // @todo Sacar el Service de acá y meter alguna solución de cache de modelos relacionados.
        $clientId = $this->client_id;
        $statusIds = $this->triggering_status_ids;
        $withTrashed = $opts['withTrashed'] ?? false;
        if (!$statusIds) {
            return new Collection();
        }
        if ($withTrashed) {
            $statusList = resolve(StatusService::class)->findWithTrashedByClientIdAndIds($clientId, $statusIds);
        } else {
            $statusList = resolve(StatusService::class)->findByClientIdAndIds($clientId, $statusIds);
        }
        return $statusList;
    }


    public function getTriggeringTagsAttribute(?array $opts = []): Collection
    {
        // $tagIds = $this->triggering_tags_ids;
        // if (!$tagIds) {
        //     return new Collection();
        // }
        // $query = Tag::whereIn('id', $tagIds);
        // if ($opts['withTrashed'] ?? false) {
        //     $query->withTrashed();
        // }
        // $tags = $query->get();
        // return $tags;

        // @todo Sacar el Service de acá y meter alguna solución de cache de modelos relacionados.
        $clientId = $this->client_id;
        $tagIds = $this->triggering_tags_ids;
        $withTrashed = $opts['withTrashed'] ?? false;
        if (!$tagIds) {
            return new Collection();
        }
        if ($withTrashed) {
            $tags = resolve(TagService::class)->findWithTrashedByClientIdAndIds($clientId, $tagIds);
        } else {
            $tags = resolve(TagService::class)->findByClientIdAndIds($clientId, $tagIds);
        }
        return $tags;
    }


    public function getCancellingStatusAttribute(): Collection
    {
        // $statusIds = $this->cancelling_status_ids;
        // if (!$statusIds) {
        //     return collect([]);
        // }
        // $status = Status::whereIn('id', $statusIds)->get();
        // return $status;

        // @todo Sacar el Service de acá y meter alguna solución de cache de modelos relacionados.
        $cancellingStatusList = new Collection();
        $statusIds = $this->cancelling_status_ids;
        if ($statusIds) {
            $cancellingStatusList = resolve(StatusService::class)->findByClientIdAndIds($this->client_id, $statusIds);
        }
        return $cancellingStatusList;
    }


    public function getCancellingTagsAttribute(): Collection
    {
        // $tagIds = $this->cancelling_tags_ids;
        // if (!$tagIds) {
        //     return collect([]);
        // }
        // $tags = Tag::whereIn('id', $tagIds)->get();
        // return $tags;

        // @todo Sacar el Service de acá y meter alguna solución de cache de modelos relacionados.
        $cancellingTags = new Collection();
        $tagIds = $this->cancelling_tags_ids;
        if ($tagIds) {
            $cancellingTags = resolve(TagService::class)->findByClientIdAndIds($this->client_id, $tagIds);
        }
        return $cancellingTags;
    }

}
