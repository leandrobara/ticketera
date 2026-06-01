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


class AutomationEmailSendStep extends Model
{

    use SoftDeletes, BaseModelRelationCache, ClientModelRelationCache, HasFactory;

    protected $table = 'AutomationsEmailSendSteps';

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'integer',
            'send_hour' => 'string',
            'client_id' => 'integer',
            'add_tags_ids' => 'array',
            'add_status_id' => 'integer',
            'deleted_at_ts' => 'integer',
            'send_delay_days' => 'integer',
            'send_delay_minutes' => 'integer',
            'send_email_template_id' => 'integer',
            'automation_email_send_id' => 'integer',
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


    public function automationEmailSend()
    {
        return $this->belongsTo(AutomationEmailSend::class, 'automation_email_send_id');
    }


    public function getAutomationEmailSendAttribute(): ?AutomationEmailSend
    {
        return $this->getModelRelationFromCache(
            'automationEmailSend',
            'AutomationEmailSend',
            $this->automation_email_send_id
        );
    }


    public function automationLog()
    {
        return $this->hasOne(AutomationLog::class, 'automation_email_send_step_id');
    }


    public function sendEmailTemplate()
    {
        return $this->belongsTo(EmailTemplate::class, 'send_email_template_id');
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
