<?php

namespace App\DTO\WapSalesAgent;

class NoteCandidateDTO
{
    public $id;
    public $text;
    public $createdAt;

    public static function build(array $data): self
    {
        return new self($data);
    }

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->text = $data['text'] ?? null;
        $this->createdAt = $data['createdAt'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'createdAt' => $this->createdAt,
        ];
    }
}
