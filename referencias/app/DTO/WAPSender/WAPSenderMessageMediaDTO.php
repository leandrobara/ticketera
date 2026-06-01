<?php

namespace App\DTO\WAPSender;


class WAPSenderMessageMediaDTO
{

    public function __construct(public string $data, public ?string $fileSize, public string $mimeType)
    {
    }

}
