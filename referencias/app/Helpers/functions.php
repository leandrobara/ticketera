<?php

use App\Models\Client;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use App\Services\API\Dispatchers\ClientEventsDispatcherService;


function saveVisitedScreenUrl(Request $req)
{
    resolve(ClientEventsDispatcherService::class)->dispatchSaveVisitedScreenUrlUsageLogJob($req);
}


function cacheUrl($fileUrl)
{
    if (!$fileUrl) {
        return '';
    }
    // $assetUrl = config('app.asset_url');
    $key = config('app.assets_cache_param_value');
    if (environmentIsLocal()) {
        $key = mt_rand(1, 9999999999);
    }
    $arr = explode('?', $fileUrl);
    $arr[] = '__v=' . $key;
    $newUrl = array_shift($arr) . '?' . implode('&', $arr);
    // if ($assetUrl) {
    //     $newUrl = $assetUrl . '/' . $newUrl;
    //     $newUrl = str_replace('//', '/', $newUrl);
    // }
    $newUrl = asset($newUrl);
    return $newUrl;
}


function imgToBase64String(string $imgPath)
{
    $imgFullPath = public_path($imgPath);
    if (!file_exists($imgFullPath)) {
        return '';
    }
    $base64 = Cache::store('redis')->get('imgToBase64String::' . $imgPath);
    if ($base64) {
        return $base64;
    }
    $type = pathinfo($imgFullPath, PATHINFO_EXTENSION);
    $data = file_get_contents($imgFullPath);
    $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
    Cache::store('redis')->set('imgToBase64String::' . $imgPath, $base64);
    return $base64;
}


function environmentIn(array $envs)
{
    $currentEnv = strtolower(config('app.env'));
    return in_array($currentEnv, $envs);
}

function environmentNotIn(array $envs)
{
    return !environmentIn($envs);
}

function environmentIsLocal()
{
    return environmentIn(['local']);
}

function environmentIsNotProduction()
{
    return environmentNotIn(['prod', 'production']);
}

function environmentIsTesting()
{
    return environmentIn(['testing', 'test']);
}

function environmentIsProduction()
{
    return environmentIn(['prod', 'production']);
}


function redirectEmails()
{
    return config('emails.redirect_emails') ?? true;
}


function getTimezoneStringByCountryCode(string $countryCode): string
{
    $countryCode = trim(strtoupper($countryCode));
    switch ($countryCode) {
        case 'AR':
            return 'America/Argentina/Buenos_Aires';
        case 'CL':
            return 'America/Santiago';
        case 'BR':
            return 'America/Sao_Paulo';
        case 'CR':
            return 'America/Costa_Rica';
        case 'MX':
            return 'America/Mexico_City';
        case 'CO':
            return 'America/Bogota';
        case 'UY':
            return 'America/Montevideo';
        case 'PE':
            return 'America/Lima';
        case 'ES':
            return 'Europe/Madrid';
        case 'SV':
            return 'America/El_Salvador';
        case 'BO':
            return 'America/La_Paz';
        case 'PY':
            return 'America/Asuncion';
        case 'VE':
            return 'America/Caracas';
        case 'HN':
            return 'America/Tegucigalpa';
        case 'US':
            return 'America/Chicago';
        case 'NI':
            return 'America/Managua';
        case 'DO':
            return 'America/Santo_Domingo';
        case 'HN':
            return 'America/Tegucigalpa';
        case 'GT':
            return 'America/Guatemala';
    }
    $timezones = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, $countryCode);
    $timezoneStr = $timezones[0] ?? 'America/Argentina/Buenos_Aires';
    return $timezoneStr;
}


/**
 * Devuelve la diferencia SOLO entre las horas (sin tener en cuenta fechas). Y la diferencia absoluta más cercana.
 * Ej:
 * - entre 23 y 02 -> 3
 * - entre 02 y 23 -> 3
 * - entre 00 y 15 -> 9
 * - entre 15 y 00 -> 9
 **/
function absoluteDatesHoursDiff(DateTime $dateTime1, DateTime $dateTime2): int
{
    $hour1 = (int) $dateTime1->format('H');
    $hour2 = (int) $dateTime2->format('H');
    return absoluteHoursDiff($hour1, $hour2);
}

function absoluteHoursDiff(int $hour1, int $hour2): int
{
    $hoursDiff = 0;
    while ($hour1 != $hour2) {
        $hoursDiff++;
        $hour1++;
        if ($hour1 >= 24) {
            $hour1 = 0;
        }
    }
    if ($hoursDiff > 12) {
        $hoursDiff = 24 - $hoursDiff;
    }
    return $hoursDiff;
}


function clientUrl(Client $client, ?string $uri = null): string
{
    $clientUrl = str_replace('://', "://{$client->subdomain}.", config('app.url'));
    if ($uri) {
        $clientUrl = $clientUrl . $uri;
    }
    return $clientUrl;
}