<?php

namespace App\Services\API\Actions;

use Exception;
use Throwable;
use App\Models\Lead;
use App\Models\Client;
use App\Models\Status;
use App\Services\API\TagService;
use App\Services\API\UserService;
use App\Services\API\LeadService;
use App\Services\API\NoteService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\API\StatusService;
use App\Services\Traits\GetUserFromRequest;
use App\Services\API\LeadCustomFieldService;
use App\Helpers\SpreadSheetLeadImportHelper;
use App\Services\Traits\GetClientFromRequest;
use App\Services\API\AcquisitionChannelService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Exceptions\Services\LeadService\ExistentLeadException;
use App\DTO\Import\SpreadSheetLeadImport\SpreadSheetImportLeadDTO;
use App\Exceptions\Services\LeadsBulkUploadService\ExceededRowsException;


class LeadsBulkUploadService
{

    use GetClientFromRequest, GetUserFromRequest;

    private $tagService;
    private $userService;
    private $leadService;
    private $statusService;
    private $leadCustomFieldService;
    private $acquisitionChannelService;
    private $spreadSheetLeadImportHelper;


    public function __construct(
        SpreadSheetLeadImportHelper $spreadSheetLeadImportHelper,
        UserService $userService,
        LeadService $leadService,
        TagService $tagService,
        StatusService $statusService,
        AcquisitionChannelService $acquisitionChannelService,
        LeadCustomFieldService $leadCustomFieldService
    ) {
        $this->tagService = $tagService;
        $this->userService = $userService;
        $this->leadService = $leadService;
        $this->statusService = $statusService;
        $this->leadCustomFieldService = $leadCustomFieldService;
        $this->acquisitionChannelService = $acquisitionChannelService;
        $this->spreadSheetLeadImportHelper = $spreadSheetLeadImportHelper;
    }


    public function getLeadsPreviewList(UploadedFile $file): Collection
    {
        $dtoCollection = $this->spreadSheetLeadImportHelper->parseFile($file);
        $isSuperUser = $this->loggedUserIsSuperUser();
        if ($isSuperUser && $dtoCollection->count() > 1500) {
            throw new ExceededRowsException('exceeded_rows', 413);
        }
        if (!$isSuperUser && $dtoCollection->count() > 500) {
            throw new ExceededRowsException('exceeded_rows', 413);
        }

        foreach ($dtoCollection as $dto) {
            $dto = $this->fillPreviewDTOUser($dto);
            $dto = $this->fillPreviewDTOTags($dto);
            $dto = $this->fillPreviewDTOStatus($dto);
            $dto = $this->fillPreviewDTOChannel($dto);
            $dto = $this->fillPreviewDTOLeadContacts($dto);
            $dto = $this->fillPreviewDTOPersistStatus($dto);
            $dto = $this->fillPreviewDTOLeadCustomFields($dto);
        }
        $dtoCollection = $dtoCollection->sortBy('isPersistable');
        return $dtoCollection;
    }


    public function uploadLeads(Collection $bulkUploadLeadsDataDTO): Collection
    {
        $existentLeads = new Collection();
        $importedLeads = new Collection();
        
        try {
            // DB::beginTransaction();
            
            foreach ($bulkUploadLeadsDataDTO as $bulkUploadLeadDataDTO) {
                try {
                    $lead = $this->leadService->createBulkManual($bulkUploadLeadDataDTO);
                    $importedLeads->push($lead);
                } catch (ExistentLeadException $e) {
                    $existentLeads->push($e->getLead());
                }
            }

            // DB::commit();
        } catch (Throwable $e) {
            // DB::rollBack();
            throw $e;
        }

        return collect([
            'importedLeadIds' => $importedLeads->pluck('id'),
            'existentLeadIds' => $existentLeads->pluck('id'),
        ]);
    }


    private function fillPreviewDTOPersistStatus(SpreadSheetImportLeadDTO $dto): SpreadSheetImportLeadDTO
    {
        $dto->fixMainContactEmailsAndPhones();

        if ($dto->mainContactIsEmpty()) {
            $dto->isPersistable = false;
            $dto->addNonPersistibleReason(SpreadSheetImportLeadDTO::EMPTY_MAIN_CONTACT);
        } else if ($this->isLeadDuplicated($dto)) {
            $dto->isPersistable = false;
            $dto->addNonPersistibleReason(SpreadSheetImportLeadDTO::DUPLICATED);
        }

        if (!$dto->statusWasFound) {
            $dto->isPersistable = false;
            $dto->addNonPersistibleReason(SpreadSheetImportLeadDTO::NON_EXISTENT_STATUS);
        }
        if ($dto->userName && !$dto->user) {
            $dto->isPersistable = false;
            $dto->addNonPersistibleReason(SpreadSheetImportLeadDTO::NON_EXISTENT_USER);
        }
        return $dto;
    }


    private function fillPreviewDTOStatus(SpreadSheetImportLeadDTO $dto): SpreadSheetImportLeadDTO
    {
        $status = null;
        if ($dto->statusName) {
            $status = $this->statusService->findOneByClientAndName($this->getClient(), $dto->statusName);
        }
        $dto->statusWasFound = $status ? true : false;
        $dto->status = $status ?? null;
        return $dto;
    }


    private function fillPreviewDTOChannel(SpreadSheetImportLeadDTO $dto): SpreadSheetImportLeadDTO
    {
        $channel = null;
        if ($dto->acquisitionChannelName) {
            $channel = $this->acquisitionChannelService->findOneByClientAndName(
                $this->getClient(), $dto->acquisitionChannelName
            );
        }
        $dto->channelWasFound = $channel ? true : false;
        $dto->acquisitionChannel = $channel;
        return $dto;
    }


    private function fillPreviewDTOLeadContacts(SpreadSheetImportLeadDTO $dto): SpreadSheetImportLeadDTO
    {
        foreach ($dto->contacts as $leadContactDto) {
            if (strlen($leadContactDto->name) > 99) {
                $dto->leadContactNameIsTooLong = true;
                $leadContactDto->leadContactNameIsTooLong = true;
                $dto->longLeadContactNames[] = $leadContactDto->name;
            }
            if (strlen($leadContactDto->lastName) > 99) {
                $dto->leadContactLastNameIsTooLong = true;
                $leadContactDto->leadContactLastNameIsTooLong = true;
                $dto->longLeadContactLastNames[] = $leadContactDto->lastName;
            }
        }
        return $dto;
    }


    private function fillPreviewDTOTags(SpreadSheetImportLeadDTO $dto): SpreadSheetImportLeadDTO
    {
        if (!$dto->tagNames) {
            return $dto;
        }
        foreach ($dto->tagNames as $tagName) {
            $tag = $this->tagService->findOneByClientAndName($this->getClient(), $tagName);
            if ($tag) {
                $dto->tags[] = $tag;
            } else {
                $dto->notFoundTagNames[] = $tagName;
            }
            if (strlen($tagName) > 59) {
                $dto->tagNameIsTooLong = true;
            }
        }
        return $dto;
    }


    private function fillPreviewDTOLeadCustomFields(SpreadSheetImportLeadDTO $dto): SpreadSheetImportLeadDTO
    {
        if (!$dto->customFields) {
            return $dto;
        }
        $leadCustomFields = $this->leadCustomFieldService->findAllByClient($this->getClient());

        foreach ($dto->customFields as $i => $customField) {
            $leadCustomFieldName = trim(strtolower($customField['name']));
            $leadCustomField = $leadCustomFields->first(function ($leadCustomField) use ($leadCustomFieldName) {
                return strtolower($leadCustomField['name']) === $leadCustomFieldName;
            });
            if ($leadCustomField) {
                $dto->customFields[$i]['found'] = true;
                $dto->customFields[$i]['id'] = $leadCustomField->id;
                $dto->customFields[$i]['type'] = $leadCustomField->type;
                $dto->customFields[$i]['lead_custom_field_id'] = $leadCustomField->id;
                $dto->customFields[$i]['type_values'] = $leadCustomField->type_values;
                $dto->customFields[$i]['default_values'] = $leadCustomField->default_values;
            } else {
                $dto->customFields[$i]['found'] = false; // Redundante para más robustez: ya es false por default
            }
        }
        return $dto;
    }


    private function fillPreviewDTOUser(SpreadSheetImportLeadDTO $dto): SpreadSheetImportLeadDTO
    {
        if (!$dto->userName) {
            return $dto;
        }
        $user = $this->userService->findByEmailOrUsername($dto->userName);
        if ($user) {
            $dto->user = $user;
        }
        return $dto;
    }


    private function isLeadDuplicated($dto): bool
    {
        $leadAttrs = $dto->getLeadAttrs();
        $mainLeadContactAttrs = $dto->getMainContactAttrs();
        $hash = Lead::buildHash($leadAttrs, $mainLeadContactAttrs);

        $lead = $this->leadService->findOneByClientAndHash($this->getClient(), $hash);
        return $lead ? true : false;
    }

}
