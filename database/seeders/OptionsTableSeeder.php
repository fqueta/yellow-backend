<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OptionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('options')->truncate();
        DB::table('options')->insert([
            [
                'name'  => 'Id da permissão dos clientes',
                'value' => '6',
                'url'   => 'permission_client_id',
                'tags'  => 'permission',
            ],
            [
                'name'  => 'Id da permissão dos fornecedores',
                'value' => '5',
                'url'   => 'permission_partner_id',
                'tags'  => 'permission',
            ],
            [
                'name'  => 'Url Api Alloyal',
                'value' => 'https://api.lecupon.com',
                'url'   => 'url_api_aloyall',
                'tags'  => 'alloyal',
            ],
            [
                'name'  => 'Email Admin Api Alloyal',
                'value' => '',
                'url'   => 'email_admin_api_alloyal',
                'tags'  => 'alloyal',
            ],
            [
                'name'  => 'Token da Api Alloyal',
                'value' => '',
                'url'   => 'token_api_alloyal',
                'tags'  => 'alloyal',
            ],
            [
                'name'  => 'Business ID Alloyal',
                'value' => '',
                'url'   => 'business_id_alloyal',
                'tags'  => 'alloyal',
            ],
            [
                'name'  => 'Link ativação cadastro',
                'value' => 'http://yellow-dev.localhost:8080/form-client-active/{cpf}',
                'url'   => 'link_active_cad',
                'tags'  => 'link',
            ],
            [
                'name'  => 'Link para arquivos',
                'value' => 'http://yellow-dev.localhost:8000{image}',
                'url'   => 'link_files',
                'tags'  => 'link',
            ],
            [
                'name'  => 'Redirecionar para login Alloyal',
                'value' => 'https://clube.yellowbc.com.br/',
                'url'   => 'redirect_url_login_alloyal',
                'tags'  => 'alloyal',
            ],
        ]);
    }
}
