<?php

namespace App\Http\Requests\WhatsAppMetaAPI;

use App\Helpers\PhonesHelper;
use App\Models\WhatsAppTemplate;
use App\Http\Requests\APIBaseRequest;
use App\Models\MongoDB\WhatsAppMetaAPI\WhatsAppConversationMessage;
use App\Services\API\WhatsAppMetaAPI\WhatsAppConversationMessageService;


class WhatsAppMetaAPISendNonLeadMessageRequest extends APIBaseRequest
{

    public ?string $normalizedPhone = null;
    public ?string $mediaFileType = null;
    public array $resolvedBodyVariables = [];
    public array $resolvedHeaderVariables = [];
    public ?WhatsAppTemplate $whatsAppTemplate = null;


    public function rules()
    {
        return [
            'mediaFile' => ['nullable', 'file'],
            'audioVoiceFile' => ['nullable', 'file'],
            'whatsAppTemplateId' => ['nullable', 'integer'],
            'chatMessage' => ['nullable', 'string', 'max:1000'],
            'whatsAppMetaAPIConnectionId' => ['required', 'integer'],
            'customerPhoneNumber' => ['required', 'string', 'regex:/^[0-9]{7,20}$/'],
            'variables' => ['sometimes', 'nullable', 'array'],
            'variables.*' => ['nullable', 'string'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($validator->failed()) {
                return;
            }

            $user = request()->user;
            $client = request()->client;
            $mediaFile = request()->file('mediaFile');
            $chatMessage = request()->input('chatMessage');
            $audioVoiceFile = request()->file('audioVoiceFile');
            $whatsAppTemplateId = request()->input('whatsAppTemplateId');
            
            $hasMediaFile = $mediaFile !== null;
            $hasAudioVoiceFile = $audioVoiceFile !== null;
            $hasWhatsAppTemplate = !empty($whatsAppTemplateId);
            $hasChatMessage = trim((string) $chatMessage) !== '';

            // Debe venir exactamente uno de los cuatro: texto, template, audio o archivo adjunto
            $providedMessageTypesCount = (int) $hasChatMessage +
                (int) $hasWhatsAppTemplate +
                (int) $hasAudioVoiceFile +
                (int) $hasMediaFile
            ;
            if ($providedMessageTypesCount === 0) {
                $validator->errors()->add('message', 'chat_message_or_whatsapp_template_id_or_audio_file_is_required');
                return;
            }
            if ($providedMessageTypesCount > 1) {
                $validator->errors()->add('message', 'only_one_message_type_is_allowed');
                return;
            }

            // Validaciones de audioVoiceFile
            if ($hasAudioVoiceFile) {
                $audioVoiceMimeType = strtolower(trim(explode(';', $audioVoiceFile->getClientMimeType())[0]));
                $allowedAudioVoiceMimeTypes = ['audio/webm', 'video/webm', 'audio/mp4'];

                if (!in_array($audioVoiceMimeType, $allowedAudioVoiceMimeTypes)) {
                    $validator->errors()->add('audioVoiceFile', 'audio_voice_file_must_be_webm_or_mp4');
                    return;
                }
                if ($audioVoiceFile->getSize() > 8 * 1024 * 1024) {
                    $validator->errors()->add('audioVoiceFile', 'audio_file_exceeds_max_size');
                    return;
                }
            }

            // Validaciones de mediaFile (imagen, video, documento)
            if ($hasMediaFile) {
                $mediaFileMimeType = strtolower(trim(explode(';', $mediaFile->getClientMimeType())[0]));

                $videoMimes = ['video/mp4', 'video/3gpp'];
                $imageMimes = ['image/jpeg', 'image/png', 'image/webp'];
                $documentMimes = [
                    'text/plain',
                    'application/pdf',
                    'application/msword',
                    'application/vnd.ms-excel',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                ];

                if (in_array($mediaFileMimeType, $imageMimes)) {
                    $this->mediaFileType = 'image';
                    $maxSize = 5 * 1024 * 1024;
                } elseif (in_array($mediaFileMimeType, $videoMimes)) {
                    $this->mediaFileType = 'video';
                    $maxSize = 16 * 1024 * 1024;
                } elseif (in_array($mediaFileMimeType, $documentMimes)) {
                    $this->mediaFileType = 'document';
                    $maxSize = 25 * 1024 * 1024;
                } else {
                    $validator->errors()->add('mediaFile', 'media_file_type_not_supported');
                    return;
                }

                if ($mediaFile->getSize() > $maxSize) {
                    $validator->errors()->add('mediaFile', 'media_file_exceeds_max_size');
                    return;
                }
            }

            if (!$client->clientSettings->enable_whatsapp_meta_api) {
                $validator->errors()->add('whatsapp_meta_api', 'whatsapp_meta_api_is_not_enabled');
                return;
            }

            if (!$user->whatsAppMetaAPIConnection) {
                $validator->errors()->add('whatsapp_meta_api', 'whatsapp_meta_api_connection_does_not_exist');
                return;
            }

            $whatsAppMetaAPIConnectionId = (int) request()->input('whatsAppMetaAPIConnectionId');
            if ($whatsAppMetaAPIConnectionId !== (int) $user->whatsAppMetaAPIConnection->id) {
                $validator->errors()->add('whatsAppMetaAPIConnectionId', 'connection_does_not_match_user');
                return;
            }

            // Normalizar y validar teléfono
            $phonesHelper = resolve(PhonesHelper::class);
            $this->normalizedPhone = $phonesHelper->formatPhoneForWhatsAppWithSettings(
                request()->input('customerPhoneNumber'), $client->country_code, $client->clientSettings ?? null
            );
            if (!$phonesHelper->formattedPhoneNumberHasValidLength($this->normalizedPhone)) {
                $validator->errors()->add('customerPhoneNumber', 'phone_number_has_invalid_length');
                return;
            }

            // Validaciones de template
            if ($hasWhatsAppTemplate) {
                $this->whatsAppTemplate = WhatsAppTemplate::where('id', $whatsAppTemplateId)
                    ->where('client_id', $client->id)
                    ->first();

                if (!$this->whatsAppTemplate) {
                    $validator->errors()->add('whatsAppTemplateId', 'whatsapp_template_not_found');
                    return;
                }

                if ($this->whatsAppTemplate->waba_id !== $user->whatsAppMetaAPIConnection->waba_id) {
                    $validator->errors()->add('whatsAppTemplateId', 'whatsapp_template_does_not_belong_to_connection');
                    return;
                }

                // No se permite attachment + header con variables simultáneamente
                // (el helper sendTemplateMessage lo rechaza, pero conviene fallar antes con error claro)
                $hasAttachment = $this->whatsAppTemplate->whatsAppAttachment !== null;
                $headerText = $this->whatsAppTemplate->meta_header_text;
                $headerHasVariables = $headerText && preg_match('/{{\s*[A-Za-z0-9_]+\s*}}/', $headerText);
                if ($hasAttachment && $headerHasVariables) {
                    $validator->errors()->add('whatsAppTemplateId', 'template_has_media_and_header_variables');
                    return;
                }

                // Extraer y validar variables del template
                $variables = request()->input('variables', []) ?? [];
                $this->resolvedBodyVariables = $this->buildOrderedVariables(
                    $this->whatsAppTemplate->body, $variables, $validator, 'body'
                );
                if ($validator->errors()->any()) {
                    return;
                }
                $this->resolvedHeaderVariables = $this->buildOrderedVariables(
                    $headerText, $variables, $validator, 'header'
                );
                if ($validator->errors()->any()) {
                    return;
                }
            }

            // Si es mensaje libre (texto, audio o archivo, sin template), validar ventana de 24hs
            $isOpenWindowMessage = !$hasWhatsAppTemplate && ($hasChatMessage || $hasAudioVoiceFile || $hasMediaFile);
            if ($isOpenWindowMessage) {
                $phoneNumberId = $user->whatsAppMetaAPIConnection->phone_number_id;

                $conversationService = resolve(WhatsAppConversationMessageService::class);
                $recentMessages = $conversationService->listConversation(
                    $phoneNumberId, $this->normalizedPhone, ['limit' => 10]
                );
                $lastIncomingMsg = $recentMessages->last(function ($msg) {
                    return $msg->direction === WhatsAppConversationMessage::DIRECTION_INCOMING;
                });

                $isWindowOpen = false;
                if ($lastIncomingMsg && $lastIncomingMsg->metaReceivedMessageTimestamp) {
                    $diffMinutes = now()->diffInMinutes($lastIncomingMsg->metaReceivedMessageTimestamp, true);
                    $isWindowOpen = $diffMinutes < (24 * 59);
                }

                if (!$isWindowOpen) {
                    $validator->errors()->add('conversationWindow', 'conversation_window_is_closed');
                    return;
                }
            }
        });
    }


    /**
     * Extrae variables del texto, valida que cada una tenga valor en $variables,
     * y retorna array ordenado para Meta API.
     * Mismo formato que buildOrderedVariablesArray() en WhatsAppMetaAPIService.
     */
    private function buildOrderedVariables(
        ?string $text,
        array $variables,
        $validator,
        string $section
    ): array {
        if (!$text) {
            return [];
        }

        preg_match_all('/{{\s*([A-Za-z0-9_]+)\s*}}/', $text, $match);
        $orderedVarNames = $match[1] ?? [];
        if (empty($orderedVarNames)) {
            return [];
        }

        $params = [];
        foreach ($orderedVarNames as $varName) {
            $value = $variables[$varName] ?? null;
            if ($value === null || trim((string) $value) === '') {
                $validator->errors()->add(
                    "variables.{$varName}",
                    "variable_{$section}_{$varName}_is_required"
                );
                return [];
            }
            $params[] = [
                'type' => 'text',
                'parameter_name' => (string) $varName,
                'text' => (string) trim($value),
            ];
        }
        return $params;
    }
}
