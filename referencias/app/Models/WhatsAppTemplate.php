<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\ModelCache\BaseModelRelationCache;


class WhatsAppTemplate extends Model
{

    use SoftDeletes, BaseModelRelationCache, HasFactory;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'WhatsAppTemplates';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'body' => 'string',
            'title' => 'string',
            'client_id' => 'int',
            'meta_id' => 'string',
            'waba_id' => 'string',
            'meta_name' => 'string',
            'is_proposal' => 'boolean',
            'meta_category' => 'string',
            'meta_header_text' => 'string',
            'meta_footer_text' => 'string',
            'template_category_id' => 'int',
            'whatsapp_attachment_id' => 'int',
            'meta_body_variables_json' => 'string',
            'meta_header_variables_json' => 'string',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format')
        ];
        parent::__construct($attributes);
    }


    public function whatsAppMetaAPIConnections()
    {
        return $this->hasMany(WhatsAppMetaAPIConnection::class, 'waba_id', 'waba_id');
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function attachment()
    {
        return $this->belongsTo(WhatsAppAttachment::class, 'whatsapp_attachment_id');
    }


    public function templateCategory()
    {
        return $this->belongsTo(templateCategory::class);
    }


    public function getAttachmentAttribute(): ?WhatsAppAttachment
    {
        return $this->getModelRelationFromCache('attachment', 'WhatsAppAttachment', $this->whatsapp_attachment_id);
    }


    public function whatsAppAttachment()
    {
        return $this->belongsTo(WhatsAppAttachment::class, 'whatsapp_attachment_id');
    }


    public function getWhatsAppAttachmentAttribute(): ?WhatsAppAttachment
    {
        return $this->getModelRelationFromCache(
            'whatsAppAttachment', 'WhatsAppAttachment', $this->whatsapp_attachment_id
        );
    }

    public function WAutomationsSequenceStep()
    {
        return $this->hasMany(WAutomationSequenceStep::class, 'send_whatsapp_template_id');
    }


    public function WAutomationsProposalResendRule()
    {
        return $this->hasMany(WAutomationProposalResendRule::class, 'send_whatsapp_template_id');
    }


    public function getMetaCompleteTextJson()
    {
        $texts = [];
        if (!empty($this->meta_header_text)) {
            $texts['header'] = $this->meta_header_text;
        }
        $texts['body'] = $this->body;
        if (!empty($this->meta_footer_text)) {
            $texts['footer'] = $this->meta_footer_text;
        }
        return json_encode($texts, JSON_UNESCAPED_UNICODE);
    }

}
