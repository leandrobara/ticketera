<?php

namespace App\Http\Requests\Integration;

use App\Models\Lead;
use App\Models\Client;
use Illuminate\Support\Str;
use App\Services\API\LeadService;
use App\Http\Requests\APIBaseRequest;
use App\DTO\Integration\CreateNewIntegrationZapierAppLeadDTO;


class ExtractLeadFromEmailUsingIAZapierRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'emailBody' => ['required', 'string'],
            'customVariablesPrompts' => ['sometimes', 'nullable', 'array'],
            'customPrompt' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
                
                $customVarPromptsReq = request()->input('customVariablesPrompts');
                if ($customVarPromptsReq) {
                    $customVarPrompts = array_slice($customVarPromptsReq, 0, 10);
                    foreach ($customVarPrompts as $customVarPromptsName => $customVarPromptsVal) {
                        $customVarPromptsVal = trim($customVarPromptsVal);
                        $customVarPromptsName = trim($customVarPromptsName);
                        if (!$customVarPromptsName || !$customVarPromptsVal) {
                            $validator->errors()->add(
                                'custom_variables_prompts', 'custom_variables_prompts_name_or_value_is_empty'
                            );
                            return false;
                        }
                    }
                }
            }
        });
    }


    public function getEmailBody(): string
    {
        $validated = parent::validated();
        return $validated['emailBody'];
    }


    public function getCustomVariablesPrompts(): array
    {
        $validated = parent::validated();
        return $validated['customVariablesPrompts'] ?? [];
    }


    public function getCustomPrompt(): string
    {
        $validated = parent::validated();
        return $validated['customPrompt'] ?? '';
    }

}
