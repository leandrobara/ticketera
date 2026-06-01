<?php

namespace App\Helpers\WhatsAppMetaAPI;

use Exception;
use Throwable;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\WhatsAppMetaAPIConnection;
use App\DTO\WhatsAppMetaAPI\WhatsAppMetaAPITemplateDTO;


class WhatsAppMetaAPIHelper
{

    protected array $scopes;
    protected string $appId;
    protected string $appSecret;
    protected string $oAuthCallbackHandlerUri;
    
    // Id de business de la app de Clienty en Meta
    // Es el ID del Business Manager de la empresa (nuestro BM “agencia/partner”).
    protected string $partnerBusinessId = '385804558289909';


    public function __construct(string $appId, string $appSecret)
    {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->scopes = [
            'business_management',
            'whatsapp_business_management',
            'whatsapp_business_messaging',
            'whatsapp_business_manage_events',
        ];

        // clienty.clienty.co/api/whatsapp-meta-api/handle-oauth-callback
        $this->oAuthCallbackHandlerUri = config('app.facebook.waba_oauth_handler_url');
    }


    // $state -> ['client_id' => 'xx', 'client_subdomain' => 'xx', 'user_id' => 'xx', 'user_username' => 'xx']
    public function getOAuthRedirectUrl(array|string $state): string
    {
        $state = base64_encode(is_array($state) ? json_encode($state) : $state);
        $query = http_build_query([
            'state' => $state,
            'extras' => json_encode([
                'setup' => new \stdClass(),
                'sessionInfoVersion' => '3',
                'featureType' => 'whatsapp_business_app_onboarding',
            ]),
            'response_type' => 'code',
            'client_id' => $this->appId,
            'config_id' => '1496916398352646',
            'scope' => implode(',', $this->scopes),
            'override_default_response_type' => 'true',
            'redirect_uri' => $this->oAuthCallbackHandlerUri,
        ], '', '&', PHP_QUERY_RFC3986);   // Evita “+” por espacios (usa %20)

        return "https://www.facebook.com/v23.0/dialog/oauth?{$query}";
    }


    public function debugAccessToken($accessToken): array
    {
        $debug = Http::get('https://graph.facebook.com/v23.0/debug_token', [
            'input_token' => $accessToken,
            'access_token'=> $this->appId . '|' . $this->appSecret,
        ])->throw()->json();
        return $debug;
    }


    public function getWebhooksSubscriptions(string $wabaId, string $accessToken): array
    {
        $endpoint = "https://graph.facebook.com/v25.0/{$wabaId}/subscribed_apps";
        $data = Http::withToken($accessToken)->get($endpoint)->throw()->json();
        return $data['data'] ?? [];
    }


    public function subscribeWabaToWebhooks(string $wabaId, string $accessToken): bool
    {
        $endpoint = "https://graph.facebook.com/v23.0/{$wabaId}/subscribed_apps";
        $res = Http::withToken($accessToken)->post($endpoint);
        if (!$res->ok()) {
            throw new Exception($res->body());
        }
        return (bool) ($res->json()['success'] ?? false);
    }


    public function isWabaSubscribedToWebhooks(string $wabaId, string $accessToken): bool
    {
        $subscriptions = $this->getWebhooksSubscriptions($wabaId, $accessToken);
        foreach ($subscriptions as $subscription) {
            if (($subscription['whatsapp_business_api_data']['id'] ?? null) === $this->appId) {
                return true;
            }
        }
        return false;
    }


    // Intercambia el code de autorización por un access token
    public function exchangeCodeForAccessToken(string $code): string
    {
        $response = Http::get('https://graph.facebook.com/v23.0/oauth/access_token', [
            'code' => $code,
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'redirect_uri' => $this->oAuthCallbackHandlerUri,
        ]);
        if (!$response->ok()) {
            throw new Exception($response->body());
        }
        return $response->json('access_token');
    }


    // Obtiene info del accessToken
    public function debugToken(string $accessToken): array
    {
        $appAccessToken = "{$this->appId}|{$this->appSecret}";
        $response = Http::get(
            'https://graph.facebook.com/v23.0/debug_token',
            ['input_token'  => $accessToken, 'access_token' => $appAccessToken]
        );
        if (!$response->ok()) {
            throw new Exception($response->body());
        }
        $body = $response->json();
        return $body['data'] ?? [];
    }


    /**
     * Obtiene los negocios asociados al usuario logueado
     * (NO funciona con el accessToken que generamos por que es un SYSTEM token, y no un USER token)
     */
    public function getBusinesses(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->get('https://graph.facebook.com/v23.0/me?fields=businesses');
        if (!$response->ok()) {
            throw new Exception($response->body());
        }
        $businessesArr = $response->json('businesses');
        return $businessesArr['data'] ?? [];
    }


    public function getBusinessInfoById(string $businessId, string $accessToken): array
    {
        $endpoint = "https://graph.facebook.com/v23.0/{$businessId}";
        $response = Http::withToken($accessToken)->get($endpoint);
        if (!$response->ok()) {
            throw new Exception($response->body());
        }
        return $response->json();
    }


    // Obtiene las cuentas de WhatsApp (WABA) de un negocio
    public function getWABAs(string $businessId, string $accessToken): array
    {
        $endpoint = "https://graph.facebook.com/v23.0/{$businessId}/owned_whatsapp_business_accounts";
        $response = Http::withToken($accessToken)->get($endpoint);
        if (!$response->ok()) {
            $errorInfo = $response->json('error') ?? [];
            $code = $errorInfo['code'] ?? null;
            $msg = $errorInfo['message'] ?? null;
            if ($code === 200) {
                // '(#200) Requires business_management permission to manage the object'
                return []; // Silenciosamente retornar vacío o loguear
            }
            throw new Exception($response->body());
        }
        return $response->json('data') ?? [];
    }


    public function extractWabaIdsFromToken(string $accessToken): array
    {
        // Plan A: granular_scopes (método histórico, Meta dejó de devolver target_ids)
        // (Esto dejó de funcionar el 12/05: los granular scopes dejaron de traer target_id (que eran los waba_id))
        // $wabaIds = [];
        // $debugInfo = $this->debugToken($accessToken);
        // foreach ($debugInfo['granular_scopes'] ?? [] as $scopeInfo) {
        //     if (
        //         in_array($scopeInfo['scope'], ['whatsapp_business_management', 'whatsapp_business_messaging']) &&
        //         isset($scopeInfo['target_ids'])
        //     ) {
        //         $wabaIds = array_merge($wabaIds, $scopeInfo['target_ids']);
        //     }
        // }
        // if (!empty($wabaIds)) {
        //     return array_unique($wabaIds);
        // }

        // Plan B: fallback via client_whatsapp_business_accounts del partner
        $wabas = $this->getClientWabasFromPartner($accessToken);
        return collect($wabas)->pluck('id')->all();
    }


    public function getClientWabasFromPartner(string $accessToken): array
    {
        $endpoint = "https://graph.facebook.com/v23.0/{$this->partnerBusinessId}/client_whatsapp_business_accounts";
        $response = Http::withToken($accessToken)->get($endpoint, [
            'fields' => 'id,name',
            'limit' => 200,
        ]);
        if (!$response->ok()) {
            throw new Exception($response->body());
        }
        return $response->json('data') ?? [];
    }



    public function getWABAInfoById(string $wabaId, string $accessToken): array
    {
        $endpoint = "https://graph.facebook.com/v25.0/{$wabaId}";
        $response = Http::withToken($accessToken)->get($endpoint);
        if (!$response->ok()) {
            throw new Exception($response->body());
        }
        return $response->json();
    }


    // Obtiene los números de teléfono asociados a una cuenta WABA
    public function getPhoneNumbers(string $wabaId, string $accessToken): array
    {
        $endpoint = "https://graph.facebook.com/v23.0/{$wabaId}/phone_numbers";
        $response = Http::withToken($accessToken)->get($endpoint);
        if (!$response->ok()) {
            throw new Exception($response->body());
        }
        return $response->json('data') ?? [];
    }


    public function getMessagingLimitByWabaId(string $wabaId, string $accessToken): ?string
    {
        // reemplaza messaging_limit_tier
        $endpoint = "https://graph.facebook.com/v25.0/{$wabaId}?fields=whatsapp_business_manager_messaging_limit";
        $response = Http::withToken($accessToken)->get($endpoint);
        if (!$response->ok()) {
            return null;
        }
        return $response->json('whatsapp_business_manager_messaging_limit');
    }


    public function getPhoneNumberInfoById(string $phoneNumberId, string $accessToken): array
    {
        $fields = implode(',', [
            'id',
            'status', // nuevo
            'throughput',
            // 'name_status', // nuevo (beta)
            'verified_name',
            'platform_type',
            'quality_rating',
            'display_phone_number',
            'messaging_limit_tier',
            'webhook_configuration',
            'code_verification_status',
            // 'whatsapp_business_manager_messaging_limit', // NO! No pedir genera error
        ]);

        $endpoint = "https://graph.facebook.com/v25.0/{$phoneNumberId}?fields={$fields}";

        $response = Http::withToken($accessToken)->get($endpoint);
        if (!$response->ok()) {
            throw new Exception($response->body());
        }
        return $response->json();
    }


    // @returns Collection<WhatsAppMetaAPITemplateDTO>
    public function getMessageTemplates(string $wabaId, string $accessToken): Collection
    {
        $endpoint = "https://graph.facebook.com/v23.0/{$wabaId}/message_templates";
        $response = Http::withToken($accessToken)->get($endpoint, [
            // 'fields' => 'name,language,category,status,components',
            'limit' => 500, // opcional, podés paginar si hay muchas
        ]);
        if (!$response->ok()) {
            throw new Exception('Error al listar plantillas: ' . $response->body());
        }
        $templates = $response->json('data') ?? [];
        $templates = new Collection($templates);
        $templates = $templates->map(fn ($tpl) => new WhatsAppMetaAPITemplateDTO($tpl));
        return $templates;
    }


    public function createTemplate(
        string $wabaId,
        string $accessToken,
        string $name,
        string $bodyText,
        string $language,
        array $bodyTextVariables = [],
        string $templateCategory = 'MARKETING',
        string $headerFormat = '',
        string $headerText = '',
        array $headerTextVariables = [],
        string $footerText = '',
        ?string $mediaHeaderHandleId = null,
        ?string $documentFilename = null
    ): WhatsAppMetaAPITemplateDTO {
        $components = [];

        if ($headerTextVariables && $mediaHeaderHandleId) {
            throw new Exception('Can not create Meta template with both attachment and header text');
        }
        
        // BODY
        $body = ['type' => 'BODY', 'text' => $bodyText];
        if (!empty($bodyTextVariables)) {
            // Formatear las variables correctamente
            $bodyExampleParams = [];
            foreach ($bodyTextVariables as $variable) {
                $bodyExampleParams[] = [
                    'param_name' => $variable['name'],
                    'example' => $variable['example'],
                ];
            }
            $body['example'] = [
                'body_text_named_params' => $bodyExampleParams,
            ];
        }
        $components[] = $body;
        
        // HEADER
        if ($headerFormat) {
            $header = ['type' => 'HEADER', 'format' => $headerFormat];
            
            if ($headerFormat == 'text') {
                $header['text'] = $headerText;
                if (!empty($headerTextVariables)) {
                    $headerExampleParams = [];
                    foreach ($headerTextVariables as $variable) {
                        $headerExampleParams[] = [
                            'param_name' => $variable['name'],
                            'example' => $variable['example']
                        ];
                    }
                    $header['example'] = [
                        'header_text_named_params' => $headerExampleParams,
                    ];
                }
            } elseif (in_array($headerFormat, ['image', 'video', 'document']) && $mediaHeaderHandleId) {
                // TODOS usan header_handle, no header_image, header_video o header_document
                $header['example'] = [
                    'header_handle' => [
                        $mediaHeaderHandleId  // Solo el ID como string
                    ]
                ];
            }
            $components[] = $header;
        }
        
        // FOOTER
        if ($footerText) {
            $components[] = ['type' => 'FOOTER', 'text' => $footerText];
        }
        
        $payload = [
            'name' => $name,
            'language' => $language,
            'category' => $templateCategory,
            'components' => $components,
        ];
        if (!empty($bodyTextVariables) || !empty($headerTextVariables)) {
            $payload['parameter_format'] = 'NAMED';
        }

        // dd($payload);
        $endpoint = "https://graph.facebook.com/v23.0/{$wabaId}/message_templates";
        $response = Http::withToken($accessToken)->post($endpoint, $payload);
        if (!$response->ok()) {
            throw new Exception($response->body());
        }

        // La respuesta de creación solo incluye id, status y category
        $tplMetaInfo = $response->json();
        $templateData = [
            'id' => $tplMetaInfo['id'],
            'status' => $tplMetaInfo['status'],
            'category' => $tplMetaInfo['category'],
            'name' => $name, // Lo sabemos porque lo enviamos
            'language' => $language, // Lo sabemos porque lo enviamos
            'components' => $components, // Lo sabemos porque lo enviamos
        ];
        return new WhatsAppMetaAPITemplateDTO($templateData);
    }


    public function deleteTemplate(string $wabaId, string $templateName, string $accessToken): bool
    {
        $endpoint = "https://graph.facebook.com/v23.0/{$wabaId}/message_templates";
        $response = Http::withToken($accessToken)->delete($endpoint, ['name' => $templateName]);
        if (!$response->ok()) {
            throw new Exception($response->body());
        }
        return $response->json('success') ?? false;
    }


    public function sendTemplateMessage(
        string $phoneNumberId,
        string $accessToken,
        string $toPhoneNumber,
        string $templateName,
        string $languageCode = 'es_ES',
        array $bodyVariables = [], // [['type'  => 'text', 'parameter_name' => 'varName', 'text' => 'xxx'], ...]
        array $headerVariables = [], // [['type'  => 'text', 'parameter_name' => 'varName', 'text' => 'xxx'], ...]
        array $attachmentData = []
    ): array {
        // No se puede header TEXT + MEDIA a la vez
        if (!empty($attachmentData) && !empty($headerVariables)) {
            throw new Exception('El header no puede ser simultáneamente media y texto.');
        }

        $components = [];

        if ($headerVariables) {
            $components[] = ['type' => 'header', 'parameters' => $headerVariables];
        }
        if ($bodyVariables) {
            $components[] = ['type' => 'body', 'parameters' => $bodyVariables];
        }

        if ($attachmentData) {
            $mediaInfo = [];
            $mediaType = $attachmentData['type'];

            // Si tiene meta_id, lo uso, si no, uso el link al archivo.
            if ($attachmentData['meta_id'] ?? null) {
                $mediaInfo['id'] = $attachmentData['meta_id'];
            } else {
                $mediaInfo['link'] = $attachmentData['link'];
            }

            if ($mediaType == 'document') {
                $mediaInfo['filename'] = $attachmentData['filename'];
            }
            if (!empty($attachmentData['caption'])) {
                $mediaInfo['caption'] = $attachmentData['caption'];
            }
            $components[] = [
                'type' => 'header',
                'parameters' => [
                    ['type' => $mediaType, $mediaType => $mediaInfo]
                ],
            ];
        }

        $payload = [
            'type' => 'template',
            'to' => $toPhoneNumber,
            'messaging_product' => 'whatsapp',
            'template' => [
                'name' => $templateName,
                'components' => $components,
                'language' => ['code' => $languageCode],
            ],
        ];
        $endpoint = "https://graph.facebook.com/v23.0/{$phoneNumberId}/messages";
        $response = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($endpoint, $payload)
        ;
        
        if (!$response->ok()) {
            throw new Exception($response->body());
        }
        return $response->json();
    }


    /**
     * Envía un mensaje de media (audio, imagen, video, documento) en ventana de conversación abierta.
     * Usa un media_id previamente obtenido al subir el archivo a Meta con uploadMedia().
     */
    public function sendMediaMessage(
        WhatsAppMetaAPIConnection $connection,
        string $toPhoneNumber,
        string $mediaType,
        string $mediaId,
        bool $isVoiceNote = false,
        ?string $filename = null,
    ): array {
        $mediaPayload = ['id' => $mediaId];
        if ($mediaType === 'audio' && $isVoiceNote) {
            $mediaPayload['voice'] = true;
        }
        if ($mediaType === 'document' && $filename) {
            $mediaPayload['filename'] = $filename;
        }

        $payload = [
            'type' => $mediaType,
            'to' => $toPhoneNumber,
            $mediaType => $mediaPayload,
            'recipient_type' => 'individual',
            'messaging_product' => 'whatsapp',
        ];
        $endpoint = $this->getSendMessageEndpoint($connection);
        $response = Http::withToken($connection->access_token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($endpoint, $payload)
        ;
        if (!$response->successful()) {
            throw new Exception($response->body());
        }
        return $response->json();
    }


    public function sendTextMessage(
        WhatsAppMetaAPIConnection $connection,
        string $toPhoneNumber,
        string $messageText
    ): array {
        $payload = [
            'type' => 'text',
            'to' => $toPhoneNumber,
            'recipient_type' => 'individual',
            'messaging_product' => 'whatsapp',
            'text' => ['preview_url' => false, 'body' => $messageText],
        ];
        $endpoint = $this->getSendMessageEndpoint($connection);
        $response = Http::withToken($connection->access_token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($endpoint, $payload)
        ;
        if (!$response->successful()) {
            throw new Exception($response->body());
        }
        $responseArr = $response->json();
        return $responseArr;
    }


    public function sendTextMessageFromKapsoAPI(string $toPhoneNumber, string $messageText): array
    {
        $payload = [
            'type' => 'text',
            'to' => $toPhoneNumber,
            'recipient_type' => 'individual',
            'messaging_product' => 'whatsapp',
            'text' => ['preview_url' => false, 'body' => $messageText],
        ];

        $kapsoApiKey = config('app.kapso.api_key');
        // +1 318 706-2664 (Kapso) (meta phone ID: 976199978920754)
        $kapsoPhoneNumberId = config('app.kapso.wap_sales_agent_meta_phone_id');
        $headers = ['X-API-Key' => $kapsoApiKey, 'Content-Type' => 'application/json'];
        $endpoint = "https://api.kapso.ai/meta/whatsapp/v24.0/{$kapsoPhoneNumberId}/messages";

        $response = Http::withHeaders($headers)->post($endpoint, $payload);
        if (!$response->successful()) {
            throw new Exception($response->body());
        }
        $responseArr = $response->json();
        return $responseArr;
    }


    public function sendButtonsMessage(
        WhatsAppMetaAPIConnection $connection,
        string $toPhoneNumber,
        string $textMessage,
        array $buttonLegends
    ): array {
        $actionButtons = [];
        $buttonLegends = array_slice(array_values($buttonLegends), 0, 3);
        foreach ($buttonLegends as $buttonLegend) {
            $buttonLegend = Str::limit($buttonLegend, 24);
            $actionButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'title' => $buttonLegend,
                    'id' => 'btn_' . Str::slug($buttonLegend, '_'),
                ],
            ];
        }
        if (empty($actionButtons)) {
            return $this->sendTextMessage($connection, $toPhoneNumber, $textMessage);
        }
        $payload = [
            'to' => $toPhoneNumber,
            'type' => 'interactive',
            'messaging_product' => 'whatsapp',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $textMessage],
                'action' => ['buttons' => $actionButtons],
            ],
        ];

        $endpoint = $this->getSendMessageEndpoint($connection);
        $response = Http::withToken($connection->access_token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($endpoint, $payload)
        ;
        if (!$response->successful()) {
            throw new Exception('Error al enviar botones: ' . $response->body());
        }
        return $response->json();
    }


    public function sendListMessage(
        WhatsAppMetaAPIConnection $connection,
        string $toPhoneNumber,
        string $textMessage,
        array $optionLegends
    ): array {
        $rows = [];
        $optionLegends = array_slice(array_values($optionLegends), 0, 10);
        foreach ($optionLegends as $optionLegend) {
            $optionLegend = Str::limit($optionLegend, 24);
            $rows[] = [
                'title' => $optionLegend,
                'id' => 'row_' . Str::slug($optionLegend, '_'),
            ];
        }
        if (empty($rows)) {
            return $this->sendTextMessage($connection, $toPhoneNumber, $textMessage);
        }
        $payload = [
            'to' => $toPhoneNumber,
            'type' => 'interactive',
            'messaging_product' => 'whatsapp',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => $textMessage],
                'action' => [
                    'button' => 'Elegir',
                    'sections' => [
                        ['title' => 'Opciones', 'rows' => $rows],
                    ],
                ],
            ],
        ];

        $endpoint = $this->getSendMessageEndpoint($connection);
        $response = Http::withToken($connection->access_token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($endpoint, $payload)
        ;
        if (!$response->successful()) {
            throw new Exception('Error al enviar lista: ' . $response->body());
        }
        return $response->json();
    }


    public function sendTypingIndicator(WhatsAppMetaAPIConnection $connection, string $messageId): void
    {
        $payload = [
            'status' => 'read',
            'message_id' => $messageId,
            'messaging_product' => 'whatsapp',
            'typing_indicator' => ['type' => 'text'],
        ];
        $endpoint = $this->getSendMessageEndpoint($connection);
        Http::withToken($connection->access_token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($endpoint, $payload)
        ;
    }


    /**
     * Pide código para verificar el teléfono.
     * Este flujo [requestVerificationCode() - verifyCode() - registerNumber()] no se usa en nuestro flow.
     * Sirve SOLO para números que estén dando de alta ahora y no usen la app de WhatsApp Business.
     * Usar WhatsApp Business en el celular es precondición.
     */
    public function requestVerificationCode($phoneNumberId, $accessToken)
    {
        $endpoint = "https://graph.facebook.com/v23.0/{$phoneNumberId}/request_code";
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$accessToken}",
        ])->post($endpoint, [
            'language' => 'es_ES',
            'code_method' => 'SMS', // o 'VOICE' para llamada
        ]);
        if (!$response->ok()) {
            throw new Exception($response->body());
        }
        return $response->json();
    }


    /**
     * Verifica el código recibido por SMS/VOICE
     */
    public function verifyCode(string $phoneNumberId, string $verificationCode, string $accessToken): bool
    {
        $res = Http::withToken($accessToken)->post(
            "https://graph.facebook.com/v23.0/{$phoneNumberId}/verify_code",
            ['code' => $verificationCode]
        );
        if (!$res->ok()) {
            throw new Exception($res->body());
        }
        return (bool) ($res->json()['success'] ?? false);
    }


    /**
     * Registra el número para Cloud API después de verificarlo
     */
    public function registerNumber(string $phoneId, string $accessToken, ?string $pin = null): array
    {
        $payload = ['messaging_product' => 'whatsapp'];
        if ($pin) {                       // pin = 2‑step verification si existe
            $payload['pin'] = $pin;
        }
        $res = Http::withToken($accessToken)->post("https://graph.facebook.com/v23.0/{$phoneId}/register", $payload);
        if (!$res->ok()) {
            throw new Exception($res->body());
        }
        return $res->json();              // { "success": true }
    }


    /**
     * Sube un archivo local a Meta media upload.
     */
    public function uploadMediaFromPath(
        string $filePath,
        string $filename,
        string $uploadType,
        string $mimeType,
        string $phoneNumberId,
        string $accessToken
    ): string {
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            throw new Exception("Could not read media file from path: {$filePath}");
        }
        return $this->uploadMediaFromContent(
            mimeType: $mimeType,
            filename: $filename,
            uploadType: $uploadType,
            fileContent: $fileContent,
            accessToken: $accessToken,
            phoneNumberId: $phoneNumberId,
        );
    }


    /**
     * Sube contenido binario a Meta media upload.
     */
    public function uploadMediaFromContent(
        string $fileContent,
        string $filename,
        string $uploadType,
        string $mimeType,
        string $phoneNumberId,
        string $accessToken
    ): string {
        if ($fileContent === '') {
            throw new Exception('Could not upload empty media content');
        }
        $endpoint = "https://graph.facebook.com/v23.0/{$phoneNumberId}/media";
        $response = Http::withToken($accessToken)
            ->attach('file', $fileContent, $filename, ['Content-Type' => $mimeType])
            ->post($endpoint, ['type' => $uploadType, 'messaging_product' => 'whatsapp'])
        ;
        if (!$response->ok()) {
            throw new Exception($response->body());
        }
        return $response->json('id');
    }


    public function uploadMediaResumable(
        string $filePath,
        string $fileName,
        string $fileSize,
        string $mimeType,
        string $accessToken
    ): string {
        // Paso 1: Crear la sesión de subida (query string, como indica la doc)
        $response = Http::withoutVerifying()->post("https://graph.facebook.com/v23.0/{$this->appId}/uploads", [
            'file_name'    => $fileName,
            'file_length'  => $fileSize,
            'file_type'    => $mimeType,
            'access_token' => $accessToken,
        ]);
        if (!$response->ok()) {
            throw new Exception($response->body());
        }

        $session = $response->json();
        $uploadSessionId = $session['id'] ?? null;
        if (!$uploadSessionId) {
            throw new Exception('Error:' . json_encode($session));
        }

        $endpoint = "https://graph.facebook.com/v23.0/{$uploadSessionId}";
        $fileContents = file_get_contents($filePath);
        $uploadResponse = Http::withBody($fileContents, $mimeType)
            ->withHeaders([
                'file_offset' => '0',
                'Content-Type' => $mimeType,
                'Authorization' => "OAuth {$accessToken}",
            ])
            ->post($endpoint)
        ;
        if (!$uploadResponse->ok()) {
            throw new Exception($uploadResponse->body());
        }

        $handleId = $uploadResponse->json('h');
        if (!$handleId) {
            throw new Exception("Missing response param" . json_encode($uploadResponse->json()));
        }
        $rawHandleId = (string) $uploadResponse->json('h'); // puede venir con saltos de linea
        // separo por saltos y tomo el primero no vacío
        $handles = preg_split("/\r\n|\n|\r/", trim($rawHandleId));
        $handleId  = trim($handles[0] ?? '');

        if ($handleId === '' || strpos($handleId, '4:') !== 0) {
            throw new \RuntimeException('Handle inválido: ' . $rawHandleId);
        }
        // elimina cualquier whitespace interno:
        $handleId = preg_replace('/\s+/', '', $handleId);
        return $handleId;
    }


    public function getMediaInfo(string $mediaId, string $accessToken): array
    {
        $endpoint = "https://graph.facebook.com/v23.0/{$mediaId}";
        $response = \Illuminate\Support\Facades\Http::withToken($accessToken)->get($endpoint);

        if (!$response->ok()) {
            throw new Exception("Error al obtener info del media {$mediaId}: " . $response->body());
        }
        return $response->json();
    }


    public function downloadMediaFile(string $url, string $accessToken): string
    {
        $response = Http::withToken($accessToken)->get($url);

        if (!$response->ok()) {
            throw new Exception("Error al descargar media desde {$url}: " . $response->status());
        }
        return $response->body();
    }


    private function getSendMessageEndpoint(WhatsAppMetaAPIConnection $connection): string
    {
        $phoneNumberId = $connection->phone_number_id;
        if (!$phoneNumberId) {
            throw new Exception('La conexión no posee phone_number_id configurado');
        }
        return "https://graph.facebook.com/v23.0/{$phoneNumberId}/messages";
    }


    public function validateWebhook(string $mode, string $token, string $challenge): string
    {
        $data = '';
        if ($mode == 'subscribe' && $token === 'godixital') {
            $data = $challenge;
        }
        return $data;
    }

}
