<?php

namespace App\DTO\WapBot;

use App\Models\WapBot;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use App\Models\MongoDB\WapBot\WapBotConversation;


class NewWapBotLeadDTO
{
    protected $fbclid = null;
    protected $utmSource = null;
    protected $utmMedium = null;
    protected $utmContent = null;
    protected $utmCampaign = null;
    protected $utmKeywords = null;

    protected $client = null;
    protected $method = null;
    protected $company = null;
    protected $message = null;
    protected $otherFields = [];
    protected $referralData = [];
    protected $serializedFields = null;
    protected $mainLeadContactName = null;
    protected $mainLeadContactEmail = null;
    protected $mainLeadContactPhone = null;
    protected $mainLeadContactLastName = null;


    public function __construct(WapBot $wapBot, WapBotConversation $wapBotConversation)
    {
        $leadAttrs = [];
        $this->method = 'chat';
        $this->client = $wapBot->client;

        $leadParams = $wapBotConversation->extractedParameters['leadInfo'] ?? [];
        
        $this->company = $leadParams['empresa'] ?? null;
        $this->mainLeadContactEmail = $leadParams['email'] ?? null;
        $this->mainLeadContactName = $leadParams['nombre'] ?? null;
        $this->mainLeadContactLastName = $leadParams['apellido'] ?? null;

        $this->referralData = $wapBotConversation->referralData ?? [];
        $this->message = $wapBotConversation->getConversationTranscript();
        $this->mainLeadContactPhone = $wapBotConversation->customerPhoneNumber;
        
        $this->otherFields = collect($leadParams)
            ->map(function ($value, $key) use ($leadParams) {
                return ['name' => $key, 'value' => Arr::get($leadParams, $key, null)];
            })
            ->values()
            ->toArray()
        ;
        $this->serializedFields = json_encode($leadParams);
    }


    public function getLeadAttrs(): array
    {
        $trackingParameters = $this->flattenReferralData($this->referralData);
        $leadAttrs = [
            'method' => $this->method,
            'is_wap_bot_chat' => true,
            'fbclid' => $this->fbclid,
            'company' => $this->company,
            'message' => $this->message,
            'client_id' => $this->client->id,
            'utm_source' => $this->utmSource,
            'utm_medium' => $this->utmMedium,
            'utm_content' => $this->utmContent,
            'utm_campaign' => $this->utmCampaign,
            'utm_keywords' => $this->utmKeywords,
            'seriealized_fields' => $this->serializedFields,
            'other_fields' => $this->getFormattedOtherFields(),
            'tracking_parameters' => $trackingParameters ?: null,
        ];
        return $leadAttrs;
    }


    public function getMainLeadContactAttrs(): array
    {
        return [
            'name' => $this->mainLeadContactName,
            'email' => $this->mainLeadContactEmail,
            'phone' => $this->mainLeadContactPhone,
            'last_name' => $this->mainLeadContactLastName,
        ];
    }


    protected function getFormattedOtherFields()
    {
        $i = 1;
        $formattedOtherFields = [];
        foreach ($this->otherFields as $otherFieldArr) {
            $formattedOtherFields["$i"] = $otherFieldArr;
            $i++;
        }
        return $formattedOtherFields;
    }


    protected function flattenReferralData(mixed $referralData): array
    {
        if (!is_array($referralData) || empty($referralData)) {
            return [];
        }
        return Arr::dot($referralData);
    }

}
