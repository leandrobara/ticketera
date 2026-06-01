<?php

namespace App\Exceptions;

use Throwable;
use Exception;
use Illuminate\Support\Arr;
use App\Exceptions\HttpException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use App\Exceptions\ValidationException;
use App\Exceptions\Middleware\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Exceptions\Helpers\WAPIHelper\WAPIHelperException;
use App\Exceptions\Services\LeadService\ExistentLeadException;
use App\Exceptions\Services\AuthService\AuthorizationException;
use App\Exceptions\Services\WAutomations\UserWAPINotSyncedException;
use App\Exceptions\Services\LeadService\FacebookInvalidLeadException;
use App\Exceptions\Helpers\WAPIHelper\WAPIHelperUserNotSyncedException;
use App\Exceptions\Services\LeadsBulkUploadService\ExceededRowsException;
use App\Exceptions\Services\GoogleAPI\InvalidLinkedEmailAddressException;
use App\Exceptions\Services\WAutomations\WAutomationNotToReportException;
use App\Exceptions\Helpers\SpreadSheetLeadImportHelper\InvalidHeadersException;


class Handler
{

    // Custom
    protected $sentryDontReport = [
        HttpException::class,
        ExceededRowsException::class,
        ExistentLeadException::class,
        AuthorizationException::class,
        AuthenticationException::class,
        WAutomationNotToReportException::class,
        InvalidLinkedEmailAddressException::class,
        // WAPI
        WAPIHelperException::class,
        WAPIHelperUserNotSyncedException::class,
    ];

    protected $sentryDontReportByMessages = [
        HttpException::class => [
            'expired_token',
            'client_not_found',
        ],
        ValidationException::class => [
            'tag_already_exists',
            'lead_already_exists',
            'status_already_exists',
            'user_email_already_exists',
            'tag_category_does_not_exists',
            'user_username_already_exists',
            'user_is_not_synced_with_wapi', // WAPI
            'filters_search_max_allowed_lead_ids_exceeded',
            'lead_attachment_already_exists_for_this_lead',
            'lead_attachment_name_already_exists_for_this_lead',
        ],
        InvalidHeadersException::class => ['invalid_headers'],
        UserWAPINotSyncedException::class => ['user_is_not_synced_with_wapi'], // WAPI
        Exception::class => [
            'uploaded_file_invalid_mime_type',
            'missing_clienty_tab', // WAP SENDER
            'missing_whatsapp_tab', // WAP SENDER
            'wap_sender_unreachable', // WAP SENDER
            'user_is_not_synced_with_wapi', // WAPI
            'clienty_url_is_not_unique_in_tabs', // WAP SENDER
            'user_is_not_synced_with_wap_sender', // WAP SENDER
            'whatsapp_web_user_number_does_not_match', // WAP SENDER
            'whatsapp_sending_can_not_be_finished_because_is_already_finished',
        ],
    ];


    public function report(Throwable $exception)
    {
        $debug = config('app.debug');
        $hasToBeReportedToSentry = $this->hasToBeReportedToSentry($exception);
        if ($debug || $hasToBeReportedToSentry) {
            if (app()->bound('sentry')) {
                try {
                    \Sentry\captureException($exception);
                } catch (\Throwable $e) {
                    Log::error($e);
                }
            }
        }
    }


    public function hasToBeReportedToSentry(Throwable $exception): bool
    {
        $className = trim(strtolower(get_class($exception)));
        foreach ($this->sentryDontReport as $listClassName) {
            $listClassName = trim(strtolower($listClassName));
            if ($className == $listClassName) {
                return false;
            }
        }

        foreach ($this->sentryDontReportByMessages as $listClassName => $listMsgs) {
            $listClassName = trim(strtolower($listClassName));
            if ($className == $listClassName) {
                $exceptionMsg = $exception->getMessage();
                if (in_array($exceptionMsg, $listMsgs)) {
                    return false;
                }
            }
        }
        return true;
    }


    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        $this->rollbackOpenedTransactions();
        
        $isApi = Request::is('api/*');
        $isFBApi = Request::is('api/facebook/*');
        $isMakeApp = Request::is('api/integration/make/*');
        $isZapierApp = Request::is('api/integration/zapier/*');
        $jsonOutput = config('app.json_output');
        // SIEMPRE devuelvo 200 a Facebook y a Zapier
        if ($isFBApi || $isZapierApp || $isMakeApp) {
            $hasToBeReportedToSentry = $this->hasToBeReportedToSentry($exception);
            if ($hasToBeReportedToSentry) {
                resolve('sentry')->captureException($exception);
            }
            list($data, $code) = $this->buildJsonMessage($exception);

            $debug = config('app.debug');
            if ($debug) {
                return response()->json($data, 200);
            }
            if ($isZapierApp) {
                return response()->json($data, 200);
            }
            return response()->json([], 200);
        }

        //if is api route && json output is needed
        if ($isApi && $jsonOutput) {
            list($data, $code) = $this->buildJsonMessage($exception);
            return response()->json($data, $code);
        }
    }


    protected function rollbackOpenedTransactions(): void
    {
        do {
            $transactionLevel = DB::transactionLevel() ?? 0;
            if ($transactionLevel) {
                DB::rollBack();
            }
        } while ($transactionLevel > 0);
    }


    private function buildJsonMessage(Throwable $exception): array
    {
        $debug = config('app.debug');

        if ($exception instanceof ModelNotFoundException) {
            $exception = new HttpException($exception->getMessage(), 404);
        }

        $code =  $this->resolveCode($exception->getCode());
        $data = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => 'an_unknown_error_ocurred',
            ],
        ];

        // if ($code !== 500 || $debug) {
            $data['error']['message'] = $exception->getMessage();
        // }

        if ($debug && property_exists($exception, 'debugInfo')) {
            $data['debug'] = $exception->debugInfo;
        } elseif ($debug) {
            $data['debug']['exception'] = get_class($exception);
            $data['debug']['file'] = $exception->getFile();
            $data['debug']['line'] = $exception->getLine();
            $data['debug']['trace'] = collect($exception->getTrace())->map(function ($trace) {
                return Arr::except($trace, ['args']);
            })->all();
            report($exception);
        }

        return [$data, $code];
    }


    private function resolveCode($code): int
    {
        if ($code) {
            if ($code < 300 || $code > 500) {
                $code = 500;
            }
        } else {
            $code = 500;
        }
        if (!is_numeric($code)) {
            $code = 500;
        }
        return $code;
    }

}
