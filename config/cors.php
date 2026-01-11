<?php

return [
    'paths' => ['*'], // Tetap pakai bintang agar aman di Vercel

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'https://zona-fe-pad.vercel.app', // TANPA slash di akhir
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true, 
];