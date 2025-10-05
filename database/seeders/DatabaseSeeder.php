<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
          $this->call([
            UserSeeder::class,
            // escolaridadeSeeder::class,
            // estadocivilSeeder::class,
            // ProfissaoSeeder::class,
            MenuSeeder::class, //cadastra menus permissõs e menu_permissions
            // PermissionSeeder::class,
            // MenuPermissionSeeder::class,
            DashboardMetricsSeeder::class,
            CategorySeeder::class,
            OptionsTableSeeder::class,
            ProductUnitsSeeder::class,
            ProductSeeder::class,
            // QoptionSeeder::class,
        ]);
    }
}
