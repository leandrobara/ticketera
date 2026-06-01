<?php

namespace App\Helpers;

use Exception;
use Illuminate\Support\Facades\Http;


class AudioEncodingLambdaHelper
{

    private const LAMBDA_URL = 'https://rbpa7sap2wkvgrjfzpgopgez5y0vlnhb.lambda-url.us-east-1.on.aws/';


    public function __construct(private readonly string $lambdaFunctionSecretKey)
    {
    }


    /**
     * Convierte un archivo de audio (webm/mp4) a OGG/Opus vía Lambda.
     *
     * @param string $audioContent Contenido binario del audio original
     * @param string $sourceMimeType MIME type original del audio subido
     * @return string Bytes raw del audio OGG (binary string)
     */
    public function encodeToOgg(string $audioContent, string $sourceMimeType): string
    {
        $normalizedMimeType = strtolower(trim(explode(';', $sourceMimeType)[0]));
        $contentType = match ($normalizedMimeType) {
            'audio/mp4' => 'audio/mp4',
            'audio/webm', 'video/webm' => 'audio/webm;codecs=opus',
            default => null,
        };

        if (!$contentType) {
            throw new Exception("AudioEncodingLambdaHelper: mime type no soportado: {$sourceMimeType}");
        }

        if ($audioContent === '') {
            throw new Exception('AudioEncodingLambdaHelper: audio vacío');
        }

        $response = Http::timeout(30)
            ->withHeaders([
                'Content-Type' => $contentType,
                'x-api-key' => $this->lambdaFunctionSecretKey,
            ])
            ->withBody($audioContent, $contentType)
            ->post(self::LAMBDA_URL);

        $decoded = json_decode($response->body(), true);

        if (!$decoded || ($decoded['statusCode'] ?? 0) !== 200 || empty($decoded['body'])) {
            $errorData = [
                'statusCode' => $decoded['statusCode'] ?? 'unknown',
                'response' => $decoded['body'] ?? $response->body(),
            ];
            throw new Exception('AudioEncodingLambdaHelper: Lambda error - ' . json_encode($errorData));
        }

        $audioData = base64_decode($decoded['body'], true);
        if ($audioData === false) {
            throw new Exception('AudioEncodingLambdaHelper: base64 decode falló');
        }
        return $audioData;
    }
}
