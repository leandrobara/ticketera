<?php

namespace App\Models;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class LeadContactEmail extends Model
{

    use SoftDeletes, HasFactory;

    const EMAIL_VALIDATOR_NULL_STATUS = 'null';
    const EMAIL_VALIDATOR_VALID_STATUS = 'valid';
    const EMAIL_VALIDATOR_SKIPPED_STATUS = 'skipped';
    const EMAIL_VALIDATOR_INVALID_STATUS = 'invalid';
    
    const MAILS_SO_VALIDATION = 'Mails.So';
    const IPQUALITYSCORE_VALIDATION = 'IPQualityScore';
    const EMAIL_VALIDATOR_VALIDATION = 'EmailValidator';
    const EMAIL_LIST_VERIFY_VALIDATION = 'EmailListVerify';

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'LeadsContactsEmails';



    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'order' => 'int',
            'lead_id' => 'int',
            'email' => 'string',
            'client_id' => 'int',
            'bounced' => 'boolean',
            'is_valid' => 'boolean',
            'validations' => 'array',
            'complained' => 'boolean',
            'lead_contact_id' => 'int',
            'unsubscribed' => 'boolean',
            'lead_ids_where_repeated' => 'array',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format')
        ];

        parent::__construct($attributes);
    }


    public function setEmailValidatorValidationStatus(string $status): LeadContactEmail
    {
        $this->setValidationStatus(self::EMAIL_VALIDATOR_VALIDATION, $status);
        return $this;
    }


    public function setIPQualityScoreValidationStatus(string $status): LeadContactEmail
    {
        $this->setValidationStatus(self::IPQUALITYSCORE_VALIDATION, $status);
        return $this;
    }


    public function setMailsSoValidationStatus(string $status): LeadContactEmail
    {
        $this->setValidationStatus(self::MAILS_SO_VALIDATION, $status);
        return $this;
    }


    public function setEmailListVerifyValidationStatus(string $status): LeadContactEmail
    {
        $this->setValidationStatus(self::EMAIL_LIST_VERIFY_VALIDATION, $status);
        return $this;
    }


    protected function setValidationStatus(string $key, string $status)
    {
        $opts = [
            self::EMAIL_VALIDATOR_NULL_STATUS,
            self::EMAIL_VALIDATOR_VALID_STATUS,
            self::EMAIL_VALIDATOR_SKIPPED_STATUS,
            self::EMAIL_VALIDATOR_INVALID_STATUS,
        ];
        if (!Str::contains($status, $opts)) {
            throw new Exception('invalid_validation_status');
        }
        if (!$this->validations) {
            $this->validations = [];
        }
        
        $this->validations = array_merge($this->validations, [$key => $status]);
    }


    public function wasValidatedWithMailsSo(): bool
    {
        $key = self::MAILS_SO_VALIDATION;
        return array_key_exists($key, $this->validations ?? []);
    }

    public function wasValidatedWithIPQualityScore(): bool
    {
        $key = self::IPQUALITYSCORE_VALIDATION;
        return array_key_exists($key, $this->validations ?? []);
    }

    public function wasValidatedWithEmailListVerify(): bool
    {
        $key = self::EMAIL_LIST_VERIFY_VALIDATION;
        return array_key_exists($key, $this->validations ?? []);
    }

    public function wasValidatedWithEmailValidator(): bool
    {
        $key = self::EMAIL_VALIDATOR_VALIDATION;
        return array_key_exists($key, $this->validations ?? []);
    }


    public function isHotmailAddress(): bool
    {
        $opts = ['hotmail.', 'live.', 'outlook.'];
        return Str::contains($this->email, $opts);
    }


    public function isYahooAddress(): bool
    {
        $opts = ['yahoo.'];
        return Str::contains($this->email, $opts);
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }


    public function leadContact()
    {
        return $this->belongsTo(LeadContact::class, 'lead_contact_id');
    }


    public static function buildHash(string $email): string
    {
        return md5($email);
    }


    public function buildExternalCustomId(): string
    {
        $leadId = $this->lead_id;
        $clientId = $this->client_id;
        return 'CID_' . $clientId . '_LID_' . $leadId. '_LCEID_' . $this->id;
    }


    public function buildExternalCustomMetadata(): string
    {
        return json_encode([
            'lead' => [
                'id' => $this->lead_id,
            ],
            'client' => [
                'id' => $this->client_id,
            ],
            'leadContactEmail' => [
                'id' => $this->id,
            ],
        ]);
    }

}
