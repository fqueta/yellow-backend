<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Services\Qlib;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                // 'id' => Qlib::token(),
                'name' => 'Fernando Queta',
                'email' => 'quetafernando1@gmail.com',
                'password' => Hash::make('ferqueta'),
                'status' => 'actived',
                'verificado' => 'n',
                'permission_id' => 1, // Grupo Master
            ],
            [
                // 'id' => Qlib::token(),
                'name' => 'Test User',
                'email' => 'ger.maisaqui1@gmail.com',
                'password' => Hash::make('mudar123'),
                'status' => 'actived',
                'verificado' => 'n',
                'permission_id' => 2, // Grupo Administrador
            ],
            [
                // 'id' => Qlib::token(),
                'name' => 'Test User',
                'email' => 'ferqueta@yahoo.com',
                'password' => Hash::make('12345678'),
                'status' => 'actived',
                'verificado' => 'n',
                'permission_id' => 6, // Grupo CLientes
            ],
        ];

        foreach ($users as $userData) {
            // dump($userData);
            // User::updateOrCreate(
            //     ['email' => $userData['email']], // evita duplicados
            //     $userData
            // );
            User::create(
                $userData
            );
        }
    }
}
