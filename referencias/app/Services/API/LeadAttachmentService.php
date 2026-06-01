<?php

namespace App\Services\API;

use Throwable;
use App\Models\Lead;
use App\Models\Client;
use App\Models\LeadAttachment;
use Illuminate\Support\Facades\DB;
use App\Helpers\LeadAttachmentHelper;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Collection;
use App\Repositories\LeadAttachmentRepository;
use Symfony\Component\HttpFoundation\File\File;


class LeadAttachmentService
{

    private $leadAttachmentHelper;
    private $leadAttachmentRepository;


    public function __construct(
        LeadAttachmentRepository $leadAttachmentRepository,
        LeadAttachmentHelper $leadAttachmentHelper
    ) {
        $this->leadAttachmentHelper = $leadAttachmentHelper;
        $this->leadAttachmentRepository = $leadAttachmentRepository;
    }


    public function findOrSaveByFile(Lead $lead, File $file): LeadAttachment
    {
        $existentLeadAttachment = $this->findOneByClientAndFile($lead->client, $file);
        if ($existentLeadAttachment) {
            $existentLeadAttachmentArr = $existentLeadAttachment->toArray();
            $dataToSave = $this->buildDataToSave($lead, $file, $existentLeadAttachmentArr);
            $leadAttachment = $this->save($dataToSave);
            return $leadAttachment;
        }

        try {
            DB::beginTransaction();

            $uploadResponse = $this->leadAttachmentHelper->uploadFile($lead->client, $file);
            $leadAttachmentArr = [
                'hash' => $uploadResponse['hash'],
                'bucket_name' => $uploadResponse['bucketName'],
                'bucket_filepath' => $uploadResponse['bucketFilepath'],
            ];
            $dataToSave = $this->buildDataToSave($lead, $file, $leadAttachmentArr);
            $leadAttachment = $this->save($dataToSave);
            
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $leadAttachment;
    }


    public function findByLead(Lead $lead): Collection
    {
        $leadAttachments = $this->leadAttachmentRepository->findAllByLead($lead);
        return $leadAttachments;
    }


    public function findOneByClientAndFile(Client $client, File $file): ?LeadAttachment
    {
        $opts['withTrashed'] = true;
        $hash = $this->leadAttachmentHelper->getHashFromFile($file);
        $leadAttachment = $this->leadAttachmentRepository->findOneByClientAndFileHash($client, $hash, $opts);
        return $leadAttachment;
    }


    public function findOneByLeadAndFile(Lead $lead, File $file): ?LeadAttachment
    {
        $hash = $this->leadAttachmentHelper->getHashFromFile($file);
        $leadAttachment = $this->leadAttachmentRepository->findOneByLeadAndFileHash($lead, $hash);
        return $leadAttachment;
    }


    public function findOneByLeadAndFileName(Lead $lead, string $fileName): ?LeadAttachment
    {
        $leadAttachment = $this->leadAttachmentRepository->findOneByLeadAndFileName($lead, $fileName);
        return $leadAttachment;
    }


    public function delete(LeadAttachment $leadAttachment): LeadAttachment
    {
        return $this->leadAttachmentRepository->delete($leadAttachment);
    }


    private function buildDataToSave(Lead $lead, File $file, array $data)
    {
        $dataToSave = [
            'lead_id' => $lead->id,
            'hash' => $data['hash'],
            'size' => $file->getSize(),
            'client_id' => $lead->client->id,
            'bucket_name' => $data['bucket_name'],
            'extension' => $file->extension(),
            'bucket_filepath' => $data['bucket_filepath'],
            'original_filename' => $file->getClientOriginalName(),
        ];

        return $dataToSave;
    }


    private function save(array $leadAttachmentData): LeadAttachment
    {
        return $leadAttachment = $this->leadAttachmentRepository->save($leadAttachmentData);
    }

}
