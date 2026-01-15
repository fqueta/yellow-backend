<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => [
        'https://api-clubeyellow.maisaqui.com.br',
        'https://clubeyellow.maisaqui.com.br',
        'https://api-cl.yellowbc.com.br',
        'https://cl.yellowbc.com.br',
        'http://localhost:8080', // Para desenvolvimento
        'http://127.0.0.1:8080', // Para desenvolvimento
        'http://yellow-dev.localhost:8080', // Para frontend local
        'http://yellow-dev.localhost:8000', // Para XAMPP local
        'http://localhost:8000', // Para desenvolvimento local
        'http://127.0.0.1:8000', // Para desenvolvimento local
        'http://localhost:2000', // Frontend Vite (Novo)
        'http://127.0.0.1:2000', // Frontend Vite (Novo)
    ],
    'allowed_origins_patterns' => [
        '#^http://.*\.localhost(:[0-9]+)?$#', // Regex válida para subdomínios locais
    ],
    'allowed_headers' => ['*', 'x-form-token'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
