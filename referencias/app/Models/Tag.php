<?php

namespace App\Models;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Tag extends Model
{

    use SoftDeletes, HasFactory;

    
    protected $casts = [];
    protected $table = 'Tags';
    public $timestamps = true;
    protected $guarded = ['id'];


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'client_id' => 'int',
            'tag_category_id' => 'int',
            'last_used_date' => 'datetime:' . config('app.datetime_format'),
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


    public function leads()
    {
        return $this->belongsToMany(Lead::class, 'Leads_Tags');
    }


    public function tagCategory()
    {
        return $this->belongsTo(TagCategory::class);
    }


    public static function buildHash(string $name): string
    {
        return md5(strtolower($name));
    }


    public function getLeadsCountAttribute()
    {
        return $this->leads()->count();
    }


    public function getAutomationsEmailSendAttribute(): Collection
    {
        $automations = AutomationEmailSend::where('client_id', $this->client_id)
            ->whereJsonContains('triggering_tags_ids', $this->id)
            ->get()
        ;
        return $automations;
    }


    public function getWAutomationsSequenceAttribute(): Collection
    {
        $automations = WAutomationSequence::where('client_id', $this->client_id)
            ->whereJsonContains('triggering_tags_ids', $this->id)
            ->get()
        ;
        return $automations;
    }

}
