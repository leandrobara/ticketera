<?php

namespace App\Models;


use App\Helpers\PhonesHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\ModelCache\BaseModelRelationCache;
use App\Models\Traits\ModelCache\ClientModelRelationCache;


class LeadContactPhone extends Model
{

    use SoftDeletes, HasFactory, BaseModelRelationCache, ClientModelRelationCache;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'LeadsContactsPhones';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'order' => 'int',
            'lead_id' => 'int',
            'hash' => 'string',
            'phone' => 'string',
            'client_id' => 'int',
            'lead_contact_id' => 'int',
            'normalized_hash' => 'string',
            'normalized_phone' => 'string',
            'lead_ids_where_repeated' => 'array',
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


    public function leadContact()
    {
        return $this->belongsTo(LeadContact::class, 'lead_contact_id');
    }


    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }


    public function getWhatsAppFormattedPhoneAttribute(): string
    {
        $formattedPhone = $this->getWhatsAppFormattedPhone(
            $this->client->country_code, $this->client->clientSettings
        );
        return $formattedPhone;
    }


    public function getWhatsAppFormattedPhone(
        ?string $countryCode = null,
        ?ClientSettings $clientSettings = null
    ): string {
        if (!$countryCode) {
            $countryCode = $this->client->country_code;
        }
        if (!$clientSettings) {
            $clientSettings = $this->client->clientSettings;
        }
        return resolve(PhonesHelper::class)->formatPhoneForWhatsAppWithSettings(
            $this->phone, $countryCode, $clientSettings
        );
    }


    public static function buildHash(string $phone): string
    {
        return md5($phone);
    }


    public static function buildNormalizedHash(string $normalizedPhone): string
    {
        return md5($normalizedPhone);
    }

}
