<?php

namespace App\Http\Controllers\API\Worker;

use DateTime;
use Throwable;
use Exception;
use App\Models\Client;
use App\Helpers\LockHelper;
use Illuminate\Http\Request;
use App\Services\API\WAPIService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\MongoDB\ClientUsageLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Dispatchers\ClientEventsDispatcherService;


// Deprecado, no se usa más (17 Jul 2023)
class ClientUsageLogWorkerController extends BaseAPIController
{

    public function __construct()
    {
        \Debugbar::disable();
    }


    // Deprecado, no se usa más (17 Jul 2023)
    public function dispatchCreateAllClientsReportFileJob(Request $request)
    {
        // $clientEventsDispatcherService = resolve(ClientEventsDispatcherService::class);
        // $clientEventsDispatcherService->dispatchCreateAllClientsUsageReportFileJob();
        // return 'CreateAllClientsUsageReportFileJob dispatched';
    }

}
