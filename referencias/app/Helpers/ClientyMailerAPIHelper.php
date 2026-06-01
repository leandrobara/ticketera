<?php

namespace App\Helpers;

use DateTime;
use Exception;
use App\Models\Email;
use Illuminate\Support\Collection;
use App\DTO\MailerSendResponseDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use App\DTO\MailerScheduleResponseDTO;
use App\DTO\Attachments\SaveAttachmentDTO;
use App\DTO\MailerSendRequestParametersDTO;
use App\DTO\MailerMassiveScheduleResponseDTO;
use App\Helpers\ClientyMailerValidatorHelper;
use App\DTO\MailerScheduleRequestParametersDTO;
use App\DTO\Attachments\ClientyMailerAttachmentDTO;
use App\DTO\MailerQuickEmailSendRequestParametersDTO;
use App\DTO\MailerMassiveScheduleRequestParametersDTO;
use App\DTO\MailerQuickEmailScheduleRequestParametersDTO;
use App\Exceptions\Helpers\ClientyMailer\ClientyMailerException;


class ClientyMailerAPIHelper
{

    protected $route;
    protected $timeout;
    protected $clientyJwtAlgo;
    protected $clientyJwtSecret;
    protected $clientyMailerValidator;


    public function __construct(
        ClientyMailerValidatorHelper $clientyMailerValidator,
        string $route,
        string $clientyJwtSecret,
        string $clientyJwtAlgo,
        int $timeout
    ) {
        $this->route = $route;
        $this->timeout = $timeout;
        $this->clientyJwtAlgo = $clientyJwtAlgo;
        $this->clientyJwtSecret = $clientyJwtSecret;
        $this->clientyMailerValidator = $clientyMailerValidator;
    }


    public function sendEmail(
        MailerSendRequestParametersDTO $mailerSendParamsDTO
    ): MailerSendResponseDTO {
        $this->clientyMailerValidator->validateSendRequestParametersDTO($mailerSendParamsDTO);
        try {
            $token = $this->getJWT();
            $endpoint = $this->route . '/email/send';
            $params = $mailerSendParamsDTO->toArray();
            $response = Http::withToken($token)
                ->asJson()
                ->timeout($this->timeout)
                ->withOptions(['verify' => false])
                ->post($endpoint, $params)
            ;
        } catch (Exception $e) {
            throw new ClientyMailerException($e->getMessage());
        }
        $response = $this->parseResponse($response);
        $dto = MailerSendResponseDTO::buildFromResponseArray($response);
        return $dto;
    }


    public function scheduleEmail(
        MailerScheduleRequestParametersDTO $mailerScheduleParamsDTO
    ): MailerScheduleResponseDTO {
        $this->clientyMailerValidator->validateScheduleRequestParametersDTO($mailerScheduleParamsDTO);
        try {
            $token = $this->getJWT();
            $endpoint = $this->route . '/email/schedule';
            $params = $mailerScheduleParamsDTO->toArray();
            $response = Http::withToken($token)
                ->asJson()
                ->timeout($this->timeout)
                ->withOptions(['verify' => false])
                ->post($endpoint, $params)
            ;
        } catch (Exception $e) {
            throw new ClientyMailerException($e->getMessage());
        }
        $response = $this->parseResponse($response);
        $dto = MailerScheduleResponseDTO::buildFromResponseArray($response);
        return $dto;
    }


    public function sendQuickEmail(
        MailerQuickEmailSendRequestParametersDTO $sendParamsDTO
    ): MailerSendResponseDTO {
        $this->clientyMailerValidator->validateSendRequestParametersDTO($sendParamsDTO);

        try {
            $token = $this->getJWT();
            $params = $sendParamsDTO->toArray();
            $endpoint = $this->route . '/quick-email/send';
            $response = Http::withToken($token)
                ->asJson()
                ->timeout($this->timeout)
                ->withOptions(['verify' => false])
                ->post($endpoint, $params)
            ;
        } catch (Exception $e) {
            throw new ClientyMailerException($e->getMessage());
        }
        $response = $this->parseResponse($response);
        $dto = MailerSendResponseDTO::buildFromResponseArray($response);
        return $dto;
    }


    public function scheduleQuickEmail(
        MailerQuickEmailScheduleRequestParametersDTO $scheduleParamsDTO
    ): MailerScheduleResponseDTO {
        $this->clientyMailerValidator->validateScheduleRequestParametersDTO($scheduleParamsDTO);

        try {
            $response = Http::withToken($this->getJWT())
                ->asJson()
                ->timeout($this->timeout)
                ->withOptions(['verify' => false])
                ->post($this->route . '/quick-email/schedule', $scheduleParamsDTO->toArray())
            ;
        } catch (Exception $e) {
            throw new ClientyMailerException($e->getMessage());
        }
        $response = $this->parseResponse($response);
        $dto = MailerScheduleResponseDTO::buildFromResponseArray($response);
        return $dto;
    }


    // Emails that are being sent by Clienty CRM to notify users and so on.
    public function scheduleSystemEmail(
        MailerScheduleRequestParametersDTO $mailerScheduleParamsDTO
    ): MailerScheduleResponseDTO {
        $mailerScheduleParamsDTO->from = $this->systemEmailFrom;
        $mailerScheduleParamsDTO->fromName = $this->systemNameFrom;

        $responseDTO = $this->scheduleEmail($mailerScheduleParamsDTO);
        return $responseDTO;
    }


    public function scheduleMassiveEmail(
        MailerMassiveScheduleRequestParametersDTO $scheduleParamsDTO
    ): MailerMassiveScheduleResponseDTO {

        $this->clientyMailerValidator->validateMassiveScheduleRequestParametersDTO($scheduleParamsDTO);
        try {
            $response = Http::withToken($this->getJWT())
                ->asJson()
                ->timeout($this->timeout)
                ->withOptions(['verify' => false])
                ->post($this->route . '/email/massive/schedule', $scheduleParamsDTO->toArray())
            ;
        } catch (Exception $e) {
            throw new ClientyMailerException($e->getMessage());
        }
        $response = $this->parseResponse($response);
        $dto = MailerMassiveScheduleResponseDTO::buildFromResponseArray($response);
        return $dto;
    }


    public function cancelEmails(Collection $emails): Collection
    {
        try {
            $emailExternalIds = $emails->pluck('external_id');
            $params = ['id' => $emailExternalIds];
            $route = $this->route . '/email/cancel';
            $response = Http::withToken($this->getJWT())
                ->asJson()
                ->timeout($this->timeout)
                ->withOptions(['verify' => false])
                ->post($route, $params)
            ;
        } catch (Exception $e) {
            throw new ClientyMailerException($e->getMessage());
        }
        $response = $this->parseResponse($response);
        return collect($response);
    }


    public function cancelMassiveEmail(string $externalMassiveId): Collection
    {
        try {
            $route = $this->route . '/email/massive/cancel';
            $params = ['massive_sending_id' => $externalMassiveId];
            $response = Http::withToken($this->getJWT())
                ->asJson()
                ->timeout($this->timeout)
                ->withOptions(['verify' => false])
                ->post($route, $params)
            ;
        } catch (Exception $e) {
            throw new ClientyMailerException($e->getMessage());
        }
        $response = $this->parseResponse($response);
        $externalEmailIds = collect($response)->flatten();
        return $externalEmailIds;
    }


    public function doEmailVerification(string $email): array
    {
        try {
            $token = $this->getJWT();
            $response = Http::withToken($token)->asJson()
                ->timeout($this->timeout)
                ->withOptions(['verify' => false])
                ->post($this->route . '/email_address/verify_aws_identity', ['email' => $email]);
        } catch (Exception $e) {
            throw new ClientyMailerException($e->getMessage());
        }

        return $this->parseResponse($response);
    }


    public function deleteEmailVerification(string $email): array
    {
        try {
            $token = $this->getJWT();
            $response = Http::withToken($token)
                ->asJson()
                ->timeout($this->timeout)
                ->withOptions(['verify' => false])
                ->post($this->route . '/email_address/unverify_aws_identity', ['email' => $email]);
        } catch (Exception $e) {
            throw new ClientyMailerException($e->getMessage());
        }
        return $this->parseResponse($response);
    }


    public function emailIsVerified(string $email): bool
    {
        try {
            $token = $this->getJWT();
            $params = ['email' => $email];
            $endpoint = $this->route . '/email_address/is_aws_verified';
            $response = Http::withToken($token)
                ->asJson()
                ->timeout($this->timeout)
                ->withOptions(['verify' => false])
                ->post($endpoint, $params)
            ;
        } catch (Exception $e) {
            throw new ClientyMailerException($e->getMessage());
        }
        $response = $this->parseResponse($response);
        return $response['isVerified'];
    }


    public function getSentEmails(array $requestParams)
    {
        try {
            $token = $this->getJWT();
            $response = Http::withToken($token)
                ->asJson()
                ->timeout($this->timeout)
                ->withOptions(['verify' => false])
                ->post($this->route . '/email', $requestParams)
            ;
        } catch (Exception $e) {
            throw new ClientyMailerException($e->getMessage());
        }

        return $response = $this->parseResponse($response);
    }


    public function getSentEmail(Email $email, array $requestParams)
    {
        try {
            $token = $this->getJWT();
            $response = Http::withToken($token)
                ->asJson()
                ->timeout($this->timeout)
                ->withOptions(['verify' => false])
                ->get($this->route . '/email/' . $email->external_id, $requestParams)
            ;
        } catch (Exception $e) {
            throw new ClientyMailerException($e->getMessage());
        }

        return $response = $this->parseResponse($response);
    }


    public function getMassiveSentEmails(array $requestParams)
    {
        try {
            $token = $this->getJWT();
            $response = Http::withToken($token)
                ->asJson()
                ->timeout($this->timeout)
                ->withOptions(['verify' => false])
                ->get($this->route . '/email/massive', $requestParams)
            ;
        } catch (Exception $e) {
            throw new ClientyMailerException($e->getMessage());
        }

        return $response = $this->parseResponse($response);
    }


    public function saveAttachment(SaveAttachmentDTO $saveAttachmentDTO): ClientyMailerAttachmentDTO
    {
        // $this->clientyMailerValidator->validateAttachmentDTO($mailerSendParamsDTO);
        try {
            $attachment = file_get_contents($saveAttachmentDTO->pathname);
            $response = Http::withToken($this->getJWT())
                ->attach('attachment', $attachment, $saveAttachmentDTO->name)
                ->asMultipart()
                ->timeout($this->timeout)
                ->withOptions(['verify' => false])
                ->post($this->route . '/attachment')
            ;
        } catch (Exception $e) {
            throw new ClientyMailerException($e->getMessage());
        }

        $response = $this->parseResponse($response);
        $dto = ClientyMailerAttachmentDTO::buildFromResponseArray($response);
        return $dto;
    }


    public function getAttachmentRawDataByHash(string $attachmentHash): string
    {
        try {
            $rawData = Http::withToken($this->getJWT())
                ->timeout($this->timeout)
                ->withOptions(['verify' => false])
                ->get($this->route . "/attachment/{$attachmentHash}/download")
            ;
        } catch (Exception $e) {
            throw new ClientyMailerException($e->getMessage());
        }
        return $rawData;
    }

    public function getAwsDkimInfo(string $domain)
    {
        try {
            $params = ['domain' => $domain];
            $response = Http::withToken($this->getJWT())
                ->timeout($this->timeout)
                ->withOptions(['verify' => false])
                ->get($this->route . "/aws-ses-identity/dkim", $params)
            ;
        } catch (Exception $e) {
            throw new ClientyMailerException($e->getMessage());
        }
        
        return $response = $this->parseResponse($response);
    }


    public function ensureAwsDkimIntegrity(string $domain)
    {
        try {
            $params = ['domain' => $domain];
            $response = Http::withToken($this->getJWT())
                ->timeout($this->timeout)
                ->withOptions(['verify' => false])
                ->post($this->route . "/aws-ses-identity/dkim", $params)
            ;
        } catch (Exception $e) {
            throw new ClientyMailerException($e->getMessage());
        }
        
        return $response = $this->parseResponse($response);
    }


    private function parseResponse(Response $response): array
    {
        $jsonResponse = json_decode($response->body(), true);
        if (!$jsonResponse) {
            throw new ClientyMailerException('Mailer API Response: ' . $response->body());
        }
        if (!$jsonResponse['success']) {
            throw new ClientyMailerException('Mailer API Response: ' . $jsonResponse['message']);
        }
        return $jsonResponse['data'];
    }


    private function getJWT()
    {
        $jwtInfo = [
            'sub' => 'clienty_crm',
            'exp' => (new DateTime('+' . $this->timeout . ' seconds'))->getTimestamp()
        ];
        return JwtHelper::encode($jwtInfo, $this->clientyJwtSecret, $this->clientyJwtAlgo);
    }
}
