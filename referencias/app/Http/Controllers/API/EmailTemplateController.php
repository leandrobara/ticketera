<?php

namespace App\Http\Controllers\API;

use App\Models\EmailTemplate;
use App\Services\API\EmailTemplateService;
use App\Http\Resources\EmailTemplateResource;
use App\Http\Requests\GetEmailTemplateRequest;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\ListEmailTemplateRequest;
use App\Http\Requests\CreateEmailTemplateRequest;
use App\Http\Requests\UpdateEmailTemplateRequest;
use App\Http\Requests\DeleteEmailTemplateRequest;
use App\Http\Resources\EmailTemplateResourceCollection;


class EmailTemplateController extends BaseAPIController
{

    public function list(ListEmailTemplateRequest $request)
    {
        $tpls = resolve(EmailTemplateService::class)->list($request->validated());
        $resource = (new EmailTemplateResourceCollection($tpls))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function getOne(EmailTemplate $emailTemplate, GetEmailTemplateRequest $request)
    {
        return $this->getSuccessResponse((new EmailTemplateResource($emailTemplate))->loadOptionsFromRequest($request));
    }


    public function create(CreateEmailTemplateRequest $request)
    {
        $emailTemplate = resolve(EmailTemplateService::class)->create($request->validatedAttributes());
        return $this->getSuccessResponse((new EmailTemplateResource($emailTemplate))->loadOptionsFromRequest($request));
    }


    public function update(EmailTemplate $emailTemplate, UpdateEmailTemplateRequest $request)
    {
        $emailTemplate = resolve(EmailTemplateService::class)->update($emailTemplate, $request->validatedAttributes());
        return $this->getSuccessResponse((new EmailTemplateResource($emailTemplate))->loadOptionsFromRequest($request));
    }


    public function delete(EmailTemplate $emailTemplate, DeleteEmailTemplateRequest $request)
    {
        $emailTemplate = resolve(EmailTemplateService::class)->delete($emailTemplate);
        return $this->getSuccessResponse((new EmailTemplateResource($emailTemplate))->loadOptionsFromRequest($request));
    }

}
