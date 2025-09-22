<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\MenuPermission;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    public function run()
    {
        DB::table('menus')->delete(); //Menu::delete();
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

        // Menu::create([
        //     'title' => 'Categorias',
        //     'url'   => '/categories',
        //     'parent_id' => $catalogo->id,
        // ]);

        // Orçamentos
        // Menu::create([
        //     'title' => 'Orçamentos',
        //     'url'   => '/budgets',
        //     'icon'  => 'FileText',
        // ]);

        // Ordens de Serviço
        // Menu::create([
        //     'title' => 'Propostas',
        //     'url'   => '/service-orders',
        //     'icon'  => 'ClipboardList',
        // ]);

        // ----------------------------
        // Financeiro (pai + filhos)
        // ----------------------------
        // $financeiro = Menu::create([
        //     'title' => 'Financeiro',
        //     'url'   => null,
        //     'icon'  => 'DollarSign',
        // ]);

        // Menu::create([
        //     'title' => 'Pagamentos',
        //     'url'   => '/payments',
        //     'parent_id' => $financeiro->id,
        // ]);

        // Menu::create([
        //     'title' => 'Fluxo de Caixa',
        //     'url'   => '/cash-flow',
        //     'parent_id' => $financeiro->id,
        // ]);

        // Menu::create([
        //     'title' => 'Contas',
        //     'url'   => '/financial',
        //     'parent_id' => $financeiro->id,
        // ]);

        // Menu::create([
        //     'title' => 'Contas a Pagar',
        //     'url'   => '/financial/accounts-payable',
        //     'parent_id' => $financeiro->id,
        // ]);

        // Menu::create([
        //     'title' => 'Categorias',
        //     'url'   => '/financial/categories',
        //     'parent_id' => $financeiro->id,
        // ]);
        // ----------------------------
        // Relatórios (pai + filhos)
        // ----------------------------
        // $relatorios = Menu::create([
        //     'title' => 'Relatórios',
        //     'url'   => null,
        //     'icon'  => 'BarChart3',
        // ]);

        // Menu::create([
        //     'title' => 'Faturamento',
        //     'url'   => '/reports/revenue',
        //     'parent_id' => $relatorios->id,
        // ]);

        // Menu::create([
        //     'title' => 'OS por Período',
        //     'url'   => '/reports/service-orders',
        //     'parent_id' => $relatorios->id,
        // ]);

        // Menu::create([
        //     'title' => 'Produtos Mais Vendidos',
        //     'url'   => '/reports/top-products',
        //     'parent_id' => $relatorios->id,
        // ]);

        // Menu::create([
        //     'title' => 'Análise Financeira',
        //     'url'   => '/reports/financial',
        //     'parent_id' => $relatorios->id,
        // ]);

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

        DB::table('permissions')->delete();

        DB::table('permissions')->insert([
            // MASTER → acesso a tudo
            [
                'name' => 'Master',
                'description' => 'Desenvolvedores',
                'redirect_login' => '/',
                'active' => 's',
            ],

            // ADMINISTRADOR → tudo, mas em configurações só "Usuários" e "Perfis"
            [
                'name' => 'Administrador',
                'description' => 'Administradores do sistema',
                'redirect_login' => '/',
                'active' => 's'
            ],

            // GERENTE → todos os menus exceto configurações
            [
                'name' => 'Gerente',
                'description' => 'Gerente do sistema (sem acesso a configurações)',
                'redirect_login' => '/',
                'active' => 's'
            ],

            // ESCRITÓRIO → somente dois primeiros menus
            [
                'name' => 'Escritorio',
                'description' => 'Usuários do escritório',
                'redirect_login' => '/',
                'active' => 's'
            ],
            // ESCRITÓRIO → somente dois primeiros menus
            [
                'name' => 'Parceiros',
                'description' => 'Empresas parceiras',
                'redirect_login' => '/',
                'active' => 's'
            ],
            // Cliente → para clientes sem acesso ao admin
            [
                'name' => 'Cliente',
                'description' => 'Acesso limitado a Dashboard e Clientes',
                'redirect_login' => '/',
                'active' => 's'
            ],
        ]);


        //Registrar permissões
        DB::table('menu_permission')->delete(); //MenuPermission::delete();
        $menus = Menu::all();
        $groups =  Permission::all(); // grupos de usuário do sistema
        DB::table('menu_permission')->delete();
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
}
