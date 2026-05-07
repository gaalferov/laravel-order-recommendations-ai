<?php

return [
    'mailtrap' => [
        'apiKey' => env('MAILTRAP_API_KEY'),
        'category' => env('MAILTRAP_CATEGORY', 'Order Confirmation'),
    ],
];
