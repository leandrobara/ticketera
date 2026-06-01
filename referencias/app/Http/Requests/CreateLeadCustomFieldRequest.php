<?php

namespace App\Http\Requests;

use App\Models\LeadCustomField;
use Illuminate\Validation\Rule;
use App\Rules\InLeadCustomFieldReturnFields;


class CreateLeadCustomFieldRequest extends APIBaseRequest
{

    protected $type = null;
    protected $typeValues = null;


    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'is_shown_in_leads_row' => ['required', 'boolean'],
            'type_values' => ['sometimes', 'nullable', 'array'],
            'default_value' => ['sometimes', 'nullable', 'string'],
            'type' => ['required', Rule::in(['text', 'numeric_integer', 'numeric', 'date', 'datetime', 'dropdown'])],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InLeadCustomFieldReturnFields()],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $clientId = request()->input('client')->id;
                $this->type = trim(request()->input('type'));
                $this->typeValues = request()->input('type_values');
                $leadCustomFieldName = trim(request()->input('name'));

                // Si el array vino con data, pero al limpiarlo la data queda vacía.
                if ($this->typeValues) {
                    $this->typeValues = array_map('trim', array_values(array_filter($this->typeValues)));
                    if (!$this->typeValues) {
                        $validator->errors()->add('type_values', 'lead_custom_field_type_values_is_empty');
                        return false;
                    }
                }
                
                $clientyClientId = (int) config('app.clienty.client_id');
                $clientIsClienty = $clientyClientId == $clientId;

                // Clienty no tiene límite de cantidad de campos personalizados.
                $existentLeadCustomFieldsCount = LeadCustomField::where('client_id', $clientId)->get()->count();
                if ($existentLeadCustomFieldsCount >= 10 && !$clientIsClienty) {
                    $validator->errors()->add('lead_custom_field', 'client_lead_custom_fields_quota_full');
                    return false;
                }

                $leadCustomFieldExists = LeadCustomField::where(
                    ['client_id' => $clientId, 'name' => $leadCustomFieldName]
                )->count();
                if ($leadCustomFieldExists) {
                    $validator->errors()->add('lead_custom_field', 'lead_custom_field_already_exists');
                    return false;
                }
                
                if ($this->type == 'dropdown' && !$this->typeValues) {
                    $validator->errors()->add(
                        'lead_custom_field_type_values', 'lead_custom_field_type_dropdown_requires_type_values'
                    );
                    return false;
                }

                // invalid dropdown default value
                $defaultValue = trim(request()->input('default_value'));
                if ($this->type == 'dropdown' &&
                    $defaultValue &&
                    !in_array($defaultValue, $this->typeValues)
                ) {
                    $validator->errors()->add(
                        'lead_custom_field_default_value', 'lead_custom_field_dropdown_default_value_is_wrong'
                    );
                    return false;
                }

                // invalid int default value
                if ($this->type == 'numeric_integer' &&
                    $defaultValue &&
                    !filter_var($defaultValue, FILTER_VALIDATE_INT)
                ) {
                    $validator->errors()->add(
                        'lead_custom_field_default_value', 'lead_custom_field_numeric_integer_default_value_is_wrong'
                    );
                    return false;
                }

                // invalid number default value
                if ($this->type == 'numeric' && $defaultValue && !is_numeric($defaultValue)) {
                    $validator->errors()->add(
                        'lead_custom_field_default_value', 'lead_custom_field_numeric_default_value_is_wrong'
                    );
                    return false;
                }
            }
        });
    }


    public function validatedAttributes()
    {
        $validated = parent::validated();
        if ($validated['fields'] ?? false) {
            unset($validated['fields']);
        }

        $validated['type_values'] = null;
        if ($this->type == 'dropdown') {
            $validated['type_values'] = $this->typeValues;
        }
        return $validated;
    }

}
