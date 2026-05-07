<?php

return [
    'default' => env('MAIL_MAILER', 'mailtrap'),

    'mailers' => [
        'mailtrap' => [
            'transport' => 'mailtrap',
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Order Confirmation'),
    ],
];
