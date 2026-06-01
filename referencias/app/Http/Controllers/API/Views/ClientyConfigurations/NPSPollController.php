<?php

namespace App\Http\Controllers\API\Views\ClientyConfigurations;

use App\Models\NPSPoll;
use Illuminate\Http\Request;
use App\Helpers\SystemHelper;
use App\Services\API\NPSPollService;
use App\Exports\NPSPollAnswersExport;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Resources\NPSPollsResourceCollection;
use App\Http\Resources\Views\NPSPollModal\ClientyConfigNPSPollModalResource;
use App\Http\Requests\Views\ClientyConfigurations\NPSPoll\ModalNPSPollRequest;
use App\Http\Requests\Views\ClientyConfigurations\NPSPoll\NPSPollExportInfoRequest;
use App\Http\Requests\Views\ClientyConfigurations\NPSPoll\ListClientyConfigurationNPSPollRequest;


class NPSPollController extends BaseAPIController
{

    public function list(ListClientyConfigurationNPSPollRequest $req)
    {
        $polls = resolve(NPSPollService::class)->list($req->validated());
        return $this->getSuccessResponse(new NPSPollsResourceCollection($polls));
    }


    public function modal(NPSPoll $NPSPoll, ModalNPSPollRequest $req)
    {
        return $this->getSuccessResponse(new ClientyConfigNPSPollModalResource($NPSPoll));
    }


    public function export(NPSPoll $NPSPoll, NPSPollExportInfoRequest $req)
    {
        SystemHelper::setMemoryLimitMB(500);
        return (new NPSPollAnswersExport($NPSPoll))->download('reporte-encuesta-nps.xlsx');
    }

}
