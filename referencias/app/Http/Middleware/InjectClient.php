<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use App\Models\Client;
use App\Exceptions\HttpException;
use App\Services\API\ClientService;
use App\Repositories\ClientRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class InjectClient
{

    public function handle($req, Closure $next)
    {
        try {
            list($subdomain) = explode('.', $req->getHost(), 2);
            if (!$subdomain) {
                throw new HttpException('client not found', 404);
            }
            // $client = Client::where(['subdomain' => $subdomain])->firstOrFail();
            // $client = resolve(ClientService::class)->findOneBySubdomain($subdomain);

            // No usar services en middlewares (pueden inicializar cosas que asumen que middlewares ya finalizaron)
            $client = resolve(ClientRepository::class)->findOneBySubdomain($subdomain);
            if (!$client) {
                throw new Exception('InjectClient | Client does not exist');
            }

            $req->merge(['client' => $client]);
        } catch (ModelNotFoundException $e) {
            throw new HttpException('client_not_found', 404);
        } catch (Exception $e) {
            throw $e;
        }
        return $next($req);
    }

}
