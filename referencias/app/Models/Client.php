<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\ModelCache\BaseModelRelationCache;


class Client extends Model
{

    use SoftDeletes, BaseModelRelationCache, HasFactory;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'Clients';
    protected $connection = 'mysql';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'name' => 'string',
            'emails' => 'array',
            'manager_id' => 'int',
            'version' => 'string',
            'timezone' => 'string',
            'enabled' => 'boolean',
            'subdomain' => 'string',
            'country_code' => 'string',
            'leads_client_id' => 'int',
            'business_area' => 'string',
            'google_ads_id' => 'string',
            'contract_type' => 'string',
            'client_settings_id' => 'int',
            'email_from_name' => 'string',
            'enabled_to_receive_leads' => 'boolean',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format')
        ];
        parent::__construct($attributes);
    }


    public function clientSettings()
    {
        return $this->belongsTo(ClientSettings::class);
    }

    // Uso cache!
    public function getClientSettingsAttribute(): ClientSettings
    {
        $clientId = $this->id;
        $clientSettingsId = $this->client_settings_id;
        return $this->getModelRelationFromCache('clientSettings', 'ClientSettings', $clientSettingsId, $clientId);
    }



    public function managers()
    {
        return $this->belongsTo(Manager::class);
    }


    public function users()
    {
        return $this->hasMany(User::class);
    }


    public function wapBots()
    {
        return $this->hasMany(WapBot::class);
    }


    public function enabledUsers()
    {
        return $this->hasMany(User::class)->where('enabled', true);
    }


    public function whatsAppMetaAPIConnections()
    {
        return $this->hasMany(WhatsAppMetaAPIConnection::class);
    }


    public function mainUser()
    {
        return $this->hasOne(User::class)->orderBy('id')->where('type', 'admin')->where('enabled', true);
    }


    public function acquisitionChannels()
    {
        return $this->hasMany(AcquisitionChannel::class);
    }


    public function tags()
    {
        return $this->hasMany(Tag::class);
    }


    public function leads()
    {
        return $this->hasMany(Lead::class);
    }


    public function leadsCustomFields()
    {
        return $this->hasMany(LeadCustomField::class);
    }


    public function news()
    {
        return $this->hasMany(News::class);
    }


    public function landings()
    {
        return $this->hasMany(Landing::class);
    }


    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }


    public function hasGodixitalContract()
    {
        return $this->contract_type == 'godixital';
    }


    public function isClienty()
    {
        return $this->subdomain == 'clienty';
    }

}
