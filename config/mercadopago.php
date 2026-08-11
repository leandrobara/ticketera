<?php

return [
    'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN'),
    'public_key' => env('MERCADO_PAGO_PUBLIC_KEY'),
    'notification_secret' => env('MERCADO_PAGO_NOTIFICATION_SECRET'),

    'payment_methods' => [
        'installments' => 1,
        'excluded_payment_types' => [
            ['id' => 'ticket'],
            ['id' => 'bank_transfer'],
            ['id' => 'atm'],
        ],
    ],

    'urls' => [
        'success' => env('MERCADO_PAGO_SUCCESS_URL', rtrim(env('APP_URL', 'http://localhost'), '/').'/checkout/success'),
        'failure' => env('MERCADO_PAGO_FAILURE_URL', rtrim(env('APP_URL', 'http://localhost'), '/').'/checkout/failure'),
        'pending' => env('MERCADO_PAGO_PENDING_URL', rtrim(env('APP_URL', 'http://localhost'), '/').'/checkout/pending'),
        'notification' => env('MERCADO_PAGO_NOTIFICATION_URL', rtrim(env('APP_URL', 'http://localhost'), '/').'/api/notifications/mercado-pago'),
    ],
];
