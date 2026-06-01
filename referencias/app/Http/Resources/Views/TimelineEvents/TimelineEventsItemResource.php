<?php

namespace App\Http\Resources\Views\TimelineEvents;

use DateTime;
use App\DTO\GoogleAPI\GoogleAPIGmailMessageDTO;
use Illuminate\Http\Resources\Json\JsonResource;


class TimelineEventsItemResource extends JsonResource
{

    public function toArray($request)
    {
        if (is_a($this->resource, GoogleAPIGmailMessageDTO::class)) {
            $response = [
                'event' => 'google_gmail_message',
                'info' => $this->resource->toArray(),
                'createdAt' => $this->formatDate($this->resource->sentDate->getTimestamp()),
            ];
        } else {
            $response = [
                'event' => $this->resource['event'],
                'info' => $this->prepareInfo($this->resource['log']),
                'createdAt' => $this->formatDate($this->resource['createdAtTs']),
            ];
        }
        return $response;
    }


    public function prepareInfo(array $logInfo): array
    {
        $logInfo = $this->formatTaskData($logInfo);
        $logInfo = $this->formatEmailData($logInfo);
        return $logInfo;
    }


    protected function formatTaskData(array $logInfo): array
    {
        if (isset($logInfo['task'])) {
            $logInfo['task']['limitDate'] = $this->formatDate($logInfo['task']['limitDateTs']);
            unset($logInfo['task']['limitDateTs']);
        }
        return $logInfo;
    }


    protected function formatEmailData(array $logInfo): array
    {
        if (isset($logInfo['email']['emailModel'])) {
            $emailModel = $logInfo['email']['emailModel'];
            $mailerDto = $emailModel->getMailerDTO();

            $logInfo['email']['emailModel'] = $emailModel->toArray();
            if ($mailerDto) {
                $logInfo['email']['emailModel']['mailerInfo'] = $mailerDto->toArray();
            }
        }
        return $logInfo;
    }


    public function formatDate($timestamp)
    {
        return (new DateTime())->setTimestamp($timestamp)->format(config('app.datetime_format'));
    }

}
