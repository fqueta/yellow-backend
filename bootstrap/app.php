<?php

use App\Http\Middleware\DynamicCors;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ValidatePublicFormToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            '/create-tenant',
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Usar novo CORS Dinâmico para resolver problemas de Tenant/Produção
        $middleware->prepend(DynamicCors::class);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Aplicar CORS também para API
        $middleware->api(prepend: [
            DynamicCors::class,
        ]);
        
        // Registrar middlewares personalizados
        $middleware->alias([
            'validate.public.form.token' => ValidatePublicFormToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
