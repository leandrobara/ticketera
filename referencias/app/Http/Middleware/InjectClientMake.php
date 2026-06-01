<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use App\Models\Client;
use App\Exceptions\HttpException;
use App\Services\API\ClientService;
use App\Repositories\ClientRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class InjectClientMake
{

    public function handle($req, Closure $next)
    {
        try {
            $subdomain = $req->input('subdomain');
            if (!$subdomain) {
                throw new Exception('InjectClientMake | Client subdomain parameter is missing');
            }
            $cleanSubdomain = $this->getCleanSubdomain($subdomain);

            $client = resolve(ClientRepository::class)->findOneBySubdomain($cleanSubdomain);
            if (!$client) {
                throw new Exception('InjectClientMake | Client does not exist');
            }

            $req->merge(['client' => $client]);
        } catch (Exception $e) {
            throw $e;
        }
        return $next($req);
    }


    private function getCleanSubdomain(string $url): string
    {
        $url = trim($url);
        // Intentamos parsear la URL para obtener solo el host.
        $host = parse_url($url, PHP_URL_HOST);
        // Si el parseo falla (probablemente porque no era una URL válida), usamos el string original.
        if (!$host) {
            $host = $url;
        }
        // Contamos cuántos puntos hay en el host.
        $dotsCount = substr_count($host, '.');
        // Si hay 2 o más puntos, buscamos el subdominio con una expresión regular.
        if ($dotsCount >= 2) {
            if (preg_match('/^(.+?)\.[^\.]+\.[^\.]+$/', $host, $matches)) {
                return $matches[1]; // Subdominio encontrado
            }
        } elseif ($dotsCount == 1) {
            // Si solo hay un punto, asumimos que el primer segmento es el subdominio.
            $parts = explode('.', $host);
            return $parts[0];
        }
        // Si no hay puntos o no se cumplen las condiciones anteriores, devolvemos el string original.
        return $host;
    }

}
