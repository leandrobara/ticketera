<?php

namespace App\DTO\WAPI;


class WAPIMessageMediaDTO
{

    public function __construct(public string $data, public ?string $fileSize, public string $mimeType)
    {
    }

}
