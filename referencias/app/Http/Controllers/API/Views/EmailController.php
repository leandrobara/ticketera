<?php

namespace App\Http\Controllers\API\Views;

use App\Models\Email;
use Illuminate\Http\Request;
use App\Exports\MassiveEmailExport;
use App\Exports\MassiveEmailOpeningsExport;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Views\ListSentEmailsRequest;
use App\Http\Requests\Views\MassiveEmailModalRequest;
use App\Http\Requests\Views\ShowSentEmailModalRequest;
use App\Http\Requests\Views\MassiveEmailExportRequest;
use App\Http\Requests\Views\ListMassiveSentEmailRequest;
use App\Services\API\Views\EmailService as ViewsEmailService;
use App\Http\Requests\Views\ShowSentMassiveEmailModalRequest;
use App\Http\Requests\Views\MassiveEmailOpeningsExportRequest;
use App\Http\Resources\Views\SentEmailModal\SentEmailModalResource;
use App\Http\Resources\Views\MassiveEmailModal\MassiveEmailModalResource;
use App\Http\Resources\Views\SentEmailList\SentEmailListResourceCollection;
use App\Http\Resources\Views\MassiveSentEmailList\MassiveSentEmailListResource;
use App\Http\Resources\Views\SentMassiveEmailModal\SentMassiveEmailModalResource;


class EmailController extends BaseAPIController
{

    public function listSentEmails(ListSentEmailsRequest $request)
    {
        $emails = resolve(ViewsEmailService::class)->findSentEmails($request->validated());
        return $this->getSuccessResponse(new SentEmailListResourceCollection($emails));
    }


    public function listMassiveSentEmails(ListMassiveSentEmailRequest $request)
    {
        $massiveSentEmails = resolve(ViewsEmailService::class)->findMassiveSentEmails($request->validated());
        return $this->getSuccessResponse(new MassiveSentEmailListResource($massiveSentEmails));
    }


    public function massiveEmailModal(MassiveEmailModalRequest $req)
    {
        $service = resolve(ViewsEmailService::class);
        $massiveEmailModalDTO = $service->getMassiveEmailModalInfo($req->validatedLeadIds());
        return $this->getSuccessResponse(new MassiveEmailModalResource($massiveEmailModalDTO));
    }


    public function showSentEmailModal(Email $email, ShowSentEmailModalRequest $req)
    {
        $email = resolve(ViewsEmailService::class)->showSentEmailModalInfo($email);
        return $this->getSuccessResponse(new SentEmailModalResource($email));
    }


    public function showSentMassiveEmailModal(string $externalMassiveId, ShowSentMassiveEmailModalRequest $req)
    {
        $email = resolve(ViewsEmailService::class)->fillMassiveEmailWithModalInfo($req->getEmail());
        return $this->getSuccessResponse(new SentMassiveEmailModalResource($email));
    }


    public function exportMassiveEmailInfo(MassiveEmailExportRequest $request)
    {
        $massiveEmailDetails = resolve(ViewsEmailService::class)->listEmailMassiveToExport($request->validated());
        return (new MassiveEmailExport($massiveEmailDetails))->download('reporte-envio-emails-massivo.xlsx');
    }


    public function exportMassiveEmailOpeningsInfo(MassiveEmailOpeningsExportRequest $req)
    {
        $massiveEmailOpenDetails = resolve(ViewsEmailService::class)->listEmailMassiveOpenToExport($req->validated());
        return (
            new MassiveEmailOpeningsExport($massiveEmailOpenDetails->getCollection(), $req->getSubject())
        )->download('reporte-envio-email-masivo-aperturas.xlsx');
    }


    public function getEmailQuotaInfoDTO(Request $req)
    {
        $emailQuotaDTO = resolve(ViewsEmailService::class)->getEmailQuotaInfoDTO($req->client);
        return $this->getSuccessResponse($emailQuotaDTO);
    }

}
