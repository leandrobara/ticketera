<?php

namespace App\Models;

use App\DTO\MailerEmailDTO;
use App\DTO\MailerMassiveEmailDTO;
use App\Models\EmailNotificationLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Email extends Model
{

    use SoftDeletes, HasFactory;

    protected $casts = [];
    public $timestamps = true;
    protected $table = 'Emails';
    protected $guarded = ['id'];

    protected $mailerDTO = null;
    protected $massiveSentDTO = null;


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'string',
            'cc' => 'string',
            'lead_id' => 'integer',
            'user_id' => 'integer',
            'client_id' => 'integer',
            'external_id' => 'integer',
            'is_proposal' => 'boolean',
            'automation_log_id' => 'integer',
            'external_custom_id' => 'string',
            'external_massive_id' => 'string',
            'lead_contact_email_id' => 'integer',
            'individual_lead_send_hash' => 'string',
            'external_custom_massive_id' => 'string',
            'send_date'  => 'datetime:' . config('app.datetime_format'),
            'sent_date'  => 'datetime:' . config('app.datetime_format'),
            'opened_date'  => 'datetime:' . config('app.datetime_format'),
            'bounced_date'  => 'datetime:' . config('app.datetime_format'),
            'migrated_date'  => 'datetime:' . config('app.datetime_format'),
            'cancelled_date'  => 'datetime:' . config('app.datetime_format'),
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format'),
        ];
        parent::__construct($attributes);
    }


    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    // Points to all Emails that are part of an individual lead sending.
    public function leadSendIndividualEmails()
    {
        return $this->hasMany(Email::class, 'individual_lead_send_hash', 'individual_lead_send_hash');
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function automationLog()
    {
        return $this->belongsTo(AutomationLog::class);
    }


    public function leadContactEmail()
    {
        return $this->belongsTo(LeadContactEmail::class)->withTrashed();
    }


    public function proposalsInfo()
    {
        return $this->hasMany(ProposalInfo::class);
    }


    public function openedEmailNotificationLogs()
    {
        return $this->hasMany(EmailNotificationLog::class)->where('event', 'open');
    }


    public function setMailerDTO(MailerEmailDTO $mailerDTO): Email
    {
        $this->mailerDTO = $mailerDTO;
        return $this;
    }


    public function getMailerDTO(): ?MailerEmailDTO
    {
        return $this->mailerDTO;
    }


    public function getMailerMassiveDTO(): ?MailerMassiveEmailDTO
    {
        return $this->massiveSentDTO;
    }


    public function setMailerMassiveDTO(MailerMassiveEmailDTO $massiveSentDTO): Email
    {
        $this->massiveSentDTO = $massiveSentDTO;

        return $this;
    }


    public static function buildMassiveSendingId()
    {
        return uniqid(1);
    }

}
