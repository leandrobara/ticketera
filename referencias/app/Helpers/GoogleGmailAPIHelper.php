<?php

namespace App\Helpers;

use Exception;
use Google_Client;
use Google\Service\Gmail;
use Illuminate\Support\Str;
use Google\Service\Gmail\Message;
use Illuminate\Support\Collection;
use App\Models\GoogleAPIUserToken;
use Google\Service\Gmail\MessagePart;
use Google\Service\Gmail\MessagePartHeader;


class GoogleGmailAPIHelper
{

    const HTML_HEADER_NAME = 'text/html';
    const PLAIN_HEADER_NAME = 'text/plain';
    const MULTIPART_HEADER_NAME = 'multipart/alternative';
    const MULTIPART_MIXED_HEADER_NAME = 'multipart/mixed';
    const MULTIPART_RELATED_HEADER_NAME = 'multipart/related';

    const CLIENTY_EMAIL_METADATA_FLAG_END = '/__Delivered_by_Clienty_Mailer__';
    const CLIENTY_EMAIL_METADATA_FLAG_START = '__Delivered_by_Clienty_Mailer__';
    const CLIENTY_EMAIL_METADATA_FLAG_SEARCH = '__Delivered_by_Clienty_Mailer__';


    public function getBodyStringFromGmailMessage(Message $populatedGmailMessage): string
    {
        $this->failIfNotPopulated($populatedGmailMessage);

        $parts = $populatedGmailMessage->getPayload()->getParts();
        if (is_object($parts)) {
            $parts = $parts[0]->getParts();
        }
        if ($parts) {
            $bodyData = $this->buildBodyStringFromPartsHeaders($parts);
            return $bodyData;
        }
        $bodyData = $populatedGmailMessage->getPayload()->getBody()->getData();
        $bodyData = ($bodyData) ? base64_decode(strtr($bodyData, '-_', '+/')) : '';
        return $bodyData;
    }


    protected function buildBodyStringFromPartsHeaders(array $parts, array $opts = []): string
    {
        $resultParts = [
            self::HTML_HEADER_NAME => null,
            self::PLAIN_HEADER_NAME => null,
            self::MULTIPART_HEADER_NAME => null,
            self::MULTIPART_RELATED_HEADER_NAME => null,
        ];

        foreach ($parts as $part) {
            $bodyString = $this->getBodyStringFromNestedParts($part);
            
            $headers = $part->getHeaders();
            foreach ($headers as $header) {
                $headerName = $header->getName();
                $headerValue = $header->getValue();
                
                $isContentTypeHeader = Str::lower($headerName) == 'content-type';
                $isHtmlContent = Str::contains($headerValue, self::HTML_HEADER_NAME);
                $isPlainContent = Str::contains($headerValue, self::PLAIN_HEADER_NAME);
                $isMultipartContent = Str::contains($headerValue, self::MULTIPART_HEADER_NAME);
                $isMultipartMixedContent = Str::contains($headerValue, self::MULTIPART_MIXED_HEADER_NAME);
                $isMultipartRelatedContent = Str::contains($headerValue, self::MULTIPART_RELATED_HEADER_NAME);
                $isDataHeader =
                    $isHtmlContent ||
                    $isPlainContent ||
                    $isMultipartContent ||
                    $isMultipartMixedContent ||
                    $isMultipartRelatedContent
                ;
                if (!$isContentTypeHeader || !$isDataHeader) {
                    continue;
                }

                $contentHeaderName = self::HTML_HEADER_NAME;
                if ($isPlainContent) {
                    $contentHeaderName = self::PLAIN_HEADER_NAME;
                }
                if ($isMultipartContent) {
                    $contentHeaderName = self::MULTIPART_HEADER_NAME;
                }
                if ($isMultipartMixedContent) {
                    $contentHeaderName = self::MULTIPART_MIXED_HEADER_NAME;
                }
                if ($isMultipartRelatedContent) {
                    $contentHeaderName = self::MULTIPART_RELATED_HEADER_NAME;
                }
                $resultParts[$contentHeaderName] = $bodyString . $this->base64UrlDecode($part->getBody()->getData());
            }
        }

        // Quito lo que NO tienen el flag.
        foreach ($resultParts as $index => $bodyPart) {
            $bodyPart = str_ireplace('<wbr>', '', $bodyPart);
            $hasFlag = stripos($bodyPart, self::CLIENTY_EMAIL_METADATA_FLAG_START) !== false;
            if (!$hasFlag) {
                $resultParts[$index] = null;
            }
        }

        $resultBody = $resultParts[self::HTML_HEADER_NAME]
            ?? $resultParts[self::PLAIN_HEADER_NAME]
            ?? $resultParts[self::MULTIPART_HEADER_NAME]
            ?? $resultParts[self::MULTIPART_MIXED_HEADER_NAME]
            ?? $resultParts[self::MULTIPART_RELATED_HEADER_NAME]
            ?? ''
        ;
        return $resultBody;
    }


    public function isResponseToUser(Message $populatedGmailMessage, string $userEmailAddr): bool
    {
        $this->failIfNotPopulated($populatedGmailMessage);
        $headers = collect($populatedGmailMessage->getPayload()->getHeaders());

        $isDeliveredToEmailAddr = $headers->filter(function ($h) use ($userEmailAddr) {
            return ($h->name == 'Delivered-To') && Str::contains($h->value, $userEmailAddr);
        })->first();
        if (!$isDeliveredToEmailAddr) {
            return false;
        }
        $inReplyToHeader = $headers->where('name', 'In-Reply-To')->first();
        if (!$inReplyToHeader || !$inReplyToHeader?->value) {
            return false;
        }
        $referencesHeader = $headers->where('name', 'References')->first();
        if (!$referencesHeader) {
            return false;
        }
        $hasAWSReference = Str::contains($referencesHeader->value, 'amazonses.com');
        if (!$hasAWSReference) {
            return false;
        }

        return true;
    }


    public function isResponseFromUser(Message $populatedGmailMessage, string $userEmailAddr): bool
    {
        $this->failIfNotPopulated($populatedGmailMessage);
        $headers = collect($populatedGmailMessage->getPayload()->getHeaders());

        $isFromEmailAddr = $headers->filter(function ($h) use ($userEmailAddr) {
            return ($h->name == 'From') && Str::contains($h->value, $userEmailAddr);
        })->first();
        if (!$isFromEmailAddr) {
            return false;
        }
        $isInReply = $headers->where('name', 'In-Reply-To')->first();
        if (!$isInReply) {
            return false;
        }
        $referencesHeader = $headers->where('name', 'References')->first();
        if (!$referencesHeader) {
            return false;
        }
        $hasAWSReference = Str::contains($referencesHeader->value, 'amazonses.com');
        if (!$hasAWSReference) {
            return false;
        }

        return true;
    }


    public function getClientyMetadataFromBody(string $body): ?array
    {
        // Fix: remueve el tag "word break opportunity" si fue añadido por aĺgún externo al body.
        // este tag es añadido a veces en medio del flag causando errores.
        $body = str_ireplace('<wbr>', '', $body);

        $endFlag = self::CLIENTY_EMAIL_METADATA_FLAG_END;
        $startFlag = self::CLIENTY_EMAIL_METADATA_FLAG_START;
        $metadataStr = trim(Str::between($body, $startFlag, $endFlag));
        $metadataStr = strip_tags(trim($metadataStr));
        $metadataStr = trim(str_replace([PHP_EOL, '>', '<', '&gt;', '&lt;', "\r"], '', trim($metadataStr)));
        
        if (!$metadataStr) {
            return null;
        }
        $metadataStr = html_entity_decode($metadataStr);
        // Fix locos: cosas que aparecen metidas en el medio del encode.
        $metadataStr = str_replace('clientBuen dia', 'client', $metadataStr);
        $metadataStr = str_replace(':Bu{', ':{', $metadataStr);
        $metadataStr = str_replace('  :"', ':"', $metadataStr);
        $metadataStr = str_replace(':  "', ':"', $metadataStr);
        $metadataStr = str_replace(':" ', ':"', $metadataStr);
        $metadataStr = str_replace(': "', ':"', $metadataStr);
        $metadataStr = str_replace(' :"', ':"', $metadataStr);

        $metadataArr = unserialize($metadataStr);
        if (!$metadataArr || !($metadataArr['eid'] ?? null)) {
            return null;
        }

        $clientyMetadata = [
            'email' => [
                'external_id' => $metadataArr['eid'],
                'external_massive_id' => $metadataArr['msid'] ?? null,
            ],
        ];
        $mailerCustomMetadata = $metadataArr['custom_metadata'] ?? [];
        foreach ($mailerCustomMetadata as $key => $val) {
            $clientyMetadata[$key] = $val;
        }
        return $clientyMetadata;
    }


    public function getSentMessageId(Message $populatedGmailMessage): string
    {
        $header = $this->findHeaderByName(
            $populatedGmailMessage,
            'Message-ID',
            ['failIfNotExists' => true, 'caseInsensitive' => true]
        );
        return $header->value;
    }


    public function getInReplyToId(Message $populatedGmailMessage): string
    {
        $header = $this->findHeaderByName($populatedGmailMessage, 'In-Reply-To');
        return $header->value;
    }


    public function getReferencesIds(Message $populatedGmailMessage): array
    {
        $header = $this->findHeaderByName($populatedGmailMessage, 'References');
        if (!$header || !$header->value) {
            return [];
        }
        return explode(' ', $header->value);

    }


    public function getSubject(Message $populatedGmailMessage): string
    {
        $header = $this->findHeaderByName($populatedGmailMessage, 'Subject', ['failIfNotExists' => true]);
        return $header->value;
    }


    public function getEmailAddressTo(Message $populatedGmailMessage): string
    {
        $header = $this->findHeaderByName($populatedGmailMessage, 'To', ['failIfNotExists' => true]);
        $emailAddr = $this->getEmailAddressFromHeader($header);
        return $emailAddr;
    }


    public function getEmailAddressFrom(Message $populatedGmailMessage): string
    {
        $header = $this->findHeaderByName($populatedGmailMessage, 'From', ['failIfNotExists' => true]);
        $emailAddr = $this->getEmailAddressFromHeader($header);
        return $emailAddr;
    }


    public function getEmailNameTo(Message $populatedGmailMessage): ?string
    {
        $header = $this->findHeaderByName($populatedGmailMessage, 'To');
        $name = $header ? $this->getEmailNameFromHeader($header) : null;
        return $name;
    }


    public function getEmailNameFrom(Message $populatedGmailMessage): ?string
    {
        $header = $this->findHeaderByName($populatedGmailMessage, 'From');
        $name = $header ? $this->getEmailNameFromHeader($header) : null;
        return $name;
    }


    protected function base64UrlEncode($inputStr)
    {
        return strtr(base64_encode($inputStr), '+/=', '-_,');
    }


    protected function base64UrlDecode($inputStr)
    {
        return base64_decode(strtr($inputStr, '-_,', '+/='));
    }


    protected function findHeaderByName(Message $gmailMessage, string $headerName, $opts = []): ?MessagePartHeader
    {
        $this->failIfNotPopulated($gmailMessage);

        $caseInsensitive = $opts['caseInsensitive'] ?? false;
        $failIfNotExists = $opts['failIfNotExists'] ?? false;

        if ($caseInsensitive) {
            $header = collect($gmailMessage->getPayload()->getHeaders())->filter(function ($header) use ($headerName) {
                return Str::lower($header->name) == Str::lower($headerName);
            })->first();
        } else {
            $header = collect($gmailMessage->getPayload()->getHeaders())->where('name', $headerName)->first();
        }

        if ($failIfNotExists && !$header) {
            throw new Exception("GoogleGmailAPIHelper: not existent email {$headerName} header");
        }
        return $header;
    }


    protected function getEmailAddressFromHeader(MessagePartHeader $header): string
    {
        $emailAddrString = $header->value;
        $arr = explode('<', $emailAddrString);
        $emailAddr = trim($arr[1] ?? $arr[0]);
        $emailAddr = Str::replaceFirst('>', '', $emailAddr);
        return $emailAddr;
    }


    protected function getEmailNameFromHeader(MessagePartHeader $header): ?string
    {
        $emailAddrString = $header->value;
        $arr = explode('<', $emailAddrString);
        $name = trim($arr[0]);
        return $name ?: null;
    }


    protected function getBodyStringFromNestedParts(MessagePart $part): string
    {
        $bodyString = '';
        $nestedParts = $part->getParts();

        $allParts = collect([]);
        foreach ($nestedParts as $part) {
            $allParts->push($part);
            $doubleNestedParts = collect($part->getParts());
            if ($doubleNestedParts->isNotEmpty()) {
                $allParts = $allParts->merge($doubleNestedParts);
            }
        }

        foreach ($allParts as $part) {
            $hasInfo = $part && $part->getBody() && $part->getBody()->getData();
            $mimeType = $part->getMimeType();
            $isHtmlContentHeader = Str::contains($mimeType, self::HTML_HEADER_NAME);
            if (!$hasInfo || !$isHtmlContentHeader) {
                continue;
            }
            $partData = $part->getBody()->getData();
            $bodyString .= $this->base64UrlDecode($partData);
        }

        return $bodyString;
    }


    protected function failIfNotPopulated(Message $gmailMessage): void
    {
        if (!$gmailMessage->getPayload()) {
            throw new Exception('GoogleGmailAPIHelper: Gmail Message is not populated');
        }
    }

}
