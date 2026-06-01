<?php

namespace App\DTO\WhatsAppSenderExtension;


class WhatsAppSenderPopUpInfoDTO
{

    public $appVersion;
    public $minimumAppVersion;
    public $lastSending = null;
    public $currentSending = null;
    public $dailyUsedQuota = null;
    public $dailyUserQuota = null;
    public $quotaPerSending = null;
    public $dailyRemainingQuota = null;


    public function __construct(int $appVersion, int $minimumAppVersion)
    {
        $this->appVersion = $appVersion;
        $this->minimumAppVersion = $minimumAppVersion;
    }

    public function isVersionUpToDate(): bool
    {
        return $this->appVersion >= $this->minimumAppVersion;
    }

}
