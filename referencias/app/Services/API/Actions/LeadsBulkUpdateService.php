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
use App\Repositories\LeadRepository;
use App\Services\API\LeadContactService;
use App\Services\Traits\GetUserFromRequest;
use App\Services\API\LeadCustomFieldService;
use App\Helpers\SpreadSheetLeadUpdateHelper;
use App\Services\API\LeadContactPhoneService;
use App\Services\API\LeadContactEmailService;
use App\Services\Traits\GetClientFromRequest;
use App\Services\API\AcquisitionChannelService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Exceptions\Services\LeadService\UpdateLeadException;
use App\Exceptions\Services\LeadService\ExistentLeadException;
use App\DTO\Import\SpreadSheetLeadUpdate\SpreadSheetUpdateLeadDTO;
use App\Exceptions\Services\LeadsBulkUploadService\ExceededRowsException;


class LeadsBulkUpdateService
{

    use GetClientFromRequest, GetUserFromRequest;

    private $tagService;
    private $noteService;
    private $userService;
    private $leadService;
    private $statusService;
    private $leadRepository;
    private $leadContactService;
    private $leadCustomFieldService;
    private $leadContactPhoneService;
    private $leadContactEmailService;
    private $acquisitionChannelService;
    private $spreadSheetLeadUpdateHelper;


    public function __construct(
        SpreadSheetLeadUpdateHelper $spreadSheetLeadUpdateHelper,
        UserService $userService,
        LeadService $leadService,
        TagService $tagService,
        StatusService $statusService,
        AcquisitionChannelService $acquisitionChannelService,
        LeadCustomFieldService $leadCustomFieldService,
        LeadRepository $leadRepository,
        LeadContactService $leadContactService,
        NoteService $noteService,
        LeadContactPhoneService $leadContactPhoneService,
        LeadContactEmailService $leadContactEmailService,
    ) {
        $this->tagService = $tagService;
        $this->noteService = $noteService;
        $this->userService = $userService;
        $this->leadService = $leadService;
        $this->statusService = $statusService;
        $this->leadRepository = $leadRepository;
        $this->leadCustomFieldService = $leadCustomFieldService;
        $this->acquisitionChannelService = $acquisitionChannelService;
        $this->spreadSheetLeadUpdateHelper = $spreadSheetLeadUpdateHelper;
        $this->leadContactService = $leadContactService;
        $this->leadContactPhoneService = $leadContactPhoneService;
        $this->leadContactEmailService = $leadContactEmailService;
    }


    public function getLeadsPreviewList(UploadedFile $file): Collection
    {
        $leadDataDTOCollection = $this->spreadSheetLeadUpdateHelper->parseFile($file);

        $isSuperUser = $this->loggedUserIsSuperUser();
        if ($isSuperUser && $leadDataDTOCollection->count() > 150) {
            throw new ExceededRowsException('exceeded_rows', 413);
        }
        if (!$isSuperUser && $leadDataDTOCollection->count() > 150) {
            throw new ExceededRowsException('exceeded_rows', 413);
        }

        $leadIds = $leadDataDTOCollection->pluck('leadId');
        $leads = $this->leadService->findByClientAndIds($this->getClient(), $leadIds);

        $leadIdsDuplicated = $leadDataDTOCollection->pluck('leadId')->duplicates()->toArray();

        foreach ($leadDataDTOCollection as $dto) {
            $leadDB = $leads->find($dto->leadId);

            $dto = $this->fillPreviewDTOUser($dto, $leadDB);
            $dto = $this->fillPreviewDTOChannel($dto, $leadDB);
            $dto = $this->fillPreviewDTOLead($dto, $leadDB);
            $dto = $this->fillPreviewDTOLeadContactNames($dto);
            $dto = $this->fillPreviewDTOLeadContactPhone($dto, $leadDB);
            $dto = $this->fillPreviewDTOLeadContactEmail($dto, $leadDB);
            $dto = $this->fillPreviewDTOLeadMainContactDuplicated($dto);
            $dto = $this->fillPreviewDTOLeadIdIsDuplicated($dto, $leadIdsDuplicated);
            $dto = $this->fillPreviewDTOLeadCustomFields($dto);

            $dto = $this->fillPreviewDTOPersistStatus($dto);
        }

        $leadDataDTOCollection = $leadDataDTOCollection->sortBy('isPersistable');
        return $leadDataDTOCollection;
    }


    // Collection<BulkUpdateLeadDataDTO>
    public function updateLeads(Collection $bulkUpdateLeadsDataDTO): bool
    {
        $notesDataToCreate = new Collection();
        $leadsDataToUpdate = new Collection();
        $leadContactsDataToUpdate = new Collection();
        $leadCustomFieldsDataToUpdate = new Collection();
        $mainLeadContactPhonesDataToCreate = new Collection();
        $mainLeadContactEmailsDataToCreate = new Collection();

        foreach ($bulkUpdateLeadsDataDTO as $bulkUpdateLeadDataDTO) {
            $lead = $bulkUpdateLeadDataDTO->lead;

            $leadAttrsToUpdate = $bulkUpdateLeadDataDTO->getLeadAttrsToUpdate();
            if ($leadAttrsToUpdate) {
                $leadsDataToUpdate->push(['attrs' => $leadAttrsToUpdate, 'lead' => $lead]);
            }

            $mainLeadContactAttrsToUpdate = $bulkUpdateLeadDataDTO->getMainLeadContactAttrsToUpdate();
            if ($mainLeadContactAttrsToUpdate) {
                $leadContactsDataToUpdate->push(['attrs' => $mainLeadContactAttrsToUpdate, 'lead' => $lead]);
            }

            $mainLeadContactEmailToCreate = $bulkUpdateLeadDataDTO->getMainLeadContactEmailValueToCreate();
            if ($mainLeadContactEmailToCreate) {
                $mainLeadContactEmailsDataToCreate->push(['email' => $mainLeadContactEmailToCreate, 'lead' => $lead]);
            }

            $mainLeadContactPhoneToCreate = $bulkUpdateLeadDataDTO->getMainLeadContactPhoneValueToCreate();
            if ($mainLeadContactPhoneToCreate) {
                $mainLeadContactPhonesDataToCreate->push(['phone' => $mainLeadContactPhoneToCreate, 'lead' => $lead]);
            }

            $noteToCreate = $bulkUpdateLeadDataDTO->notes;
            if ($noteToCreate) {
                $notesDataToCreate->push(['notes' => $noteToCreate, 'lead' => $lead]);
            }

            $customFieldsDTOsToUpdate = $bulkUpdateLeadDataDTO->getCustomFieldDTOsToUpdate();
            foreach ($customFieldsDTOsToUpdate as $customFieldDTO) {
                $leadCustomFieldsDataToUpdate->push(['customFieldDTO' => $customFieldDTO, 'lead' => $lead]);
            }
        }

        try {
            DB::beginTransaction();

            foreach ($leadsDataToUpdate as $dataToUpdate) {
                $this->leadService->update($dataToUpdate['lead'], $dataToUpdate['attrs']);
            }

            foreach ($leadContactsDataToUpdate as $dataToUpdate) {
                $mainLeadContact = $dataToUpdate['lead']->mainLeadContact;
                $this->leadContactService->update($mainLeadContact, $dataToUpdate['attrs']);
            }

            foreach ($mainLeadContactEmailsDataToCreate as $dataToCreate) {
                $mainLeadContact = $dataToCreate['lead']->mainLeadContact;
                $this->leadContactEmailService->create($mainLeadContact, ['email' => $dataToCreate['email']]);
            }

            foreach ($mainLeadContactPhonesDataToCreate as $dataToCreate) {
                $mainLeadContact = $dataToCreate['lead']->mainLeadContact;
                $this->leadContactPhoneService->create($mainLeadContact, ['phone' => $dataToCreate['phone']]);
            }

            foreach ($notesDataToCreate as $dataToCreate) {
                $noteAttrs = [
                    'text' => $dataToCreate['notes'],
                    'user_id' => $this->getUser()->id,
                    'client_id' => $this->getClient()->id,
                ];
                $this->noteService->create($dataToCreate['lead'], $noteAttrs);
            }

            foreach ($leadCustomFieldsDataToUpdate as $dataToUpdate) {
                $customFieldDTO = $dataToUpdate['customFieldDTO'];
                $this->leadCustomFieldService->setValue(
                    $dataToUpdate['lead'], $customFieldDTO->leadCustomField, $customFieldDTO->value,
                );
            }
            
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
        
        return true;
    }


    private function fillPreviewDTOUser(SpreadSheetUpdateLeadDTO $dto, ?Lead $leadDB): SpreadSheetUpdateLeadDTO
    {
        if (!$leadDB) {
            $dto->user = null;
            $dto->userWasFound = false;
            return $dto;
        }

        if (!$dto->userName) {
            $dto->user = $leadDB->user()->first();
            $dto->userWasFound = true;
            return $dto;
        }

        $leadDBUser = $leadDB->user()->first();
        $leadDBUserName = $leadDBUser->username;

        if ($leadDBUserName == $dto->userName) {
            $dto->user = $leadDBUser;
            $dto->userWasFound = true;
            return $dto;
        } else {
            $userDB = $this->userService->findByEmailOrUsername($dto->userName);
            if ($userDB) {
                $dto->user = $userDB;
                $dto->userWasFound = true;
            } else {
                $dto->user = $leadDBUser;
                $dto->userWasFound = false;
            }

            return $dto;
        }

        return $dto;
    }


    private function fillPreviewDTOChannel(SpreadSheetUpdateLeadDTO $dto, ?Lead $leadDB): SpreadSheetUpdateLeadDTO
    {
        if (!$leadDB) {
            $dto->acquisitionChannel = null;
            $dto->channelWasFound = false;
            return $dto;
        }
        
        if (!$dto->acquisitionChannelName) {
            $dto->channelWasFound = true;
            $dto->acquisitionChannel = $leadDB->acquisitionChannel;
            return $dto;
        }

        $leadDBChannel = $leadDB->acquisitionChannel;
        $leadDBChannelName = $leadDBChannel?->name;

        if ($leadDBChannelName == $dto->acquisitionChannelName) {
            $dto->acquisitionChannel = $leadDBChannel;
            $dto->channelWasFound = true;
            return $dto;
        } else {
            $channelDB = $this->acquisitionChannelService->findOneByClientAndName(
                $this->getClient(), $dto->acquisitionChannelName
            );
            if ($channelDB) {
                $dto->acquisitionChannel = $channelDB;
                $dto->channelWasFound = true;
            } else {
                $dto->acquisitionChannel = $leadDBChannel;
                $dto->channelWasFound = false;
            }

            return $dto;
        }
    }


    private function fillPreviewDTOLead(SpreadSheetUpdateLeadDTO $dto, ?Lead $leadDB): SpreadSheetUpdateLeadDTO
    {
        if (!$dto->leadId) {
            return $dto;
        }

        $dto->lead = $leadDB;
        $dto->leadWasFound = !is_null($leadDB);
        return $dto;
    }


    private function fillPreviewDTOLeadMainContactDuplicated($dto): SpreadSheetUpdateLeadDTO
    {
        if ($dto->isEmptyMainContactAttrs()) {
            return $dto;
        }

        $leadAttrs = $dto->getLeadAttrs();
        $mainLeadContactAttrs = $dto->getMainContactAttrs();

        $hash = Lead::buildHash($leadAttrs, $mainLeadContactAttrs);
        $leadDB = $this->leadService->findOneByClientAndHash($this->getClient(), $hash);
        
        if ($leadDB && $leadDB->id != $dto->leadId) {
            $dto->leadMainContactIsDuplicated = true;
        }

        return $dto;
    }


    private function fillPreviewDTOLeadContactNames(SpreadSheetUpdateLeadDTO $dto): SpreadSheetUpdateLeadDTO
    {
        foreach ($dto->contacts as $leadContactDto) {
            if (strlen($leadContactDto->name) > 99) {
                $dto->leadContactNameIsTooLong = true;
                $leadContactDto->leadContactNameIsTooLong = true;
            }
            if (strlen($leadContactDto->lastName) > 99) {
                $dto->leadContactLastNameIsTooLong = true;
                $leadContactDto->leadContactLastNameIsTooLong = true;
            }
        }

        return $dto;
    }


    private function fillPreviewDTOLeadContactPhone(
        SpreadSheetUpdateLeadDTO $dto,
        ?Lead $leadDB
    ): SpreadSheetUpdateLeadDTO {
        if (!$dto->leadId) {
            return $dto;
        }

        if (!$leadDB || !$leadDB->mainPhone) {
            return $dto;
        }

        $leadContactPhones = $leadDB->leadContactPhones->pluck('phone');
        $dto->phones = $leadContactPhones->toArray();

        foreach ($dto->contacts as $leadContactDto) {
            if ($leadContactDto->phone) {
                if ($leadContactPhones->contains($leadContactDto->phone)) {
                    $dto->contacts->first()->leadContactPhoneAlreadyExists = true;
                }
            }
        }
        
        
        return $dto;
    }


    private function fillPreviewDTOLeadContactEmail(
        SpreadSheetUpdateLeadDTO $dto,
        ?Lead $leadDB
    ): SpreadSheetUpdateLeadDTO {
        if (!$dto->leadId) {
            return $dto;
        }

        if (!$leadDB || !$leadDB->mainEmail) {
            return $dto;
        }

        $leadContactEmails = $leadDB->leadContactEmails->pluck('email');
        $dto->emails = $leadContactEmails->toArray();

        foreach ($dto->contacts as $leadContactDto) {
            if ($leadContactDto->email) {
                if ($leadContactEmails->contains($leadContactDto->email)) {
                    $dto->contacts->first()->leadContactEmailAlreadyExists = true;
                }
            }
        }
        
        return $dto;
    }


    private function fillPreviewDTOLeadIdIsDuplicated(
        SpreadSheetUpdateLeadDTO $dto,
        array $leadIdsDuplicated
    ): SpreadSheetUpdateLeadDTO {
        if (!$dto->leadId) {
            return $dto;
        }

        if (in_array($dto->leadId, $leadIdsDuplicated)) {
            $dto->isLeadIdDuplicated = true;
        }

        return $dto;
    }


    private function fillPreviewDTOPersistStatus(SpreadSheetUpdateLeadDTO $dto): SpreadSheetUpdateLeadDTO
    {
        if ($dto->leadIdIsEmpty()) {
            $dto->addNonPersistibleReason(SpreadSheetUpdateLeadDTO::LEAD_ID_IS_EMPTY);
            return $dto;
        }
        if (!$dto->leadWasFound) {
            $dto->addNonPersistibleReason(SpreadSheetUpdateLeadDTO::NON_EXISTENT_LEAD);
            return $dto;
        }
        if ($dto->isLeadIdDuplicated) {
            $dto->addNonPersistibleReason(SpreadSheetUpdateLeadDTO::LEAD_ID_IS_DUPLICATED);
            return $dto;
        }
        if ($dto->leadMainContactIsDuplicated) {
            $dto->addNonPersistibleReason(SpreadSheetUpdateLeadDTO::LEAD_MAIN_CONTACT_ALREADY_EXISTS);
            return $dto;
        }

        if ($dto->contacts->first() && $dto->contacts->first()->leadContactEmailAlreadyExists) {
            $dto->addWarningReason(SpreadSheetUpdateLeadDTO::LEAD_CONTACT_EMAIL_ALREADY_EXISTS);
        }
        if ($dto->contacts->first() && $dto->contacts->first()->leadContactPhoneAlreadyExists) {
            $dto->addWarningReason(SpreadSheetUpdateLeadDTO::LEAD_CONTACT_PHONE_ALREADY_EXISTS);
        }
        if (!$dto->userWasFound) {
            $dto->addWarningReason(SpreadSheetUpdateLeadDTO::NON_EXISTENT_USER);
        }
        if (!$dto->channelWasFound) {
            $dto->addWarningReason(SpreadSheetUpdateLeadDTO::NON_EXISTENT_CHANNEL);
        }
        if ($dto->contacts->first() && $dto->contacts->first()->leadContactNameIsTooLong) {
            $dto->addWarningReason(SpreadSheetUpdateLeadDTO::LEAD_CONTACT_NAME_IS_TO_LONG);
        }
        if ($dto->contacts->first() && $dto->contacts->first()->leadContactLastNameIsTooLong) {
            $dto->addWarningReason(SpreadSheetUpdateLeadDTO::LEAD_CONTACT_LAST_NAME_IS_TO_LONG);
        }
        if ($dto->contacts->first() && $dto->contacts->first()->invalidEmail) {
            $dto->addWarningReason(SpreadSheetUpdateLeadDTO::LEAD_CONTACT_INVALID_EMAIL);
        }
        if ($dto->contacts->first() && $dto->contacts->first()->invalidPhone) {
            $dto->addWarningReason(SpreadSheetUpdateLeadDTO::LEAD_CONTACT_INVALID_PHONE);
        }
        if ($dto->customFieldsAreNotEnabled) {
            $dto->addWarningReason(SpreadSheetUpdateLeadDTO::CUSTOM_FIELDS_ARE_NOT_ENABLED);
        }
        if (!$dto->customFieldsAreNotEnabled && collect($dto->customFields)->isNotEmpty()) {
            $nonExistentCustomField = collect($dto->customFields)->contains(function ($customField) {
                return !$customField['found'];
            });
            if ($nonExistentCustomField) {
                $dto->addWarningReason(SpreadSheetUpdateLeadDTO::NON_EXISTENT_CUSTOM_FIELD);
            }
        }
        return $dto;
    }


    private function fillPreviewDTOLeadCustomFields(SpreadSheetUpdateLeadDTO $dto): SpreadSheetUpdateLeadDTO
    {
        if (!$dto->customFields) {
            return $dto;
        }

        $enableLeadsCustomFields = $this->getClient()->clientSettings->enable_leads_custom_fields;
        if (!$enableLeadsCustomFields) {
            $dto->customFieldsAreNotEnabled = true;
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

}
