<?php

namespace App\DTO\WhatsAppMetaAPI;

use App\Models\WhatsAppMetaAPIConnection;


class WhatsAppMetaAPIPopulatedConnectionDTO
{

    // [..., 'WABAs' => ['phoneNumbers' => [...]]]
    public array | null $enabledWABAs;

    public ?string $metaError = null;
    public ?array $associatedMetaWABAData = null;
    public WhatsAppMetaAPIConnection $wapMetaConnModel;
    public ?array $associatedMetaPhoneNumberData = null;


    public function __construct(WhatsAppMetaAPIConnection $wapMetaConnModel)
    {
        $this->wapMetaConnModel = $wapMetaConnModel;
    }


    public function modelHasAssociatedMetaWABA(): bool
    {
        return $this->wapMetaConnModel->waba_id ? true : false;
    }

    public function modelHasAssociatedMetaPhoneNumber(): bool
    {
        return $this->wapMetaConnModel->phone_number_id ? true : false;
    }


    public function appendEnabledMetaAPIData(array $waba, array $phoneNumbers): void
    {
        $this->enabledWABAs[] = array_merge($waba, ['phoneNumbers' => $phoneNumbers]);
    }

}
