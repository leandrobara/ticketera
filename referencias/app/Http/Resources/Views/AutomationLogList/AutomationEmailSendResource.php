<?php

namespace App\Http\Resources\Views\AutomationLogList;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class AutomationEmailSendResource extends JsonResource
{
    use VisibleFieldsFilter;

    public function toArray($request)
    {
        // this is restricted to prevent data leakage
        $visibleFields = $this->getFieldsToShow();
        if (!$visibleFields) {
            $response = [
                'id' => $this->resource->id,
                'name' => $this->resource->name,
                'created_at' => $this->resource->created_at,
                'trigger_type' => $this->resource->trigger_type,
                'do_not_send_weekends' => $this->resource->do_not_send_weekends,
            ];
        } else {
            $response = $this->resource->attributesToArray();
        }

        $response = $this->loadTags($response);
        $response = $this->cancellingStatus($response);
        $response = $this->triggeringStatus($response);
        $response = $this->loadCancellingTags($response);

        $response = $this->filterVisibleFields($response);

        return $response;
    }


    private function loadTags(array $response)
    {
        $triggeringTags = $this->resource->getTriggeringTagsAttribute(['withTrashed' => true]);
        if (empty($triggeringTags)) {
            $response['triggeringTags'] = null;
            return $response;
        }
        $response['triggeringTags'] = $triggeringTags;
        return $response;
    }


    private function cancellingStatus(array $response)
    {
        $cancellingStatus = $this->resource->cancellingStatus;
        if (empty($cancellingStatus)) {
            $response['cancellingStatus'] = null;
            return $response;
        }
        $response['cancellingStatus'] = $cancellingStatus;
        return $response;
    }


    private function triggeringStatus(array $response)
    {
        $triggeringStatus = $this->resource->getTriggeringStatusAttribute(['withTrashed' => true]);
        if (empty($triggeringStatus)) {
            $response['triggeringStatus'] = null;
            return $response;
        }
        $response['triggeringStatus'] = $triggeringStatus;
        return $response;
    }


    private function loadCancellingTags(array $response)
    {
        $cancellingTags = $this->resource->cancellingTags;
        if (empty($cancellingTags)) {
            $response['cancellingTags'] = null;
            return $response;
        }
        $response['cancellingTags'] = $cancellingTags;
        return $response;
    }

}
