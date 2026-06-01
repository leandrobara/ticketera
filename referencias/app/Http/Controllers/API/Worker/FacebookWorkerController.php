<?php

namespace App\Http\Controllers\API\Worker;

use Throwable;
use App\Models\Client;
use App\Helpers\LockHelper;
use Illuminate\Http\Request;
use App\Helpers\SystemHelper;
use App\Helpers\CronTabHelper;
use App\Helpers\FacebookAdHelper;
use App\Helpers\FacebookPageHelper;
use App\Helpers\WorkerOutputFormatter;
use App\Http\Controllers\API\BaseAPIController;
use App\Repositories\ClientFacebookPageRepository;


class FacebookWorkerController extends BaseAPIController
{

    public function __construct()
    {
        \Debugbar::disable();
        SystemHelper::setManualFlush();
        SystemHelper::setTimeLimit(900);
        SystemHelper::setMemoryLimitMB(500);
    }


    public function refreshExpiredUserTokens(Request $req)
    {
        $lockKey = 'refreshExpiredUserTokens';
        if (CronTabHelper::workerCronIsRunning($lockKey)) {
            die('Locked: cron is already runnning');
        }
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 900)) {
            die('Locked');
        }

        $clientId = $req->input('client_id');
        $clients = Client::where('enabled', true)->get();
        $facebookAdHelper = resolve(FacebookAdHelper::class);
        $facebookPageHelper = resolve(FacebookPageHelper::class);
        $clientFacebookPageRepository = resolve(ClientFacebookPageRepository::class);

        foreach ($clients as $client) {
            if ($clientId && $client->id != $clientId) {
                continue;
            }

            SystemHelper::doFlush();
            WorkerOutputFormatter::heading("Client ID {$client->id}: {$client->name}", 3);

            $pages = $clientFacebookPageRepository->findAllByClient($client);
            $pages = $pages->filter(fn ($page) => !empty($page->user_token));

            if ($pages->isEmpty()) {
                WorkerOutputFormatter::message('No pages with user_token', 'muted', ['indent' => 1]);
                WorkerOutputFormatter::separator();
                continue;
            }

            foreach ($pages as $page) {
                try {
                    $tokenDebug = $facebookAdHelper->debugToken($page->user_token);

                    $isValid = $tokenDebug['is_valid'] ?? false;
                    $expiresAt = $tokenDebug['expires_at'] ?? 0;
                    $dataAccessExpiresAt = $tokenDebug['data_access_expires_at'] ?? 0;

                    $expirationTimestamp = $expiresAt > 0 ? $expiresAt : $dataAccessExpiresAt;
                    $daysRemaining = $expirationTimestamp > 0
                        ? (int) ceil(($expirationTimestamp - time()) / 86400)
                        : null;

                    WorkerOutputFormatter::data(
                        "Page {$page->page_id}",
                        [
                            'is_valid' => $isValid,
                            'expires_at' => $expiresAt,
                            'data_access_expires_at' => $dataAccessExpiresAt,
                            'days_remaining' => $daysRemaining,
                        ],
                        ['indent' => 1, 'collapsed' => false]
                    );

                    $shouldRefresh = !$isValid || ($daysRemaining !== null && $daysRemaining <= 10);

                    if ($shouldRefresh) {
                        $newToken = $facebookPageHelper->refreshLongLivedUserToken($page->user_token);
                        $page->user_token = $newToken;
                        $page->save();
                        WorkerOutputFormatter::message('Token refreshed', 'success', ['indent' => 2]);
                    } else {
                        WorkerOutputFormatter::message('Token OK, no refresh needed', 'muted', ['indent' => 2]);
                    }
                } catch (Throwable $e) {
                    WorkerOutputFormatter::message(
                        "Error page {$page->page_id}: {$e->getMessage()}", 'error', ['indent' => 2]
                    );
                    report($e);
                }
            }

            WorkerOutputFormatter::separator();
            resolve(LockHelper::class)->getLockByName($lockKey, 900);
        }

        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }

}
