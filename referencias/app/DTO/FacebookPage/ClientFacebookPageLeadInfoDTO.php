<?php

namespace App\DTO\FacebookPage;

use Illuminate\Support\Str;
use App\Models\ClientFacebookPage;


class ClientFacebookPageLeadInfoDTO
{

    public array $fbLeadDataArr;
    public array $fbFormDataArr;
    public array $campaignAndAdDataArr;
    public ClientFacebookPage $clientFacebookPage;


    // $fbFormDataArr sale de FacebookAdHelper::getFacebookFormDataById()
    public function __construct(
        ClientFacebookPage $clientFacebookPage,
        array $fbLeadDataArr,
        array $fbFormDataArr = [],
        array $campaignAndAdDataArr = [],
    ) {
        $this->fbLeadDataArr = $fbLeadDataArr;
        $this->fbFormDataArr = $fbFormDataArr;
        $this->clientFacebookPage = $clientFacebookPage;
        $this->campaignAndAdDataArr = $campaignAndAdDataArr;
    }


    public static function build(
        ClientFacebookPage $clientFacebookPage,
        array $fbLeadDataArr,
        array $fbFormDataArr = [],
        array $campaignAndAdDataArr = [],
    ) {
        $dto = new ClientFacebookPageLeadInfoDTO(
            $clientFacebookPage, $fbLeadDataArr, $fbFormDataArr, $campaignAndAdDataArr
        );
        return $dto;
    }


    public function toArray(): array
    {
        $otherFields = [];
        $leadData = $this->getInitData();
        $fbFormFieldsData = $this->fbLeadDataArr['field_data'] ?? null;
        if (!$fbFormFieldsData) {
            return $leadData;
        }

        foreach ($fbFormFieldsData as $field) {
            if (!isset($field['values'])) {
                continue;
            }
            
            $key = $this->mapKey($field['name']);
            if (!is_null($key)) {
                $leadData[$key] = $this->extractValue($field['values']);
                continue;
            }
            $otherFields[] = [$field['name'] => $this->extractValue($field['values'])];
        }

        if ($leadData['email'] ?? null) {
            $leadData['email'] = filter_var($leadData['email'], FILTER_VALIDATE_EMAIL) ?: '';
            $leadData['email'] = trim(strtolower($leadData['email']));
        }

        $leadData['other_fields'] = $this->formatLeadOtherFields($otherFields);
        $leadData['serialized_fields'] = json_encode($this->fbLeadDataArr['field_data']);

        $trackingParameters = $this->extractTrackingParametersFromFbForm();
        $trackingParameters = array_merge($trackingParameters, ['fb_form_lead_id' => $this->fbLeadDataArr['id']]);
        $trackingParameters = array_merge($trackingParameters, $this->campaignAndAdDataArr);
        if ($this->fbFormDataArr['id'] ?? null) {
            $trackingParameters['fb_form_id'] = $this->fbFormDataArr['id'];
        }
        if ($this->fbFormDataArr['name'] ?? null) {
            $trackingParameters['fb_form_name'] = $this->fbFormDataArr['name'];
        }

        $leadData['tracking_parameters'] = $trackingParameters;
        
        return $leadData;
    }


    private function formatLeadOtherFields(array $otherFields): array
    {
        // 1) Filtrar entradas vacías, aplanando si viene array
        $otherFields = array_filter($otherFields, function ($arr) {
            $val = reset($arr);
            // Aplanar casos comunes de arrays
            if (is_array($val)) {
                // Caso Facebook típico: ['values' => [...]]
                if (array_key_exists('values', $val) && is_array($val['values'])) {
                    $val = reset($val['values']);
                } else {
                    // Primer elemento del array genérico
                    $val = reset($val);
                }
            }
            if ($val === null) {
                return false;
            }
            if (is_bool($val)) {
                return $val;
            }
            return trim((string)$val) !== '';
        });

        // 2) Mapear a ['name' => ..., 'value' => ...] aplanando el valor si hace falta
        $otherFields = array_map(function ($arr) {
            $name  = key($arr);
            $value = reset($arr);

            if (is_array($value)) {
                if (array_key_exists('values', $value) && is_array($value['values'])) {
                    $value = reset($value['values']);
                } else {
                    $value = reset($value);
                }
            }

            // Por si queda algo no escalar, lo serializamos (o podrías descartar)
            if (!is_scalar($value) && $value !== null) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            return ['name' => (string) $name, 'value' => $value];
        }, $otherFields);

        // 3) Reindexar (equivalente a tu bucle $i=1 pero empezando en 0 es más estándar)
        return array_values($otherFields);
    }


    public function mapKey(string $fieldName): ?string
    {
        $fieldName = trim(strtolower($fieldName));
        $map = [
            'name' => 'name', // No estandar
            'email' => 'email',
            'phone' => 'phone',
            'fullname' => 'name',
            'full_name' => 'name',
            'full name' => 'name',
            'first_name' => 'name',
            'message' => 'message',
            'lastname' => 'last_name',
            'phone_number' => 'phone',
            'last_name' => 'last_name',
            'company_name' => 'company',
        ];
        $key = $map[$fieldName] ?? null;
        if ($key) {
            return $key;
        }
        // Si el nombre del campo es muy largo, no lo intento asociar a un campo del lead.
        if ($fieldName && strlen($fieldName) > 20) {
            return null;
        }

        $partialMap = [
            'fono' => 'phone',
            'phone' => 'phone',
            'email' => 'email',
            'nombre' => 'name',
            'correo' => 'email',
            'mensaje' => 'message',
            'empresa' => 'company',
            'consulta' => 'message',
            'apellido' => 'last_name',
        ];
        foreach ($partialMap as $partialMapName => $key) {
            if (Str::contains($fieldName, $partialMapName)) {
                return $key;
            }
        }
        return null;
    }


    private function extractTrackingParametersFromFbForm(): array
    {
        if (
            !isset($this->fbFormDataArr['tracking_parameters']) ||
            empty($this->fbFormDataArr['tracking_parameters'])
        ) {
            return [];
        }

        // Caso 1: formato estándar de Meta
        // [
        //   ['key' => 'utm_source', 'value' => 'facebook'],
        //   ['key' => 'utm_campaign', 'value' => 'test']
        // ]
        $tracking = $this->fbFormDataArr['tracking_parameters'];
        if (
            is_array($tracking) &&
            isset($tracking[0]) &&
            is_array($tracking[0]) &&
            array_key_exists('key', $tracking[0])
        ) {
            $normalized = [];
            foreach ($tracking as $item) {
                $key = isset($item['key']) ? trim((string) $item['key']) : null;
                if (!$key) {
                    continue;
                }
                $normalized[$key] = $item['value'] ?? null;
            }
            return $normalized;
        }
        // Caso 2: ya viene como mapa asociativo ['utm_source' => 'facebook']
        if (is_array($tracking)) {
            return $tracking;
        }
        return [];
    }


    private function extractValue($value)
    {
        if (count($value) == 1) {
            $value = !empty($value[0]) ? $value[0] : null;
        }
        return $value;
    }


    private function getInitData()
    {
        return [
            'name' => null,
            'phone' => null,
            'email' => null,
            'notes' => null,
            'message' => null,
            'company' => null,
            'last_name' => null,
            'company_info' => null,
            'other_fields' => null,
            'contact_again' => null
        ];
    }

}
