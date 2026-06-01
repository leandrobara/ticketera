<?php
namespace App\DTO\FacebookPage;

use DateTime;


class ClientFacebookPageLeadGenDTO
{

    public $adId = null;
    public $pageId = null;
    public $formId = null;
    public $leadgenId = null;
    public $adgroupId = null;
    public $createdTime = null;


    public function __construct(array $data)
    {
        $this->adId = $data['ad_id'] ?? null;
        $this->pageId = $data['page_id'] ?? null;
        $this->formId = $data['form_id'] ?? null;
        $this->adgroupId = $data['adgroup_id'] ?? null;
        $this->leadgenId = $data['leadgen_id'] ?? null;
        $this->createdTime = (new DateTime())->setTimestamp($data['created_time']) ?? null;
    }


    public static function build(array $data)
    {
        $dto = new ClientFacebookPageLeadGenDTO($data);
        return $dto;
    }

}
