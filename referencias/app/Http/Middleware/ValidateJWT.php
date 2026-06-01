<?php

namespace App\Http\Middleware;

use Closure;
use App\Helpers\JwtHelper;
use App\Exceptions\HttpException;
use App\Exceptions\JWT\ExpiredJWTException;
use App\Exceptions\JWT\InvalidJWTFormatException;
use App\Exceptions\JWT\InvalidJWTSecretException;
use App\Exceptions\JWT\InvalidJWTAlgorithmException;
use App\Exceptions\JWT\InvalidJWTSignatureException;
use App\Exceptions\Middleware\AuthenticationException;


class ValidateJWT
{

    public function handle($request, Closure $next)
    {
        try {
            $authHeader = $request->bearerToken();
            // if no header
            if (!$authHeader) {
                throw new AuthenticationException();
            }
            // verify jwt
            JwtHelper::verify($authHeader, config('auth.jwt.secret'), config('auth.jwt.algo'));
            $payload = JwtHelper::getPayloadData($authHeader);
            if (!$payload) {
                throw new AuthenticationException();
            }
            // merge in request
            $request->merge(['jwtPayload' => $payload]);
        } catch (InvalidJWTFormatException $e) {
            throw new HttpException('invalid_jwt_token_jwt_strings_must_contain_exactly_2_period_characters', 400);
        } catch (InvalidJWTSecretException $e) {
            throw new HttpException('invalid_signature', 401);
        } catch (ExpiredJWTException $e) {
            throw new HttpException('expired_token', 401);
        } catch (InvalidJWTSignatureException $e) {
            throw new HttpException('invalid_token', 401);
        } catch (InvalidJWTAlgorithmException $e) {
            throw new HttpException('invalid_or_null_algorithm', 500);
        }
        return $next($request);
    }

}
