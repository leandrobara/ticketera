<?php

namespace App\DTO;


class EmailSystemScheduleParametersDTO
{

    public $body = null;
    public $subject = null;
    public $sendDate = null;
    public $appCustomId = null;
    public $appCustomMetadata = null;


    public static function build(array $arr)
    {
        $dto = new EmailSystemScheduleParametersDTO();

        $dto->body = $arr["body"] ?? null;
        $dto->subject = $arr["subject"] ?? null;
        $dto->sendDate = $arr["sendDate"] ?? null;
        $dto->appCustomId = $arr["appCustomId"] ?? null;
        $dto->appCustomMetadata = $arr["appCustomMetadata"] ?? null;

        return $dto;
    }
}
