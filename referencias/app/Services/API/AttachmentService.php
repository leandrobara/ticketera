<?php

namespace App\Services\API;

use App\Models\Client;
use App\Models\Attachment;
use Illuminate\Support\Collection;
use App\Helpers\ClientyMailerAPIHelper;
use App\Repositories\AttachmentRepository;
use App\DTO\Attachments\SaveAttachmentDTO;
use App\Services\Traits\GetClientFromRequest;


class AttachmentService
{

    use GetClientFromRequest;

    private $attachmentRepository;


    public function __construct(
        AttachmentRepository $attachmentRepository,
        ClientyMailerAPIHelper $clientyMailerAPIHelper
    ) {
        $this->attachmentRepository = $attachmentRepository;
        $this->clientyMailerAPIHelper = $clientyMailerAPIHelper;
    }


    public function save(SaveAttachmentDTO $attachmentDTO): Attachment
    {
        $mailerAttachmentDTO = $this->clientyMailerAPIHelper->saveAttachment($attachmentDTO);

        $existentAttachment = $this->findOneByClientAndHashAndName(
            $this->getClient(), $mailerAttachmentDTO->hash, $attachmentDTO->name
        );
        if ($existentAttachment) {
            return $existentAttachment;
        }

        $data = [
            'source' => 'clienty_mailer',
            'name' => $attachmentDTO->name,
            'size' => $mailerAttachmentDTO->size,
            'hash' => $mailerAttachmentDTO->hash,
            'client_id' => $this->getClient()->id,
            'extension' => $attachmentDTO->extension,
        ];
        $attachment = $this->attachmentRepository->create($data);
        return $attachment;
    }


    public function findOneByClientAndHash(Client $client, string $hash): ?Attachment
    {
        return $this->attachmentRepository->findOneByClientAndHash($client, $hash);
    }


    public function findOneByClientAndHashAndName(Client $client, string $hash, string $name): ?Attachment
    {
        return $this->attachmentRepository->findOneByClientAndHashAndName($client, $hash, $name);
    }


    public function findOneByClientIdAndHashAndName(int $clientId, string $hash, string $name): ?Attachment
    {
        return $this->attachmentRepository->findOneByClientIdAndHashAndName($clientId, $hash, $name);
    }


    public function findByClientAndHashes(Client $client, Collection $hashes): Collection
    {
        return $this->attachmentRepository->findByClientAndHashes($client, $hashes);
    }

}
