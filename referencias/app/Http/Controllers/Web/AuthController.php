<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller as BaseController;


class AuthController extends BaseController
{
    public function resetPasswordScreen(Request $request)
    {
        return view('web.auth.reset-password');
    }


    public function doPasswordReset(Request $request)
    {
        $path = '/api/auth/change-password';
        $host = 'https://' . $request->getHost();
        $querystring = http_build_query(request()->query());
        $endpoint = $host . $path . '?' . $querystring;
        
        $response = Http::withOptions(['verify' => false])->post($endpoint);
        $response = json_decode($response);

        $querystring = '?resetPassSuccess=true';
        if (!$response->success) {
            if ($response?->error?->message == 'invalid_password_link') {
                $querystring = '?resetPassSuccess=false&resetPassErr=invalid_password_link';
            }
            if ($response?->error?->message == 'already_used_reset_password_link') {
                $querystring = '?resetPassSuccess=false&resetPassErr=already_used_reset_password_link';
            }
        }
        
        $path = '/login';
        $urlLogin = $host . $path;
        $urlLogin .= $querystring;

        return redirect($urlLogin);
    }
}
