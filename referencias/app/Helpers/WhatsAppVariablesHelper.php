<?php

namespace App\Helpers;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Str;
use App\Models\LeadContactPhone;
use Illuminate\Support\Collection;
use App\Models\WhatsAppSendingMessage;


class WhatsAppVariablesHelper
{
    
    public static function getVariablesArray(
        string $chatMessage,
        LeadContactPhone $leadContactPhone,
        User $user,
        string $fallbackValue = ''
    ): array | null {
        $chatMessage = Str::lower($chatMessage);

        if (!$chatMessage) {
            return null;
        }
        if (!Str::contains($chatMessage, '{{') || !Str::contains($chatMessage, '}}')) {
            return null;
        }

        $varsArr = [];
        if (Str::contains($chatMessage, '{{nombre}}')) {
            $varsArr['nombre'] = $leadContactPhone->leadContact->name ?? $fallbackValue;
        }
        if (Str::contains($chatMessage, '{{apellido}}')) {
            $varsArr['apellido'] = $leadContactPhone->leadContact->last_name ?? $fallbackValue;
        }
        if (Str::contains($chatMessage, '{{empresa}}')) {
            $varsArr['empresa'] = $leadContactPhone->lead->company ?? $fallbackValue;
        }
        if (Str::contains($chatMessage, '{{nombre_usuario}}')) {
            $varsArr['nombre_usuario'] = $user->name ?? $fallbackValue;
        }
        if (Str::contains($chatMessage, '{{apellido_usuario}}')) {
            $varsArr['apellido_usuario'] = $user->last_name ?? $fallbackValue;
        }

        $enableLeadCustomFields = $user->client->clientSettings->enable_leads_custom_fields;
        if ($enableLeadCustomFields) {
            $leadCustomFields = $user->client->leadsCustomFields;
            if ($leadCustomFields->isNotEmpty()) {
                // Guardamos cada campo normalizado para reutilizarlo en la pasada de prefijos
                $normalizedLeadCustomFields = [];
                $leadCustomFieldsValues = $leadContactPhone->lead->leadCustomFieldsValues;
                foreach ($leadCustomFields as $leadCustomField) {
                    $leadCustomFieldValue = $leadCustomFieldsValues
                        ->where('lead_custom_field_id', $leadCustomField->id)
                        ->first()
                    ;
                    $leadCustomFieldValueData = $leadCustomFieldValue?->value ?? $fallbackValue;
                    
                    // Versión original (lowercase) - RETROCOMPATIBILIDAD
                    $leadCustomFieldNameLower = Str::lower($leadCustomField->name);
                    
                    // Versión normalizada - NUEVO (para plantillas de Meta)
                    $leadCustomFieldNameNormalized = self::normalizeVariableName($leadCustomField->name);
                    
                    // Buscar por versión original (retrocompatibilidad)
                    if (Str::contains($chatMessage, '{{' . $leadCustomFieldNameLower . '}}')) {
                        $varsArr[$leadCustomFieldNameLower] = $leadCustomFieldValueData;
                    }
                    
                    // Buscar por versión normalizada (nuevo) - solo si es diferente de la original
                    // Esto permite que {{numero_de_auto}} matchee con el campo "Número de auto"
                    if ($leadCustomFieldNameNormalized !== $leadCustomFieldNameLower &&
                        Str::contains($chatMessage, '{{' . $leadCustomFieldNameNormalized . '}}')) {
                        $varsArr[$leadCustomFieldNameNormalized] = $leadCustomFieldValueData;
                    }

                    if ($leadCustomFieldNameNormalized) {
                        $normalizedLeadCustomFields[$leadCustomFieldNameNormalized] = $leadCustomFieldValueData;
                    }
                }

                if (!empty($normalizedLeadCustomFields)) {
                    // Buscamos todas las variables del mensaje para detectar prefijos válidos
                    preg_match_all('/\{\{([^}]+)\}\}/', $chatMessage, $matches);
                    if (!empty($matches[1])) {
                        foreach ($matches[1] as $variableName) {
                            $variableName = trim($variableName);
                            if ($variableName === '' || array_key_exists($variableName, $varsArr)) {
                                // Variable vacía o ya resuelta por coincidencia exacta
                                continue;
                            }

                            foreach ($normalizedLeadCustomFields as $normalizedName => $fieldValue) {
                                if ($normalizedName === '' || $normalizedName === $variableName) {
                                    // Evitamos comparar con cadenas vacías o con coincidencias exactas
                                    continue;
                                }

                                // Chequeo de variables no resueltas: verifica si un campo EMPIEZA CON
                                if (Str::startsWith($normalizedName, $variableName)) {
                                    // Prefijo válido: asignamos el valor al alias corto
                                    $varsArr[$variableName] = $fieldValue;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $varsArr ?: null;
    }


    public static function hasVariables(string $chatMessage): bool
    {
        $hasVariables = Str::contains(
            $chatMessage,
            ['{{nombre}}', '{{apellido}}', '{{empresa}}', '{{nombre_usuario}}', '{{apellido_usuario}}']
        );
        return $hasVariables;
    }


    public static function replaceVariables(
        string $chatMessage,
        LeadContactPhone $leadContactPhone,
        User $user
    ): string {
        $variables = self::getVariablesArray($chatMessage, $leadContactPhone, $user);
        if ($variables) {
            foreach ($variables as $varName => $varValue) {
                $variable = '{{' . $varName . '}}';
                $chatMessage = str_ireplace($variable, $varValue, $chatMessage);
            }
        }
        return $chatMessage;
    }


    private static function normalizeVariableName(string $varName): string
    {
        // 1. Lowercase
        $normalized = mb_strtolower($varName, 'UTF-8');
        
        // 2. Eliminar tildes y diacríticos
        $unwanted_array = [
            'á'=> 'a', 'à'=> 'a', 'ä'=> 'a', 'â'=> 'a', 'ã'=> 'a', 'å'=> 'a',
            'é'=> 'e', 'è'=> 'e', 'ë'=> 'e', 'ê'=> 'e',
            'í'=> 'i', 'ì'=> 'i', 'ï'=> 'i', 'î'=> 'i',
            'ó'=> 'o', 'ò'=> 'o', 'ö'=> 'o', 'ô'=> 'o', 'õ'=> 'o', 'ø'=> 'o',
            'ú'=> 'u', 'ù'=> 'u', 'ü'=> 'u', 'û'=> 'u',
            'ñ'=> 'n', 'ç'=> 'c'
        ];
        $normalized = strtr($normalized, $unwanted_array);
        // 3. Espacios y guiones a underscore
        $normalized = preg_replace('/[\s\-]+/', '_', $normalized);
        // 4. Eliminar caracteres especiales (dejar solo a-z, 0-9, _)
        $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized);
        // 5. Eliminar underscores múltiples consecutivos
        $normalized = preg_replace('/_+/', '_', $normalized);
        // 6. Eliminar underscores al inicio y final
        $normalized = trim($normalized, '_');
        
        return $normalized;
    }

}
