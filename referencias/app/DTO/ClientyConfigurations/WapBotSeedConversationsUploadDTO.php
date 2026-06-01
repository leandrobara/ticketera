<?php

namespace App\DTO\ClientyConfigurations;

use Illuminate\Support\Collection;


class WapBotSeedConversationsUploadDTO
{

    public function __construct(
        public readonly Collection $csvRows
    ) {
    }


    public static function fromArray(array $data): self
    {
        $csvRows = collect($data)->map(function ($row) {
            return [
                'customerPhoneNumber' => $row['customerPhoneNumber'],
                'lastActivityTimestamp' => $row['lastActivityTimestamp'],
                'lastActivityDate' => $row['lastActivityDate'],
            ];
        });

        return new self($csvRows);
    }

}

