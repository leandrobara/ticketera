<?php

namespace App\Http\Controllers\API\Worker;

use DateTime;
use Throwable;
use Exception;
use App\Models\Client;
use App\Helpers\LockHelper;
use Illuminate\Http\Request;
use App\Helpers\SystemHelper;
use App\Models\GoogleAPIUserToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use App\Services\API\GoogleAPIUserTokenService;
use App\Http\Controllers\API\BaseAPIController;



class GoogleAPIWorkerController extends BaseAPIController
{

    public function __construct()
    {
        \Debugbar::disable();
        SystemHelper::setManualFlush();
    }


    public function deleteInvalidGoogleAPIUserTokens(Request $request)
    {
        $lockKey = 'deleteInvalidGoogleAPIUserTokens';
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 60)) {
            die('Locked');
        }

        $invalidTokens = resolve(GoogleAPIUserTokenService::class)->findWithInvalidToken();
        foreach ($invalidTokens as $googleToken) {
            $googleToken->delete();
            $this->printTokenInfo($googleToken);
        }

        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    private function printTokenInfo(GoogleAPIUserToken $googleToken): void
    {
        echo "- Google API User Token ID: {$googleToken->id} deleted. ";
        echo "Client: {$googleToken->client->name} (ID: {$googleToken->client->id}) <br/>";
    }

}
