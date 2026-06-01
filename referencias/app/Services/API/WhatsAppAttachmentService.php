<?php

namespace App\Services\API;

use Throwable;
use App\Models\User;
use App\Models\Client;
use App\Models\WhatsAppAttachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\WhatsAppMetaAPIConnection;
use App\Helpers\WhatsAppAttachmentHelper;
use Illuminate\Database\Eloquent\Collection;
use App\Services\Traits\GetClientFromRequest;
use Symfony\Component\HttpFoundation\File\File;
use App\Repositories\WhatsAppAttachmentRepository;
use App\Helpers\WhatsAppMetaAPI\WhatsAppMetaAPIHelper;
use App\Repositories\Cache\WhatsAppAttachmentRepositoryCache;


class WhatsAppAttachmentService
{

    use GetClientFromRequest;


    public function __construct(
        protected WhatsAppMetaAPIHelper $whatsAppMetaAPIHelper,
        protected WhatsAppAttachmentHelper $whatsAppAttachmentHelper,
        protected WhatsAppAttachmentRepository | WhatsAppAttachmentRepositoryCache $whatsAppAttachmentRepository,
    ) {
    }


    public function findOrFail(int $id): WhatsAppAttachment
    {
        return $this->whatsAppAttachmentRepository->findOrFail($id);
    }


    public function findOrSaveByFile(File $file): WhatsAppAttachment
    {
        $client = $this->getClient();
        $wapAttachment = $this->findExactOneByClientAndFile($client, $file, ['withTrashed' => true]);
        if ($wapAttachment && !$wapAttachment->deleted_at) {
            return $wapAttachment;
        }
        if (!$wapAttachment) {
            $wapAttachment = $this->findOneByClientAndFileHash($client, $file, ['withTrashed' => true]);
        }
        if ($wapAttachment) {
            $dataToSave = $this->buildDataToSave($file, $wapAttachment->toArray());
            $newWapAttachment = $this->create($dataToSave);
            return $newWapAttachment;
        }

        try {
            DB::beginTransaction();
            $uploadResponse = $this->whatsAppAttachmentHelper->uploadFile($this->getClient(), $file);
            $whatsAppAttachmentArr = [
                'hash' => $uploadResponse['hash'],
                'bucket_name' => $uploadResponse['bucketName'],
                'bucket_filepath' => $uploadResponse['bucketFilepath'],
            ];
            $dataToSave = $this->buildDataToSave($file, $whatsAppAttachmentArr);
            $whatsAppAttachment = $this->create($dataToSave);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $whatsAppAttachment;
    }


    public function uploadToWhatsAppMeta(
        WhatsAppMetaAPIConnection $whatsAppMetaAPIConnection,
        WhatsAppAttachment $wapAttachment
    ): ?WhatsAppAttachment {
        $fileSize = $wapAttachment->size;
        $mimeType = $wapAttachment->mime_type;
        $fileName = $wapAttachment->original_filename;
        $phoneNumberId = $whatsAppMetaAPIConnection->phone_number_id;
        $accessToken = $whatsAppMetaAPIConnection->access_token;
        $docType = $this->getWhatsAppMediaTypeByMime($wapAttachment->mime_type);
        $filePath = $this->whatsAppAttachmentHelper->getTemporaryUrl($wapAttachment);
        
        $metaHandleId = $this->whatsAppMetaAPIHelper->uploadMediaResumable(
            filePath: $filePath,
            fileName: $fileName,
            fileSize: $fileSize,
            mimeType: $mimeType,
            accessToken: $accessToken,
        );
        $wapAttachment->meta_handle_id = $metaHandleId;
        
        $wapAttachment->saveOrFail();
        return $wapAttachment->fresh();
    }


    public function findOneByClientAndFileHash(Client $client, File $file, array $opts = []): ?WhatsAppAttachment
    {
        $opts['withTrashed'] = ($opts['withTrashed'] ?? false);
        $hash = $this->whatsAppAttachmentHelper->getHashFromFile($file);
        $wapAttachment = $this->whatsAppAttachmentRepository->findExactOneByClientAndFile($client, $hash, $opts);
        return $wapAttachment;
    }


    public function findExactOneByClientAndFile(Client $client, File $file, array $opts = []): ?WhatsAppAttachment
    {
        $opts['withTrashed'] = ($opts['withTrashed'] ?? false);
        $hash = $this->whatsAppAttachmentHelper->getHashFromFile($file);
        $wapAttachment = $this->whatsAppAttachmentRepository->findOneByClientAndHashAndFilename(
            $client, $hash, $file->getClientOriginalName(), $opts
        );
        return $wapAttachment;
    }


    private function buildDataToSave(File $file, array $data)
    {
        $clientId = $this->getClient()->id;
        $dataToSave = [
            'hash' => $data['hash'],
            'client_id' => $clientId,
            'size' => $file->getSize(),
            'extension' => $file->extension(),
            'bucket_name' => $data['bucket_name'],
            'mime_type' => $file->getClientMimeType(),
            'bucket_filepath' => $data['bucket_filepath'],
            'original_filename' => $file->getClientOriginalName(),
        ];

        return $dataToSave;
    }


    public function delete(WhatsAppAttachment $whatsAppAttachment): WhatsAppAttachment
    {
        return $this->whatsAppAttachmentRepository->delete($whatsAppAttachment);
    }


    protected function create(array $whatsAppAttachmentData): WhatsAppAttachment
    {
        return $this->whatsAppAttachmentRepository->create($whatsAppAttachmentData);
    }


    public function getWhatsAppMediaTypeByMime(string $mimeType): string
    {
        $prefix = strtolower(explode('/', $mimeType)[0]);
        return match ($prefix) {
            'image' => 'image',
            'video' => 'video',
            'audio' => 'audio',
            default => 'document'
        };
    }

}
