<?php

use App\Http\Controllers\TesteController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/ini', function () {
    return Inertia::render('welcome');
})->name('ini');

// Route::middleware(['auth', 'verified'])->group(function () {
//     Route::get('dashboard', function () {
//         return Inertia::render('dashboard');
//     })->name('dashboard');


// });

// require __DIR__.'/settings.php';
// require __DIR__.'/auth.php';

use App\Models\Tenant;
use Illuminate\Http\Request;

if (app()->isLocal()) {
    Route::post('/create-tenant', function (Request $request) {
        $data = $request->validate([
            'id' => 'required|string',
            'domain' => 'nullable|string',
            'force' => 'boolean', 
        ]);

        try {
            $id = $data['id'];
            $domain = $data['domain'] ?? $id . '.localhost';

            if ($tenant = Tenant::find($id)) {
                if ($request->boolean('force')) {
                    $tenant->delete();
                } else {
                    return response()->json(['error' => 'Tenant ja existe. Use force=true para sobrescrever.'], 409);
                }
            }

            $tenant = Tenant::create([
                'id' => $id,
                'ativo' => 's',
                'excluido' => 'n',
                'deletado' => 'n'
            ]);

            $tenant->domains()->create(['domain' => $domain]);
            
            tenancy()->initialize($tenant);

            return response()->json([
                'message' => 'Tenant criado com sucesso', 
                'tenant' => $tenant->toArray(), 
                'domain' => $domain,
                'database' => config('database.connections.tenant.database')
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });
}
