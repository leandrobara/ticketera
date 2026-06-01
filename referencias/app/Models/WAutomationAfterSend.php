<?php

namespace App\Models;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class WAutomationAfterSend extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'WAutomationsAfterSend';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'integer',
            'enabled' => 'boolean',
            'client_id' => 'integer',
            'add_tags_ids' => 'array',
            'add_new_note' => 'boolean',
            'new_note_text' => 'string',
            'deleted_at_ts' => 'integer',
            'remove_tags_ids' => 'array',
            'apply_only_once' => 'boolean',
            'assign_status_id' => 'integer',
            'only_apply_to_massive_sendings' => 'boolean',
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


    public function wAutomationLogs()
    {
        return $this->hasMany(WAutomationLog::class, 'wautomation_after_send_id');
    }


    public function statusToAssign()
    {
        return $this->belongsTo(Status::class, 'assign_status_id');
    }


    public function getTagsToAddAttribute(?array $opts = []): Collection
    {
        $tagIds = $this->add_tags_ids;
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


    public function getTagsToRemoveAttribute(?array $opts = []): Collection
    {
        $tagIds = $this->remove_tags_ids;
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

}
