<?php

namespace App\Models;

use App\Services\API\TagService;
use Illuminate\Support\Collection;
use App\Services\API\StatusService;
use App\Models\Interfaces\Automation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\ModelCache\BaseModelRelationCache;
use App\Models\Traits\ModelCache\ClientModelRelationCache;


class AutomationTask extends Model implements Automation
{

    use SoftDeletes, BaseModelRelationCache, ClientModelRelationCache;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'AutomationsTask';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'integer',
            'enabled' => 'boolean',
            'client_id' => 'integer',
            'create_hour' => 'string',
            'trigger_type' => 'string',
            'is_recurrent' => 'boolean',
            'allowing_tags_ids' => 'array',
            'deleted_at_ts' => 'timestamp',
            'task_template_id' => 'integer',
            'tags_ids_to_assign' => 'array',
            'create_delay_days' => 'integer',
            'cancelling_tags_ids' => 'array',
            'triggering_tags_ids' => 'array',
            'allowing_status_ids' => 'array',
            'status_id_to_assign' => 'integer',
            'triggering_status_ids' => 'array',
            'cancelling_status_ids' => 'array',
            'is_immediately_created' => 'boolean',
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


    public function taskTemplate()
    {
        return $this->belongsTo(TaskTemplate::class);
    }


    public function getTaskTemplateAttribute(): ?TaskTemplate
    {
        return $this->getModelRelationFromCache('taskTemplate', 'TaskTemplate', $this->task_template_id);
    }


    public function getIsAfterSaleTypeAttribute(): bool
    {
        return $this->trigger_type == 'after_sale';
    }
    
    
    public function getIsAfterTagChangeTypeAttribute(): bool
    {
        return $this->trigger_type == 'after_tag_change';
    }


    public function getIsAfterStatusChangeTypeAttribute(): bool
    {
        return $this->trigger_type == 'after_status_change';
    }


    public function getIsAfterTaskExpirationTypeAttribute(): bool
    {
        return $this->trigger_type == 'after_task_expiration';
    }


    public function getIsTagTriggeredAttribute(): bool
    {
        return $this->triggering_tags_ids && $this->isAfterTagChangeType;
    }


    public function getIsStatusTriggeredAttribute(): bool
    {
        return $this->triggering_status_ids && $this->isAfterStatusChangeType;
    }


    public function getTriggeringStatusAttribute(?array $opts = []): Collection
    {
        $statusIds = $this->triggering_status_ids;
        if (!$statusIds) {
            return collect([]);
        }
        $query = Status::whereIn('id', $statusIds);
        if ($opts['withTrashed'] ?? false) {
            $query->withTrashed();
        }
        $statusList = $query->get();
        return $statusList;
    }


    public function getTriggeringTagsAttribute(?array $opts = []): Collection
    {
        $tagIds = $this->triggering_tags_ids;
        if (!$tagIds) {
            return collect([]);
        }
        $query = Tag::whereIn('id', $tagIds);
        if ($opts['withTrashed'] ?? false) {
            $query->withTrashed();
        }
        $tags = $query->get();
        return $tags;
    }


    public function getCancellingStatusAttribute(): Collection
    {
        // @todo Sacar el Service de acá y meter alguna solución de cache de modelos relacionados.
        $statusList = new Collection();
        $statusIds = $this->cancelling_status_ids;
        if ($statusIds) {
            $statusList = resolve(StatusService::class)->findByClientIdAndIds($this->client_id, $statusIds);
        }
        return $statusList;
    }


    public function getCancellingTagsAttribute(): Collection
    {
        // @todo Sacar el Service de acá y meter alguna solución de cache de modelos relacionados.
        $tags = new Collection();
        $tagIds = $this->cancelling_tags_ids;
        if ($tagIds) {
            $tags = resolve(TagService::class)->findByClientIdAndIds($this->client_id, $tagIds);
        }
        return $tags;
    }


    public function getTagsToAssignAttribute(?array $opts = []): Collection
    {
        // @todo Sacar el Service de acá y meter alguna solución de cache de modelos relacionados.
        $tags = new Collection();
        $tagIds = $this->tags_ids_to_assign;
        if ($tagIds) {
            $tags = resolve(TagService::class)->findByClientIdAndIds($this->client_id, $tagIds);
        }
        return $tags;
    }


    public function statusToAssign()
    {
        return $this->belongsTo(Status::class, 'status_id_to_assign');
    }


    public function getAllowingTagsAttribute(?array $opts = []): Collection
    {
        // @todo Sacar el Service de acá y meter alguna solución de cache de modelos relacionados.
        $tags = new Collection();
        $tagIds = $this->allowing_tags_ids;
        if ($tagIds) {
            $tags = resolve(TagService::class)->findByClientIdAndIds($this->client_id, $tagIds);
        }
        return $tags;
    }


    public function getAllowingStatusAttribute(?array $opts = []): Collection
    {
        // @todo Sacar el Service de acá y meter alguna solución de cache de modelos relacionados.
        $statusList = new Collection();
        $statusIds = $this->allowing_status_ids;
        if ($statusIds) {
            $statusList = resolve(StatusService::class)->findByClientIdAndIds($this->client_id, $statusIds);
        }
        return $statusList;
    }

}
