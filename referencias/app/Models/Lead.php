<?php

namespace App\Models;

use DateTime;
use Exception;
use Illuminate\Support\Str;
use App\Helpers\StringHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Exceptions\Models\LeadBuildHashException;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Lead extends Model
{

    use SoftDeletes, HasFactory;

    protected $casts = [];
    public $timestamps = true;
    protected $table = 'Leads';
    protected $guarded = ['id'];
    protected $connection = 'mysql';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'user_id' => 'int',
            'quality' => 'int',
            'quality' => 'int',
            'client_id' => 'int',
            'status_id' => 'int',
            'method' => 'string',
            'fbclid' => 'string',
            'landing_id' => 'int',
            'company' => 'string',
            'website' => 'string',
            'utm_source' => 'string',
            'utm_medium' => 'string',
            'landed_url' => 'string',
            'leads_query_id' => 'int',
            'utm_content' => 'string',
            'other_fields' => 'array',
            'utm_campaign' => 'string',
            'utm_keywords' => 'string',
            'country_code' => 'string',
            'lead_lead_number' => 'int',
            'leads_lead_number' => 'int',
            'is_bulk_created' => 'boolean',
            'is_wap_bot_chat' => 'boolean',
            'is_whatsapp_form' => 'boolean',
            'is_from_make_app' => 'boolean',
            'is_facebook_form' => 'boolean',
            'tracking_parameters' => 'array',
            'is_from_zapier_app' => 'boolean',
            'acquisition_channel_id' => 'int',
            'is_from_integration_api' => 'int',
            'is_manually_created' => 'boolean',
            'is_from_zapier_webhook' => 'boolean',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format'),
            'lead_created_at' => 'datetime:' . config('app.datetime_format'),
            'search_indexed_at' => 'datetime:' . config('app.datetime_format'),
            'last_status_changed_at' => 'datetime:' . config('app.datetime_format'),
        ];

        parent::__construct($attributes);
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function acquisitionChannel()
    {
        return $this->belongsTo(AcquisitionChannel::class);
    }


    public function landing()
    {
        return $this->belongsTo(Landing::class);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function status()
    {
        return $this->belongsTo(Status::class);
    }


    public function emails()
    {
        return $this->hasMany(Email::class);
    }


    public function leadContacts()
    {
        return $this->hasMany(LeadContact::class)->orderBy('order');
    }


    public function leadSales()
    {
        return $this->hasMany(LeadSale::class);
    }


    public function proposalsInfo()
    {
        return $this->hasMany(ProposalInfo::class);
    }


    public function lastProposalInfo()
    {
        return $this->hasOne(ProposalInfo::class)->orderBy('sent_date', 'DESC')->limit(1);
    }
    

    public function leadContactEmails()
    {
        return $this->hasMany(LeadContactEmail::class);
    }


    public function leadContactPhones()
    {
        return $this->hasMany(LeadContactPhone::class);
    }

    public function whatsAppSendingMessages()
    {
        return $this->hasMany(WhatsAppSendingMessage::class);
    }


    public function leadNotificationEmail()
    {
        return $this->hasOne(LeadNotificationEmail::class);
    }


    public function lastUserChangeLeadNotificationEmail()
    {
        return $this->hasOne(LeadNotificationEmail::class)->where('reason', 'lead_user_change')->orderBy('id', 'desc');
    }


    public function leadNotificationWhatsAppMessage()
    {
        return $this->hasOne(LeadNotificationWhatsAppMessage::class);
    }


    public function mainLeadContact()
    {
        return $this->hasOne(LeadContact::class)->where('is_main', true);
    }


    public function googleAPIUserContacts()
    {
        return $this->hasMany(GoogleAPIUserContact::class);
    }


    public function getGoogleAPIUserContact(User $user)
    {
        return $this->googleAPIUserContacts->where('user_id', $user->id)->first();
    }


    public function leadCustomFieldsValues()
    {
        return $this->hasMany(LeadCustomFieldValue::class);
    }


    public function leadAttachments()
    {
        return $this->hasMany(LeadAttachment::class);
    }


    public function getCustomFieldValueByName(string $fieldName): ?string
    {
        $customField = LeadCustomField::where('client_id', $this->client_id)->where('name', $fieldName)->first();
        if (!$customField) {
            return null;
        }
        $customFieldValue = $this->leadCustomFieldsValues()->where('lead_custom_field_id', $customField->id)->first();
        return $customFieldValue ? $customFieldValue->value : null;
    }


    public function getMainPhoneAttribute()
    {
        $leadContactPhone = isset($this->mainLeadContact->leadContactPhones)
            ? $this->mainLeadContact->leadContactPhones->first()
            : null
        ;
        return $leadContactPhone ? $leadContactPhone->phone : null;
    }


    public function getMainEmailAttribute()
    {
        $leadContactEmail = isset($this->mainLeadContact->leadContactEmails)
            ? $this->mainLeadContact->leadContactEmails->first()
            : null
        ;
        return $leadContactEmail ? $leadContactEmail->email : null;
    }


    public function getOtherFieldsStringAttribute()
    {
        if (!$this->other_fields) {
            return '';
        }
        $strArr = [];
        if (!is_iterable($this->other_fields)) {
            return '';
        }
        foreach ($this->other_fields as $field) {
            $strArr[] = "{$field['name']}: {$field['value']}";
        }
        return implode(' - ', $strArr);
    }


    public function getMainFullNameAttribute()
    {
        $contact = $this->mainLeadContact;
        $name = $contact->name;
        $lastName = $contact->last_name;
        if ($lastName) {
            return $name . ' ' . $lastName;
        }
        return $name;
    }


    public function getFormattedHtmlChatMessageAttribute()
    {
        $msg = $this->message;
        $msg = str_replace(PHP_EOL, '<br />', $msg);
        $msg = Str::replaceFirst('Asesor: ', '<b>Asesor: </b>', $msg);
        $msg = str_replace('<br />Asesor: ', '<br /><b>Asesor: </b>', $msg);
        $msg = str_replace('<br />Cliente: ', '<br /><b>Cliente: </b>', $msg);
        return $msg;
    }


    public function notes()
    {
        return $this->hasMany(Note::class);
    }


    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'Leads_Tags');
    }


    public function tasks()
    {
        return $this->hasMany(Task::class);
    }


    public function emailDraft()
    {
        return $this->hasOne(EmailDraft::class);
    }


    public function lastWhatsAppSendingMessage()
    {
        return $this->hasOne(WhatsAppSendingMessage::class)->orderBy('sent_date', 'desc');
    }


    public function toSearchableArray(): array
    {
        $emails = [];
        $phones = [];
        $fullNames = [];
        $normalizedPhones = [];
        $leadCustomFieldsArr = [];

        foreach ($this->leadContacts as $leadContact) {
            $fullNames[] = $leadContact->full_name;

            foreach ($leadContact->leadContactPhones as $leadContactPhone) {
                $phones[] = $leadContactPhone->phone;
                $normalizedPhones[] = filter_var(
                    str_replace(['-', '+', '.'], '', $leadContactPhone->phone), FILTER_SANITIZE_NUMBER_INT
                );
            }
            foreach ($leadContact->leadContactEmails as $leadContactEmail) {
                $emails[] = $leadContactEmail->email;
            }

            $leadCustomFieldsValues = $this->leadCustomFieldsValues()->with('leadCustomField')->get();
            $leadCustomFieldsArr = $leadCustomFieldsValues->map(function ($v) {
                return [strtolower($v->value), strtolower($v->leadCustomField->name) . ': ' . strtolower($v->value)];
            })->flatten()->toArray();
        }

        $stringHelper = resolve(StringHelper::class);
        foreach ($emails as $email) {
            $grams = $stringHelper->tokenizeEmail($email);
            $emails = array_unique(array_merge($emails, $grams));
        }
        foreach ($normalizedPhones as $phone) {
            $grams = $stringHelper->tokenizeString($phone, 4);
            $grams2 = $stringHelper->tokenizeString($phone, 5);
            $normalizedPhones = array_unique(array_merge($normalizedPhones, $grams, $grams2));
        }
        foreach ($phones as $phone) {
            $grams = $stringHelper->tokenizePhone($phone);
            $phones = array_unique(array_merge($phones, $grams));
        }

        $fields =  [
            'id' => (string) $this->id,
            'message' => $this->message,
            'company' => $this->company,
            'client_id' => $this->client_id,
            'utm_keywords' => $this->utm_keywords,
            'notes' => $this->notes->pluck('text')->toArray(),
            'created_at' => $this->created_at->getTimestamp(),
            'phones' => collect($phones)->filter()->unique()->values()->toArray(),
            'emails' => collect($emails)->filter()->unique()->values()->toArray(),
            'full_names' => collect($fullNames)->filter()->unique()->values()->toArray(),
            'normalized_phones' => collect($normalizedPhones)->filter()->unique()->values()->toArray(),
            'lead_custom_fields' => collect($leadCustomFieldsArr)->filter()->unique()->values()->toArray(),
        ];

        return $fields;
    }


    public static function buildHash(
        array $leadAttrs,
        array $mainLeadContactAttrs,
        array $opts = []
    ): string {
        $method = $leadAttrs['method'] ?? '';
        $company = $leadAttrs['company'] ?? '';
        $message = $leadAttrs['message'] ?? '';
        $name = $mainLeadContactAttrs['name'] ?? '';
        $email = $mainLeadContactAttrs['email'] ?? '';
        $phone = $mainLeadContactAttrs['phone'] ?? '';
        $lastName = $mainLeadContactAttrs['last_name'] ?? '';

        if (!$name && !$email && !$phone && !$lastName) {
            throw new LeadBuildHashException('Lead::BuildHash - Name or email or phone or lastName must be present');
        }
        if (!$method) {
            throw new LeadBuildHashException('Lead::BuildHash - Method must not be empty');
        }

        $otherFields = json_encode($leadAttrs['other_fields'] ?? []);
        $key = $name . $lastName . $email . $phone . $method . $company . $message . $otherFields;
        $hash = md5($key);
        return $hash;
    }


    public static function buildDeletedHash(string $leadHash): string
    {
        $rand = (string) (new DateTime())->getTimestamp();
        $rand = substr($rand, -5);
        return substr("DEL_{$rand}_{$leadHash}", 0, 32);
    }

}
