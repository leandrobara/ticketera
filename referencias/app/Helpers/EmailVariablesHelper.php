<?php

namespace App\Helpers;

use Carbon\Carbon;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Str;
use App\Models\LeadContactEmail;
use Illuminate\Support\Collection;


class EmailVariablesHelper
{

    public static function getVariablesArray(
        string $bodyOrSubject,
        LeadContactEmail $leadContactEmail,
        User $user
    ): array {
        $varsArr = [];

        $bodyOrSubject = Str::lower($bodyOrSubject);
        if (Str::contains($bodyOrSubject, '{{id}}')) {
            $varsArr['id'] = $leadContactEmail->lead_id ?? '';
        }
        if (Str::contains($bodyOrSubject, '{{nombre}}')) {
            $varsArr['nombre'] = $leadContactEmail->leadContact->name ?? '';
        }
        if (Str::contains($bodyOrSubject, '{{apellido}}')) {
            $varsArr['apellido'] = $leadContactEmail->leadContact->last_name ?? '';
        }
        if (Str::contains($bodyOrSubject, '{{telefono}}')) {
            $varsArr['telefono'] = $leadContactEmail->lead->main_phone ?? '';
        }
        // Ojo: acá reemplaza por el primer email del contacto principal
        if (Str::contains($bodyOrSubject, '{{email}}')) {
            $varsArr['email'] = $leadContactEmail->lead->main_email ?? '';
        }
        if (Str::contains($bodyOrSubject, '{{empresa}}')) {
            $varsArr['empresa'] = $leadContactEmail->lead->company ?? '';
        }
        if (Str::contains($bodyOrSubject, '{{nombre_usuario}}')) {
            $varsArr['nombre_usuario'] = $user->name ?? '';
        }
        if (Str::contains($bodyOrSubject, '{{apellido_usuario}}')) {
            $varsArr['apellido_usuario'] = $user->last_name ?? '';
        }
        
        $enableLeadCustomFields = $user->client->clientSettings->enable_leads_custom_fields;
        if ($enableLeadCustomFields) {
            $leadCustomFields = $user->client->leadsCustomFields;
            if ($leadCustomFields->isNotEmpty()) {
                $leadCustomFieldsValues = $leadContactEmail->lead->leadCustomFieldsValues;
                foreach ($leadCustomFields as $leadCustomField) {
                    $leadCustomFieldValue = $leadCustomFieldsValues
                        ->where('lead_custom_field_id', $leadCustomField->id)
                        ->first()
                    ;
                    $leadCustomFieldName = Str::lower($leadCustomField->name);
                    if (Str::contains($bodyOrSubject, '{{' . $leadCustomFieldName . '}}')) {
                        $value = $leadCustomFieldValue->value ?? '';
                        if ($leadCustomField->type == 'date' && $value) {
                            Carbon::setLocale('es');
                            $value = Carbon::parse($value)->translatedFormat('d \d\e F Y');
                        }
                        if ($leadCustomField->type == 'datetime' && $value) {
                            Carbon::setLocale('es');
                            $value = Carbon::parse($value)->translatedFormat('d \d\e F Y H:i') . ' hs.';
                        }
                        $varsArr[$leadCustomFieldName] = $value;
                    }
                }
            }
        }

        return $varsArr;
    }
}
