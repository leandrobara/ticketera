<?php

namespace App\DTO\WhatsAppMetaAPI;


class WhatsAppMetaAPITemplateDTO
{

    public ?string $id;
    public ?string $name;
    public ?string $status;
    public ?string $category;
    public array $components;
    public ?string $language;
    public ?string $parameterFormat;


    public function __construct(array $data)
    {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->status = $data['status'] ?? null;
        $this->category = $data['category'] ?? null;
        $this->language = $data['language'] ?? null;
        $this->components = $data['components'] ?? [];
        $this->parameterFormat = $data['parameter_format'] ?? null;
    }


    public function isApproved(): bool
    {
        return strtoupper($this->status) == 'APPROVED';
    }


    public function getBodyText(): ?string
    {
        foreach ($this->components as $component) {
            if ($component['type'] == 'BODY') {
                return $component['text'] ?? null;
            }
        }
        return null;
    }


    public function getHeaderText(): ?string
    {
        foreach ($this->components as $component) {
            if ($component['type'] == 'HEADER') {
                return $component['text'] ?? null;
            }
        }
        return null;
    }


    public function getBodyVariables(): array
    {
        $body = $this->getBodyText();
        if (!$body) {
            return [];
        }
        // Detecta {{nombre}}, {{1}}, etc.
        preg_match_all('/{{\s*([\w\d_]+)\s*}}/', $body, $matches);
        return $matches[1] ?? [];
    }


    public function usesNamedParameters(): bool
    {
        return strtoupper($this->parameterFormat) == 'NAMED';
    }


    public function usesPositionalParameters(): bool
    {
        return strtoupper($this->parameterFormat) == 'POSITIONAL';
    }

}
