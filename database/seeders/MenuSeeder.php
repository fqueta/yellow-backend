<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\MenuPermission;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    /**
     * Recria menus, perfis base e permissões de menu sem usar truncate,
     * evitando erro de chave estrangeira nas tabelas relacionadas.
     */
    public function run()
    {
        $this->resetSeedTables();

        // Dashboard
        Menu::create([
            'title' => 'Dashboard',
            'url'   => '/',
            'icon'  => 'Home',
            'order' => 1,
        ]);

        // Clientes
        Menu::create([
            'title' => 'Clientes',
            'url'   => '/clients',
            'icon'  => 'Users',
            'order' => 2,
        ]);

        // Objetos do Serviço
        Menu::create([
            'title' => 'Parceiros',
            'url'   => '/partners',
            'icon'  => 'Partner',
            'order' => 3,
        ]);
        // Menu::create([
        //     'title' => 'Objetos do Serviço',
        //     'url'   => '/service-objects',
        //     'icon'  => 'Wrench',
        // ]);

        // ----------------------------
        // Catálogo (pai + filhos)
        // ----------------------------
        $catalogo = Menu::create([
            'title' => 'Catálogo',
            'url'   => null,
            'icon'  => 'Package',
            'order' => 4,
        ]);

        Menu::create([
            'title' => 'Produtos',
            'url'   => '/products',
            'parent_id' => $catalogo->id,
            'order' => 1,
        ]);

        // Menu::create([
        //     'title' => 'Serviços',
        //     'url'   => '/services',
        //     'parent_id' => $catalogo->id,
        // ]);

        Menu::create([
            'title' => 'Categorias',
            'url'   => '/categories',
            'parent_id' => $catalogo->id,
            'order' => 2,
        ]);

        // Pedidos
        $pedidos = Menu::create([
            'title' => 'Clube de Vantagens',
            'url'   => null,
            'icon'  => 'ClipboardList',
            'order' => 2,
        ]);

        Menu::create([
            'title' => 'Pedidos',
            'url'   => '/redemptions',
            'icon'  => 'CreditCard',
            'order' => 1,
            'parent_id' => $pedidos->id,
        ]);
        // Pontos
        Menu::create([
            'title' => 'Pontos',
            'url'   => '/points-extracts',
            'icon'  => 'CreditCard',
            'order' => 2,
            'parent_id' => $pedidos->id,
        ]);
        Menu::create([
            'title' => 'Relatório de Pontos',
            'url'   => '/points-reports',
            'icon'  => 'BarChart3',
            'order' => 3,
            'parent_id' => $pedidos->id,
        ]);


        // ----------------------------
        // Configurações (pai + filhos)
        // ----------------------------
        $configuracoes = Menu::create([
            'title' => 'Configurações',
            'url'   => null,
            'icon'  => 'Settings',
            'order' => 5,
        ]);

        Menu::create([
            'title' => 'Usuários',
            'url'   => '/settings/users',
            'parent_id' => $configuracoes->id,
            'order' => 1,
        ]);

        Menu::create([
            'title' => 'Perfis de Usuário',
            'url'   => '/settings/user-profiles',
            'parent_id' => $configuracoes->id,
            'order' => 2,
        ]);

        Menu::create([
            'title' => 'Permissões',
            'url'   => '/settings/permissions',
            'parent_id' => $configuracoes->id,
            'order' => 3,
        ]);

        // Menu::create([
        //     'title' => 'Status de OS',
        //     'url'   => '/settings/os-statuses',
        //     'parent_id' => $configuracoes->id,
        // ]);

        // Menu::create([
        //     'title' => 'Formas de Pagamento',
        //     'url'   => '/settings/payment-methods',
        //     'parent_id' => $configuracoes->id,
        // ]);

        // Menu::create([
        //     'title' => 'Metricas',
        //     'url'   => '/settings/metrics',
        //     'parent_id' => $configuracoes->id,
        // ]);
        Menu::create([
            'title' => 'Sistema',
            'url'   => '/settings/system',
            'parent_id' => $configuracoes->id,
            'order' => 4,
        ]);

        //Cadastrar as permissões iniciais

        DB::table('permissions')->insert([
            // MASTER → acesso a tudo
            [
                'name' => 'Master',
                'description' => 'Desenvolvedores',
                'redirect_login' => '/',
                'active' => 's',
                'excluido' => 'n',
                'deletado' => 'n',
            ],

            // ADMINISTRADOR → tudo, mas em configurações só "Usuários" e "Perfis"
            [
                'name' => 'Administrador',
                'description' => 'Administradores do sistema',
                'redirect_login' => '/',
                'active' => 's',
                'excluido' => 'n',
                'deletado' => 'n',
            ],

            // GERENTE → todos os menus exceto configurações
            [
                'name' => 'Gerente',
                'description' => 'Gerente do sistema (sem acesso a configurações)',
                'redirect_login' => '/',
                'active' => 's',
                'excluido' => 'n',
                'deletado' => 'n',
            ],

            // ESCRITÓRIO → somente dois primeiros menus
            [
                'name' => 'Escritorio',
                'description' => 'Usuários do escritório',
                'redirect_login' => '/',
                'active' => 's',
                'excluido' => 'n',
                'deletado' => 'n',
            ],
            // ESCRITÓRIO → somente dois primeiros menus
            [
                'name' => 'Parceiros',
                'description' => 'Empresas parceiras',
                'redirect_login' => '/',
                'active' => 's',
                'excluido' => 'n',
                'deletado' => 'n',
            ],
            // Cliente → para clientes sem acesso ao admin
            [
                'name' => 'Cliente',
                'description' => 'Acesso limitado a Dashboard e Clientes',
                'redirect_login' => '/',
                'active' => 's',
                'excluido' => 'n',
                'deletado' => 'n',
            ],
        ]);


        //Registrar permissões
        $menus = Menu::all();
        $groups =  Permission::all(); // grupos de usuário do sistema
        foreach ($menus as $menu) {
            foreach ($groups as $group) {
                // $keyBase = $this->generateKey($menu);
                if($group->id==1){
                    DB::table('menu_permission')->insert([
                        'menu_id'       => $menu->id,
                        'permission_id' => $group->id,
                        // 'permission_key'=> $keyBase . '.view',
                        'can_view'      => true,   // por padrão todos os grupos podem visualizar
                        'can_create'    => true,
                        'can_edit'      => true,
                        'can_delete'    => true,
                        'can_upload'    => true,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }else{
                    DB::table('menu_permission')->insert([
                        'menu_id'       => $menu->id,
                        'permission_id' => $group->id,
                        // 'permission_key'=> $keyBase . '.view',
                        'can_view'      => false,   // por padrão todos os grupos podem visualizar
                        'can_create'    => false,
                        'can_edit'      => false,
                        'can_delete'    => false,
                        'can_upload'    => false,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }
        }

    }

    /**
     * Limpa as tabelas do seeder e reinicia os contadores de auto incremento.
     */
    private function resetSeedTables(): void
    {
        DB::table('menu_permission')->delete();
        DB::table('menus')->delete();
        DB::table('permissions')->delete();

        DB::statement('ALTER TABLE menu_permission AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE menus AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE permissions AUTO_INCREMENT = 1');
    }
}
