<?php

namespace App\Http\Controllers\API\Actions;

use App\Models\Lead;
use App\Models\User;
use App\Models\Status;
use App\Services\API\TaskService;
use App\Services\API\NoteService;
use App\Models\AcquisitionChannel;
use App\Http\Resources\UserResource;
use App\Http\Resources\StatusResource;
use App\Http\Resources\TagResourceCollection;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Actions\DeleteLeadsRequest;
use App\Services\API\GoogleAPIUserContactService;
use App\Http\Resources\AcquisitionChannelResource;
use App\Http\Requests\Actions\SetLeadsUserRequest;
use App\Http\Requests\Actions\SetLeadsTagsRequest;
use App\Http\Resources\GoogleAPIUserContactResource;
use App\Http\Requests\Actions\SetLeadsStatusRequest;
use App\Http\Requests\Actions\AssignLeadTagsRequest;
use App\Http\Requests\Actions\ChangeLeadUserRequest;
use App\Http\Requests\Actions\ChangeLeadStatusRequest;
use App\Http\Requests\Actions\ChangeLeadsStatusRequest;
use App\Http\Requests\Actions\CreateLeadsMassiveTaskRequest;
use App\Http\Requests\Actions\CreateLeadsMassiveNoteRequest;
use App\Services\API\Actions\LeadService as ActionsLeadService;
use App\Http\Requests\Actions\SetLeadsAcquisitionChannelRequest;
use App\Http\Requests\Actions\SyncLeadWithGoogleContactsRequest;
use App\Http\Requests\Actions\UnsyncLeadWithGoogleContactsRequest;
use App\Http\Requests\Actions\ChangeLeadAcquisitionChannelRequest;
use App\Http\Requests\Actions\ChangeLeadsAcquisitionChannelRequest;
use App\Http\Requests\Actions\SyncMassiveWithGoogleContactsRequest;
use App\Services\API\Dispatchers\GoogleContactsEventsDispatcherService;


class LeadController extends BaseAPIController
{

    public function changeStatus(Lead $lead, Status $status, ChangeLeadStatusRequest $request)
    {
        $newStatus = resolve(ActionsLeadService::class)->changeStatus($lead, $status);
        $resource = (new StatusResource($newStatus))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function changeAcquisitionChannel(
        Lead $lead,
        AcquisitionChannel $acquisitionChannel,
        ChangeLeadAcquisitionChannelRequest $request
    ) {
        $lead = resolve(ActionsLeadService::class)->changeAcquisitionChannel($lead, $acquisitionChannel);
        $resource = (new AcquisitionChannelResource($lead))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function changeUser(Lead $lead, User $user, ChangeLeadUserRequest $request)
    {
        $newUser = resolve(ActionsLeadService::class)->changeUserAndDispatchPostEvents($lead, $user);
        $resource = (new UserResource($newUser))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function setMassiveLeadsUser(User $newUser, SetLeadsUserRequest $req)
    {
        set_time_limit(300);
        ini_set('max_execution_time', 300);
        resolve(ActionsLeadService::class)->setMassiveLeadsUser($req->getLeadIds(), $newUser);
        return $this->getSuccessResponse(['lead_id' => $req->getLeadIds()]);
    }


    public function deleteMassiveLeads(DeleteLeadsRequest $req)
    {
        set_time_limit(300);
        ini_set('max_execution_time', 300);
        resolve(ActionsLeadService::class)->deleteMassiveLeads($req->getLeads());
        return $this->getSuccessResponse(['lead_id' => $req->getLeadIds()]);
    }


    public function assignTags(Lead $lead, AssignLeadTagsRequest $request)
    {
        $tags = resolve(ActionsLeadService::class)->assignTags($lead, $request->getTags());
        $resource = (new TagResourceCollection($tags))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function changeMassiveLeadsStatus(
        Status $originalStatus,
        Status $newStatus,
        ChangeLeadsStatusRequest $request
    ) {
        $changedLeadIds = resolve(ActionsLeadService::class)->changeMassiveLeadsStatus($originalStatus, $newStatus);
        return $this->getSuccessResponse(['lead_id' => $changedLeadIds]);
    }


    public function setMassiveLeadsStatus(Status $newStatus, SetLeadsStatusRequest $request)
    {
        $params = $request->validatedAttributes();
        $changedLeadIds = resolve(ActionsLeadService::class)->setMassiveLeadsStatus($params['leads'], $newStatus);
        return $this->getSuccessResponse(['lead_id' => $changedLeadIds]);
    }


    public function setMassiveLeadsAquisitionChannel(
        AcquisitionChannel $newAquisitionChannel,
        SetLeadsAcquisitionChannelRequest $req
    ) {
        $changedLeadIds = resolve(ActionsLeadService::class)->setMassiveLeadsAcquisitionChannel(
            $req->getLeads(), $newAquisitionChannel
        );
        return $this->getSuccessResponse(['lead_id' => $changedLeadIds]);
    }


    public function createLeadsMassiveNote(CreateLeadsMassiveNoteRequest $req)
    {
        $validated = $req->validated();
        $inserted = resolve(NoteService::class)->createMassive($validated['leads'], $validated['text']);
        return $this->getSuccessResponse($inserted);
    }


    public function syncMassiveWithGoogleContacts(SyncMassiveWithGoogleContactsRequest $req)
    {
        resolve(GoogleContactsEventsDispatcherService::class)->dispatchSyncMultipleLeadsJobs(
            $req->client, $req->user, $req->getLeadIds()
        );
        return $this->getSuccessResponse(true);
    }


    public function syncWithGoogleContacts(Lead $lead, SyncLeadWithGoogleContactsRequest $req)
    {
        $validated = $req->validated();
        $googleAPIUserContact = resolve(GoogleAPIUserContactService::class)->syncLead($req->lead, $req->user);
        $rs = (new GoogleAPIUserContactResource($googleAPIUserContact))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($rs);
    }


    public function unsyncFromGoogleContacts(Lead $lead, UnsyncLeadWithGoogleContactsRequest $req)
    {
        $validated = $req->validated();
        $googleAPIUserContact = resolve(GoogleAPIUserContactService::class)->unsyncLead($req->lead, $req->user);
        $rs = (new GoogleAPIUserContactResource($googleAPIUserContact))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($rs);
    }


    public function createLeadsMassiveTask(CreateLeadsMassiveTaskRequest $req)
    {
        $validated = $req->validated();
        $tasks = resolve(TaskService::class)->createMassive($req->getLeads(), $req->getTaskData());
        return $this->getSuccessResponse(true);
    }


    public function editMassiveLeadsTags(SetLeadsTagsRequest $request)
    {
        $params = $request->validatedAttributes();
        $changedLeadIds = resolve(ActionsLeadService::class)->editMassiveLeadsTags(
            $params['leads'], $params['tags'], ['assignType' => $params['type']]
        );
        return $this->getSuccessResponse(['lead_id' => $changedLeadIds]);
    }


    public function changeMassiveLeadsAcquisitionChannel(
        AcquisitionChannel $originalChannel,
        AcquisitionChannel $newChannel,
        ChangeLeadsAcquisitionChannelRequest $request
    ) {
        $changedLeadIds = resolve(ActionsLeadService::class)->changeMassiveLeadsAcquisitionChannel(
            $originalChannel, $newChannel
        );
        return $this->getSuccessResponse(['lead_id' => $changedLeadIds]);
    }

}
