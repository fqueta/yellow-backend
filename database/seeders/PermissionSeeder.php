<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('permissions')->delete();

        DB::table('permissions')->insert([
            // MASTER → acesso a tudo
            [
                'name' => 'Master',
                'description' => 'Desenvolvedores',
                'redirect_login' => '/home',
                'active' => 's',
            ],

            // ADMINISTRADOR → tudo, mas em configurações só "Usuários" e "Perfis"
            [
                'name' => 'Administrador',
                'description' => 'Administradores do sistema',
                'redirect_login' => '/home',
                'active' => 's'
            ],

            // GERENTE → todos os menus exceto configurações
            [
                'name' => 'Gerente',
                'description' => 'Gerente do sistema (sem acesso a configurações)',
                'redirect_login' => '/home',
                'active' => 's'
            ],

            // ESCRITÓRIO → somente dois primeiros menus
            [
                'name' => 'Escritório',
                'description' => 'Acesso permitido para Escritório',
                'redirect_login' => '/home',
                'active' => 's'
            ],
            // Cliente → para clientes sem acesso ao admin
            [
                'name' => 'Parceiros',
                'description' => 'Clientes empresas parceiras',
                'redirect_login' => '/home',
                'active' => 's'
            ],
            // Cliente → para clientes sem acesso ao admin
            [
                'name' => 'Cliente',
                'description' => 'Acesso limitado a Dashboard e Clientes',
                'redirect_login' => '/home',
                'active' => 's'
            ],
        ]);
    }
}
