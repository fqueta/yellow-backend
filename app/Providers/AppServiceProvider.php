<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Helpers\StringHelper;
use Inertia\Inertia;
use Illuminate\Foundation\AliasLoader;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('escola', function () {
            return new \App\Services\Escola();
        });
        $this->app->singleton('qlib', function () {
            return new \App\Services\Qlib();
        });
        // $this->app->singleton(StringHelper::class, function () {
        //     return new StringHelper();
        // });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registrar facade Qlib
        $loader = AliasLoader::getInstance();
        $loader->alias('Qlib', \App\Facades\QlibFacade::class);
        
        Inertia::share('nav', [
            ['label' => 'Dashboard', 'href' => '/dashboard'],
            ['label' => 'Usuários', 'href' => '/users'],
            ['label' => 'Configurações', 'href' => '/settings'],
        ]);
    }
}
