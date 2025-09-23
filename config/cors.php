<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    
    // Para produção, seja mais específico com as origens
    'allowed_origins' => env('APP_ENV') === 'production' ? [
        'https://yellow-dev.maisaqui.com.br',
        'https://api-yellow-dev.maisaqui.com.br',
        'http://localhost:3000',
        'http://localhost:8080',
    ] : ['*'],
    
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true, // Importante para autenticação
];
