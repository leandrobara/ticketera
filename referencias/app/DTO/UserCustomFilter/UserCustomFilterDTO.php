<?php

namespace App\DTO\UserCustomFilter;

class UserCustomFilterDTO
{
    public $client;

    public $user;

    public $name;

    public $filters;

    public function __construct($data)
    {
        $this->client = $data['client'];
        $this->user = $data['user'];
        $this->name = $data['name'] ?? null;
        $this->filters = json_decode($data['filters']);
    }

    public static function build(array $data)
    {
        $dto = new UserCustomFilterDTO($data);

        return $dto;
    }
}
