<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class EmailTemplate extends Model
{

    use SoftDeletes, HasFactory;

    protected $table = 'EmailTemplates';
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $casts = [];


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'user_id' => 'int',
            'body' => 'string',
            'client_id' => 'int',
            'subject' => 'string',
            'is_proposal' => 'boolean',
            'is_automation' => 'boolean',
            'template_category_id' => 'int',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format')
        ];

        parent::__construct($attributes);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function attachments()
    {
        return $this->belongsToMany(Attachment::class, 'EmailTemplates_Attachments');
    }


    public function templateCategory()
    {
        return $this->belongsTo(templateCategory::class);
    }


    public function automationsNewLead()
    {
        $id = $this->id;
        return $this->hasMany(AutomationNewLead::class, 'client_id', 'client_id')
            ->where('auto_reply_email_template_id', $id)
            ->orWhere('auto_reply_ask_phone_email_template_id', $id)
        ;
    }


    public function automationsEmailSendStep()
    {
        return $this->hasMany(AutomationEmailSendStep::class, 'send_email_template_id');
    }


    public function automationsProposalResendRule()
    {
        return $this->hasMany(AutomationProposalResendRule::class, 'send_email_template_id');
    }


}
