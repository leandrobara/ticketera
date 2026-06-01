<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class User extends Model
{

    use SoftDeletes, HasFactory;

    protected $casts = [];
    public $timestamps = true;
    protected $table = 'Users';
    protected $guarded = ['id'];
    protected $hidden = ['password'];
    protected $connection = 'mysql';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'type' => 'string',
            'name' => 'string',
            'phone' => 'string',
            'email' => 'string',
            'client_id' => 'int',
            'username' => 'string',
            'password' => 'string',
            'enabled' => 'boolean',
            'api_token' => 'string',
            'wapi_route' => 'string',
            'last_name' => 'string',
            'email_sign' => 'string',
            'wapi_engine' => 'string',
            'is_superuser' => 'boolean',
            'wapi_is_synced' => 'boolean',
            'email_from_name' => 'string',
            'wapi_is_paused' => 'boolean',
            'email_from_address' => 'string',
            'email_is_verified' => 'boolean',
            'superuser_password' => 'string',
            'superuser_username' => 'string',
            'email_sign_enabled' => 'boolean',
            'reset_password_token' => 'string',
            'google_gmail_app_name' => 'string',
            'wapi_pause_delay_seconds' => 'int',
            'is_clienty_admin_user' => 'boolean',
            'enable_emails_reception' => 'boolean',
            'wap_sender_retry_delay_days' => 'int',
            'wapi_session_phone_number' => 'string',
            'wap_sender_session_phone_number' => 'string',
            'enable_new_lead_browser_alert' => 'boolean',
            'enabled_export_leads_emails_reception' => 'boolean',
            'enabled_delete_leads_emails_reception' => 'boolean',
            'enable_alert_expiration_browser_alert' => 'boolean',
            'enable_alert_proposal_interaction_alert' => 'boolean',
            'api_token_expiration_date' => 'datetime:' . config('app.datetime_format'),
            'api_token_expiration_date' => 'datetime:' . config('app.datetime_format'),
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format')
        ];
        parent::__construct($attributes);
    }


    public function leads()
    {
        return $this->hasMany(Lead::class);
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function googlePeopleAPIUserToken()
    {
        return $this->hasOne(GoogleAPIUserToken::class)->where('type', GoogleAPIUserToken::PEOPLE_API_TYPE);
    }


    public function whatsAppMetaAPIConnection()
    {
        return $this->hasOne(WhatsAppMetaAPIConnection::class);
    }


    public function googleGmailAPIUserToken()
    {
        return $this->hasOne(GoogleAPIUserToken::class)->where('type', GoogleAPIUserToken::GMAIL_API_TYPE);
    }


    public function news()
    {
        return $this->hasMany(News::class);
    }


    public function automationsNewLeadWhereAssigned()
    {
        return $this->hasMany(AutomationNewLead::class, 'client_id', 'client_id')
            ->whereJsonContains('assign_user_ids', $this->id)
        ;
    }


    public function setApiToken()
    {
        if (!$this->api_token || $this->is_api_token_expired) {
            $this->api_token = Str::orderedUuid();
        }
    }


    public function getIsApiTokenExpiredAttribute(): bool
    {
        $expirationDate = $this->api_token_expiration_date;
        if (!$expirationDate) {
            return true;
        }

        $dateNow = Carbon::now();
        $isExpired = $dateNow > $expirationDate;
        return $isExpired;
    }


    public function getFullNameAttribute()
    {
        if ($this->last_name) {
            return "{$this->name} {$this->last_name}";
        }
        return $this->name;
    }


    public function isGodixitalUser()
    {
        return $this->client_id == 234;
    }

}
