<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    | Aqui você define quais rotas terão CORS liberado.
    | Exemplo: ['api/*'] -> todas rotas que começam com /api
    | ['*'] -> todas as rotas
    |
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Methods
    |--------------------------------------------------------------------------
    |
    | Métodos HTTP permitidos. Use ['*'] para liberar todos.
    |
    */
    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Quais domínios podem consumir sua API. Use ['*'] para liberar todos.
    | Exemplo: ['http://localhost:3000', 'https://meusite.com']
    |
    */
    'allowed_origins' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins Patterns
    |--------------------------------------------------------------------------
    |
    | Você pode usar regex para liberar origens.
    |
    */
    'allowed_origins_patterns' => [],

    /*
    |--------------------------------------------------------------------------
    | Allowed Headers
    |--------------------------------------------------------------------------
    |
    | Cabeçalhos permitidos. Use ['*'] para liberar todos.
    |
    */
    'allowed_headers' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Exposed Headers
    |--------------------------------------------------------------------------
    |
    | Cabeçalhos que podem ser expostos para o navegador.
    |
    */
    'exposed_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Max Age
    |--------------------------------------------------------------------------
    |
    | Tempo em segundos que o navegador deve cachear as requisições preflight.
    |
    */
    'max_age' => 0,

    /*
    |--------------------------------------------------------------------------
    | Supports Credentials
    |--------------------------------------------------------------------------
    |
    | Se true, permite envio de cookies/autenticação cross-origin.
    |
    */
    'supports_credentials' => false,

];
