<?php

namespace App\Http\Resources\Views\NewsNotification;

use App\Http\Resources\LeadResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\ClientResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class NewsNotificationItemResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'news_id' => $this->news_id,
            'news_type' => $this->news->type,
            'news_title' => $this->news->title,
            'force_modal_show' => $this->news->force_modal_show,
            'is_notification_viewed' => $this->is_notification_viewed,
        ];
        return $response;
    }

}
