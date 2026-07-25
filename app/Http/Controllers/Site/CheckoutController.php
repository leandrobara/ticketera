<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;

class CheckoutController extends Controller
{
    public function result(string $status)
    {
        abort_unless(in_array($status, ['success', 'failure', 'pending'], true), 404);

        return view('site.app', [
            'page' => 'checkout-result',
            'checkoutStatus' => $status,
        ]);
    }
}
