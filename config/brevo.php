<?php

return [
    'api_key' => env('BREVO_API_KEY'),
    'transactional_email_url' => env('BREVO_TRANSACTIONAL_EMAIL_URL', 'https://api.brevo.com/v3/smtp/email'),
    'sender' => [
        'name' => env('BREVO_SENDER_NAME', 'Entrada Tix'),
        'email' => env('BREVO_SENDER_EMAIL', env('MAIL_FROM_ADDRESS')),
    ],
];
