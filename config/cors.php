<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'https://api-clubeyellow.maisaqui.com.br',
        'https://clubeyellow.maisaqui.com.br',
        'http://localhost:8080', // Para desenvolvimento
        'http://127.0.0.1:8080', // Para desenvolvimento
        'http://yellow-dev.localhost:8080', // Para frontend local
        'http://yellow-dev.localhost:8000', // Para XAMPP local
        'http://localhost:8000', // Para desenvolvimento local
        'http://127.0.0.1:8000', // Para desenvolvimento local
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
