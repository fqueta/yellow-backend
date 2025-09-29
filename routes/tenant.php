<?php

declare(strict_types=1);

use App\Http\Controllers\admin\DashboardController;
use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\api\ClientController;
use App\Http\Controllers\api\FinancialController;
use App\Http\Controllers\api\PartnerController;
use App\Http\Controllers\api\PointController;
use App\Http\Controllers\api\PublicFormTokenController;
use App\Http\Controllers\api\MenuPermissionController;
use App\Http\Controllers\api\OptionController;
use App\Http\Controllers\api\PermissionController;
use App\Http\Controllers\api\PostController;
use App\Http\Controllers\api\AircraftController;
use App\Http\Controllers\api\CategoryController;
use App\Http\Controllers\api\FinancialCategoryController;
use App\Http\Controllers\api\MetricasController;
use App\Http\Controllers\api\DashboardMetricController;
use App\Http\Controllers\api\ProductUnitController;
use App\Http\Controllers\api\ProductController;
use App\Http\Controllers\api\ServiceController;
use App\Http\Controllers\api\ServiceUnitController;
use App\Http\Controllers\api\ServiceOrderController;
use App\Http\Controllers\api\RegisterController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\TesteController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Http\Controllers\Api\PermissionMenuController;
use App\Http\Controllers\api\UserController;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    // Route::get('/', function () {
    //     return Inertia::render('welcome');
    // })->name('home');
    Route::get('/teste', [ TesteController::class,'index'])->name('teste');
    // // Route::get('/', function () {
    //     //     return 'This is your multi-tenant application. The id of the current tenant is ' . tenant('id');
    //     // });
    // // Route::middleware(['auth', 'verified'])->group(function () {
    // //     Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // //     // Route::get('profile', function () {
    // //     //     return Inertia::render('profile');
    // //     // })->name('profile');
    // // });

    // require __DIR__.'/settings.php';
    // require __DIR__.'/auth.php';

});

Route::name('api.')->prefix('api/v1')->middleware([
    'api',
    // 'auth:sanctum',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::post('/login',[AuthController::class,'login'])->name('login');

    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');
    Route::post('register', [RegisterController::class, 'store']);
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    // Rotas públicas para tokens de formulário
    Route::post('public/form-token', [PublicFormTokenController::class, 'generateToken'])->name('public.form-token.generate');
    Route::post('public/form-token/validate', [PublicFormTokenController::class, 'validateToken'])->name('public.form-token.validate');

    // Rota de ativação de cliente com validação de token
    Route::post('clients/active', [ClientController::class, 'store_active'])
        ->middleware('validate.public.form.token')
        ->name('clients.active');

    Route::fallback(function () {
        return response()->json(['message' => 'Rota não encontrada'], 404);
    });
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user',[UserController::class,'perfil'])->name('perfil.user');
        Route::get('user/can',[UserController::class,'can_access'])->name('perfil.can');
        Route::post('/logout',[AuthController::class,'logout'])->name('logout');
        
        // Rota do dashboard
        Route::get('dashboard', [\App\Http\Controllers\api\DashboardController::class, 'index'])->name('dashboard');
        Route::apiResource('users', UserController::class,['parameters' => [
            'users' => 'id'
        ]]);
        // registro cliente mileto
        Route::get('clients/registred', [ClientController::class, 'pre_registred'])->name('clients.index_pre_registred');
        Route::post('clients/registred', [ClientController::class, 'pre_registred'])->name('clients.pre_registred');
        Route::put('clients/registred', [ClientController::class, 'pre_registred'])->name('clients.update_pre_registred');
        Route::delete('clients/registred/{cpf}', [ClientController::class, 'inactivate'])->name('clients.inactivate');
        Route::apiResource('clients', ClientController::class,['parameters' => [
            'clients' => 'id'
        ]]);
        Route::get('clients/trash', [ClientController::class, 'trash'])->name('clients.trash');
        Route::put('clients/{id}/restore', [ClientController::class, 'restore'])->name('clients.restore');
        Route::delete('clients/{id}/force', [ClientController::class, 'forceDelete'])->name('clients.forceDelete');

        // Rotas para partners
        Route::apiResource('partners', PartnerController::class,['parameters' => [
            'partners' => 'id'
        ]]);
        Route::get('partners/trash', [PartnerController::class, 'trash'])->name('partners.trash');
        Route::put('partners/{id}/restore', [PartnerController::class, 'restore'])->name('partners.restore');
        Route::delete('partners/{id}/force', [PartnerController::class, 'forceDelete'])->name('partners.forceDelete');

        // Rotas para financial
        Route::apiResource('financial', FinancialController::class,['parameters' => [
            'financial' => 'id'
        ]]);
        Route::get('financial/trash', [FinancialController::class, 'trash'])->name('financial.trash');
        Route::put('financial/{id}/restore', [FinancialController::class, 'restore'])->name('financial.restore');
        Route::delete('financial/{id}/force', [FinancialController::class, 'forceDelete'])->name('financial.forceDelete');
        Route::put('financial/{id}/mark-as-paid', [FinancialController::class, 'markAsPaid'])->name('financial.markAsPaid');
        Route::get('financial/summary', [FinancialController::class, 'summary'])->name('financial.summary');

        // Rotas para points
        Route::apiResource('points', PointController::class,['parameters' => [
            'points' => 'id'
        ]]);
        Route::get('points/trash', [PointController::class, 'trash'])->name('points.trash');
        Route::put('points/{id}/restore', [PointController::class, 'restore'])->name('points.restore');
        Route::delete('points/{id}/force', [PointController::class, 'forceDelete'])->name('points.forceDelete');
        Route::get('points/cliente/{clienteId}/saldo', [PointController::class, 'saldoCliente'])->name('points.saldoCliente');
        Route::get('points/relatorio', [PointController::class, 'relatorio'])->name('points.relatorio');
        Route::post('points/expirar', [PointController::class, 'expirarPontos'])->name('points.expirarPontos');

        // Rotas para options
        Route::apiResource('options', OptionController::class,['parameters' => [
            'options' => 'id'
        ]]);
        Route::post('options/all', [OptionController::class, 'fast_update_all'])->name('options.all');
        Route::get('options/trash', [OptionController::class, 'trash'])->name('options.trash');
        Route::put('options/{id}/restore', [OptionController::class, 'restore'])->name('options.restore');
        Route::delete('options/{id}/force', [OptionController::class, 'forceDelete'])->name('options.forceDelete');

        // Rotas para posts
        Route::apiResource('posts', PostController::class,['parameters' => [
            'posts' => 'id'
        ]]);
        Route::get('posts/trash', [PostController::class, 'trash'])->name('posts.trash');
        Route::put('posts/{id}/restore', [PostController::class, 'restore'])->name('posts.restore');
        Route::delete('posts/{id}/force', [PostController::class, 'forceDelete'])->name('posts.forceDelete');

        // Rotas para aircraft
        Route::apiResource('aircraft', AircraftController::class,['parameters' => [
            'aircraft' => 'id'
        ]]);
        Route::get('aircraft/trash', [AircraftController::class, 'trash'])->name('aircraft.trash');
        Route::put('aircraft/{id}/restore', [AircraftController::class, 'restore'])->name('aircraft.restore');
        Route::delete('aircraft/{id}/force', [AircraftController::class, 'forceDelete'])->name('aircraft.forceDelete');

        // Rotas para categories
        Route::apiResource('categories', CategoryController::class,['parameters' => [
            'categories' => 'id'
        ]]);
        Route::get('categories/trash', [CategoryController::class, 'trash'])->name('categories.trash');
        Route::put('categories/{id}/restore', [CategoryController::class, 'restore'])->name('categories.restore');
        Route::delete('categories/{id}/force', [CategoryController::class, 'forceDelete'])->name('categories.forceDelete');
        Route::get('categories/tree', [CategoryController::class, 'tree'])->name('categories.tree');
        Route::get('service-categories', [CategoryController::class, 'indexServiceCategories'])->name('service-categories');
        /**Rota para o cadasto de produto */
        Route::get('product-categories', [CategoryController::class, 'index'])->name('product-categories');

        // Rotas para financial/categories
        Route::apiResource('financial/categories', FinancialCategoryController::class,[
            'parameters' => ['categories' => 'id'],
            'names' => [
                'index' => 'financial.categories.index',
                'store' => 'financial.categories.store',
                'show' => 'financial.categories.show',
                'update' => 'financial.categories.update',
                'destroy' => 'financial.categories.destroy'
            ]
        ]);
        Route::get('financial/categories/trash', [FinancialCategoryController::class, 'trash'])->name('financial.categories.trash');
        Route::put('financial/categories/{id}/restore', [FinancialCategoryController::class, 'restore'])->name('financial.categories.restore');
        Route::delete('financial/categories/{id}/force', [FinancialCategoryController::class, 'forceDelete'])->name('financial.categories.forceDelete');

        // Rotas para product-units
        Route::apiResource('product-units', ProductUnitController::class,['parameters' => [
            'product-units' => 'id'
        ]]);
        Route::get('product-units/trash', [ProductUnitController::class, 'trash'])->name('product-units.trash');
        Route::put('product-units/{id}/restore', [ProductUnitController::class, 'restore'])->name('product-units.restore');
        Route::delete('product-units/{id}/force', [ProductUnitController::class, 'forceDelete'])->name('product-units.forceDelete');

        // Rotas para products
        Route::get('products/trash', [ProductController::class, 'trash'])->name('products.trash');
        Route::put('products/{id}/restore', [ProductController::class, 'restore'])->name('products.restore');
        Route::delete('products/{id}/force', [ProductController::class, 'forceDelete'])->name('products.forceDelete');
        Route::apiResource('products', ProductController::class,['parameters' => [
            'products' => 'id'
        ]]);

        // Rotas para services
        Route::apiResource('services', ServiceController::class,['parameters' => [
            'services' => 'id'
        ]]);
        Route::get('services/trash', [ServiceController::class, 'trash'])->name('services.trash');
        Route::put('services/{id}/restore', [ServiceController::class, 'restore'])->name('services.restore');
        Route::delete('services/{id}/force', [ServiceController::class, 'forceDelete'])->name('services.forceDelete');

         // Rotas para service-units
         Route::apiResource('service-units', ServiceUnitController::class,['parameters' => [
             'service-units' => 'id'
         ]]);
         Route::get('service-units/trash', [ServiceUnitController::class, 'trash'])->name('service-units.trash');
         Route::put('service-units/{id}/restore', [ServiceUnitController::class, 'restore'])->name('service-units.restore');
         Route::delete('service-units/{id}/force', [ServiceUnitController::class, 'forceDelete'])->name('service-units.forceDelete');

         // Rotas para service-orders
         Route::apiResource('service-orders', ServiceOrderController::class,['parameters' => [
             'service-orders' => 'id'
         ]]);
         Route::get('service-orders/trash', [ServiceOrderController::class, 'trash'])->name('service-orders.trash');
         Route::put('service-orders/{id}/restore', [ServiceOrderController::class, 'restore'])->name('service-orders.restore');
         Route::put('service-orders/{id}/status ', [ServiceOrderController::class, 'updateStatus'])->name('service-orders.update-status');
         Route::delete('service-orders/{id}/force', [ServiceOrderController::class, 'forceDelete'])->name('service-orders.forceDelete');

         // Rotas para dashboard-metrics
        Route::apiResource('dashboard-metrics', MetricasController::class,['parameters' => [
            'dashboard-metrics' => 'id'
        ]]);
        Route::post('dashboard-metrics/import-aeroclube', [MetricasController::class, 'importFromAeroclube'])->name('dashboard-metrics.import-aeroclube');

        // Route::apiResource('clients', ClientController::class,['parameters' => [
        //     'clients' => 'id'
        // ]]);
        Route::get('users/trash', [UserController::class, 'trash'])->name('users.trash');
        Route::get('metrics/filter', [MetricasController::class, 'filter']);
        Route::apiResource('metrics', MetricasController::class,['parameters' => [
            'metrics' => 'id'
        ]]);
        // rota flexível de filtros
        Route::get('menus', [MenuController::class, 'getMenus']);
        Route::apiResource('permissions', PermissionController::class,['parameters' => [
            'permissions' => 'id'
        ]]);
        Route::prefix('permissions')->group(function () {
            Route::get('{id}/menu-permissions', [MenuPermissionController::class, 'show'])->name('menu-permissions.show');
            Route::put('{id}/menu-permissions', [MenuPermissionController::class, 'updatePermissions'])->name('menu-permissions.update');
            // Route::post('{id}/menus', [PermissionMenuController::class, 'update']);
        });

    });


});
