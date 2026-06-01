<?php

namespace App\Helpers;

use Exception;
use Illuminate\Support\Str;
use InvalidArgumentException;
use App\Helpers\AIPromptsHelper;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Exception\GuzzleException;


class OpenAIHelper
{

    private GuzzleClient $client;
    private string $transcriptionEndpoint;
    private string $chatCompletionEndpoint;

    private const MAX_FILE_SIZE = 25 * 1024 * 1024; // 25MB limit for OpenAI API
    private const ALLOWED_EXTENSIONS = ['webm', 'mp3', 'mp4', 'mpeg', 'mpga', 'wav', 'ogg'];


    public function __construct(private readonly string $openAIApiKey)
    {
        $this->client = new GuzzleClient(['timeout' => 30, 'connect_timeout' => 5]);
        $this->chatCompletionEndpoint = 'https://api.openai.com/v1/chat/completions';
        $this->transcriptionEndpoint = 'https://api.openai.com/v1/audio/transcriptions';
    }


    public function transcribeAudioFromStorage(
        string $disk,
        string $filePath,
        string $model = 'whisper-1',
        ?string $language = 'es',
    ): ?string {
        if (!Storage::disk($disk)->exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        // Obtener el contenido del archivo
        $fileContent = Storage::disk($disk)->get($filePath);

        return $this->transcribeAudioContent($fileContent, $filePath, $model, $language);
    }

    
    public function transcribeAudioContent(
        string $content,
        string $filename,
        string $model = 'whisper-1',
        ?string $language = 'es',
    ): ?string {
        try {
            $this->validateAudioFile($content, $filename);
            
            // Crear archivo temporal
            $tempFile = tmpfile();
            if ($tempFile === false) {
                throw new Exception('OpenAIHelper::transcribeAudioContent() - Failed to create temporary file');
            }

            fwrite($tempFile, $content);
            $multipart = $this->buildMultipartData($tempFile, $filename, $model, $language);
            
            $response = $this->client->request('POST', $this->transcriptionEndpoint, [
                'multipart' => $multipart,
                'headers' => ['Authorization' => "Bearer {$this->openAIApiKey}"],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            if (!isset($data['text'])) {
                throw new Exception('OpenAIHelper::transcribeAudioContent() - Unexpected API response format');
            }

            $transcription = $data['text'];
            $cleanTranscription = $this->removeHallucinations($transcription);
            return $cleanTranscription;
        } catch (Exception $e) {
            // dump('EXCEPTION', $e);
            report($e);
            return null;
        }
    }


    public function extractLeadFromEmail(
        string $emailBody,
        string $customPrompt = '',
        array $customVariablesPrompts = []
    ): array {
        // Formatear las líneas correspondientes a los datos extra
        $formattedLines = [];
        foreach ($customVariablesPrompts as $fieldName => $explanation) {
            $fieldName = trim($fieldName);
            $explanation = trim($explanation);
            $formattedLines[] = "- El dato \"{$fieldName}\" debe extraerse siguiendo estas
                instrucciones: \"{$explanation}\". Este campo debe incluirse como clave dentro de custom_variables,
                incluso si su valor es null por no haber sido encontrado."
            ;
        }

        // Crear el prompt
        $search = ['{{EMAIL}}', '{{EXTRA_DATA}}', '{{EXTRA_PROMPT}}'];
        $replace = [$emailBody, implode("\n", $formattedLines), $customPrompt];
        $prompt = Str::replace($search, $replace, AIPromptsHelper::EXTRACT_LEAD_FROM_EMAIL_PROMPT);

        try {
            $apiKey = $this->openAIApiKey;
            $body = ['json' => ['model' => 'gpt-4.1', 'messages' => [['role' => 'user', 'content' => $prompt]]]];
            $header = ['headers' => ['Content-Type' => 'application/json', 'Authorization' => "Bearer {$apiKey}"]];

            $response = $this->client->request('POST', $this->chatCompletionEndpoint, array_merge($header, $body));
        } catch (Exception $e) {
            report($e);
            return ['success' => false, 'data' => null, 'error' => 'openai_request_failed'];
        }

        // Parsear la respuesta recibida
        $body = json_decode($response->getBody()->getContents(), true);
        $json = json_decode($body['choices'][0]['message']['content'] ?? [], true);

        return $json;
    }


    private function validateAudioFile(string $content, string $filename): void
    {
        $fileSize = strlen($content);
        if ($fileSize > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException(
                'File size exceeds the maximum limit of ' . (self::MAX_FILE_SIZE / 1024 / 1024) . 'MB'
            );
        }
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            throw new InvalidArgumentException(
                'Invalid file type. Allowed types: ' . implode(', ', self::ALLOWED_EXTENSIONS)
            );
        }
    }


    private function buildMultipartData($fileResource, string $filename, string $model, ?string $language): array
    {
        $multipart = [
            ['name' => 'model', 'contents' => $model],
            ['name' => 'file', 'filename' => basename($filename), 'contents' => $fileResource],
        ];
        if ($language) {
            $multipart[] = ['name' => 'language', 'contents' => $language];
        }
        return $multipart;
    }


    private function removeHallucinations(?string $transcription): ?string
    {
        $hallucinationPatterns = [
            'en' => [
                'www.mooji.org',
            ],
            'es' => [
                'Subtítulos realizados por la comunidad de Amara.org',
                'Subtitulado por la comunidad de Amara.org',
                'Subtítulos por la comunidad de Amara.org',
                'Subtítulos creados por la comunidad de Amara.org',
                'Subtítulos en español de Amara.org',
                'Subtítulos hechos por la comunidad de Amara.org',
                'Subtitulos por la comunidad de Amara.org',
                'Más información www.alimmenta.com',
                'www.mooji.org',
            ],
        ];
        if (!$transcription || !trim($transcription)) {
            return null;
        }
        $allPatterns = array_merge($hallucinationPatterns['en'], $hallucinationPatterns['es']);
        $cleanText = $transcription;
        foreach ($allPatterns as $pattern) {
            $cleanText = str_ireplace($pattern, '', $cleanText);
        }
        $cleanText = trim($cleanText);
        $cleanText = preg_replace('/\s+/', ' ', $cleanText);
        return $cleanText ?: null;
    }

}
