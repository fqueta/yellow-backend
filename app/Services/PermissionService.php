<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\MenuPermission;
use App\Models\User;

class PermissionService
{
    /**
     * Verifica se um usuário (via grupos) tem permissão para ação em uma chave.
     */
    public function can(User $user, string $routeName, string $action = 'view'): bool
    {
        // pega todos os grupos que o usuário pertence
        $groupIds = isset($user['permission_id']) ? $user['permission_id'] : 0;
        $campo = 'can_' . $action; // can_view, can_create, can_edit, can_delete, can_upload
        // se no seu caso for hasOne ou belongsTo, só trocar.
        $get_id_menu_by_url = $this->get_id_menu_by_url($routeName);
        $perm = MenuPermission::where('permission_id', $groupIds)
                ->where('menu_id', $get_id_menu_by_url)
                //   ->where($campo,1)
                ->first();
                // dd($campo,$get_id_menu_by_url,$perm);
        if (!$perm) {
            return false;
        }
        // dd($perm[$campo]);
        if(isset($perm[$campo]) && $perm[$campo]>0){
            return true;
        }else{
            return false;
        }
    }
    /**
     * metodo para loca
     */
    public function get_id_menu_by_url($rm){
        $url = $this->get_url_by_route($rm);
        $menu_exist = Menu::where('url',$url)->first();
        // dd($menu_exist,$rm);
        if($menu_exist){
            return $menu_exist->id;
        }else{
            return 0;
        }
        // return Menu::where('url',$url)->first()->id;
    }
    /**
     * Metodo para veriricar se o usuario tem permissão para executar ao acessar esse recurso atraves de ''
     * @params string 'view | create | edit | delete'
     */
    public function isHasPermission($permissao=''){
        $user = request()->user();
        $routeName = request()->route()->getName();
        if ($this->can($user, $routeName, $permissao)) {
            return true;
        }else{
            return false;
        }
    }
    private function get_url_by_route($name=''){
        $url = '';
        if($name=='api.dashboard'){
            $url = '/';
        }
        // dd($name);
        if($name=='api.permissions.index' || $name == 'api.permissions.update' || $name == 'api.permissions.show' || $name == 'api.permissions.store' || $name == 'api.permissions.destroy'){
            $url = '/settings/permissions';
        }
        if($name=='api.users.index' || $name == 'api.users.update' || $name == 'api.users.show' || $name == 'api.users.store' || $name == 'api.users.destroy'){
            $url = '/settings/users';
        }
        if($name=='api.metrics.index' || $name == 'api.metrics.update' || $name == 'api.metrics.show' || $name == 'api.metrics.store' || $name == 'api.metrics.destroy'){
            $url = '/settings/metrics';
        }
        // dd($name);
        if($name=='api.clients.index' || $name == 'api.clients.update' || $name == 'api.clients.show' || $name == 'api.clients.store' || $name == 'api.clients.destroy' || $name == 'api.clients.restore' || $name == 'api.clients.forceDelete' || $name == 'api.clients.pre_registred' || $name == 'api.clients.update_pre_registred' || $name == 'api.clients.create'){
            $url = '/clients';
        }

        if($name=='api.clients.inactivate' || $name == 'api.clients.index_pre_registred'){
            $url = '/clients';
        }
        if($name=='api.partners.index' || $name == 'api.partners.update' || $name == 'api.partners.show' || $name == 'api.partners.store' || $name == 'api.partners.destroy' || $name == 'api.partners.restore' || $name == 'api.partners.forceDelete' || $name == 'api.partners.trash'){
            $url = '/partners';
        }
        if($name=='api.financial.index' || $name == 'api.financial.update' || $name == 'api.financial.show' || $name == 'api.financial.store' || $name == 'api.financial.destroy' || $name == 'api.financial.restore' || $name == 'api.financial.forceDelete' || $name == 'api.financial.trash' || $name == 'api.financial.markAsPaid' || $name == 'api.financial.summary'){
            $url = '/financial';
        }
        if($name=='api.points.index' || $name == 'api.points.update' || $name == 'api.points.show' || $name == 'api.points.store' || $name == 'api.points.destroy' || $name == 'api.points.restore' || $name == 'api.points.forceDelete' || $name == 'api.points.trash' || $name == 'api.points.saldoCliente' || $name == 'api.points.relatorio' || $name == 'api.points.expirarPontos'){
            $url = '/points';
        }
        if($name == 'api.admin.users.points-balance' || $name == 'api.admin.points-extracts' || $name == 'api.admin.points-extracts.show' || $name == 'api.admin.users.points-extracts'){
            $url = '/points-extracts';
        }
        if($name=='api.options.index' || $name == 'api.options.update' || $name == 'api.options.show' || $name == 'api.options.store' || $name == 'api.options.destroy' || $name == 'api.options.restore' || $name == 'api.options.forceDelete' || $name == 'api.options.trash'){
            $url = '/options';
        }
        if($name=='api.posts.index' || $name == 'api.posts.update' || $name == 'api.posts.show' || $name == 'api.posts.store' || $name == 'api.posts.destroy' || $name == 'api.posts.restore' || $name == 'api.posts.forceDelete' || $name == 'api.posts.trash'){
            $url = '/posts';
        }
        if($name=='api.aircraft.index' || $name == 'api.aircraft.update' || $name == 'api.aircraft.show' || $name == 'api.aircraft.store' || $name == 'api.aircraft.destroy' || $name == 'api.aircraft.restore' || $name == 'api.aircraft.forceDelete' || $name == 'api.aircraft.trash'){
            $url = '/aircraft';
        }
        if($name=='api.categories.index' || $name == 'api.categories.update' || $name == 'api.categories.show' || $name == 'api.categories.store' || $name == 'api.categories.destroy' || $name == 'api.categories.restore' || $name == 'api.categories.forceDelete' || $name == 'api.categories.trash' || $name == 'api.categories.tree'){
            $url = '/categories';
        }
        if($name=='api.product-categories'){
            $url = '/categories';
        }
        if($name=='api.service-categories'){
            $url = '/categories';
        }
        if($name=='api.product-units.index' || $name == 'api.product-units.update' || $name == 'api.product-units.show' || $name == 'api.product-units.store' || $name == 'api.product-units.destroy' || $name == 'api.product-units.restore' || $name == 'api.product-units.forceDelete' || $name == 'api.product-units.trash'){
            $url = '/products';
        }
        if($name=='api.products.index' || $name == 'api.products.user-redemptions' || $name == 'api.admin.redemptions' || $name == 'api.admin.redemptions.update-status' || $name == 'api.products.update' || $name == 'api.products.show' || $name == 'api.products.store' || $name == 'api.products.destroy' || $name == 'api.products.restore' || $name == 'api.products.forceDelete' || $name == 'api.products.trash'){
            $url = '/products';
        }
        if($name=='api.services.index' || $name == 'api.services.update' || $name == 'api.services.show' || $name == 'api.services.store' || $name == 'api.services.destroy' || $name == 'api.services.restore' || $name == 'api.services.forceDelete' || $name == 'api.services.trash'){
            $url = '/services';
        }
        if($name=='api.service-units.index' || $name == 'api.service-units.update' || $name == 'api.service-units.show' || $name == 'api.service-units.store' || $name == 'api.service-units.destroy' || $name == 'api.service-units.restore' || $name == 'api.service-units.forceDelete' || $name == 'api.service-units.trash'){
            $url = '/services';
        }
        if($name=='api.service-orders.index' || $name == 'api.service-orders.update' || $name == 'api.service-orders.show' || $name == 'api.service-orders.store' || $name == 'api.service-orders.destroy' || $name == 'api.service-orders.restore' || $name == 'api.service-orders.forceDelete' || $name == 'api.service-orders.trash' || $name == 'api.service-orders.update-status'){
            $url = '/service-orders';
        }
        if($name=='api.dashboard-metrics.index' || $name == 'api.dashboard-metrics.update' || $name == 'api.dashboard-metrics.show' || $name == 'api.dashboard-metrics.store' || $name == 'api.dashboard-metrics.destroy' || $name == 'api.dashboard-metrics.import-aeroclube'){
            $url = '/settings/metrics';
        }
        if($name=='api.options.index' || $name == 'api.options.update' || $name == 'api.options.show' || $name == 'api.options.store' || $name == 'api.options.destroy' || $name == 'api.options.all.get' || $name == 'api.options.all'){
            $url = '/settings/system';
        }
        if($name=='api.point-store.redemptions.show' || $name == 'api.admin.redemptions.refund'){
            $url = '/redemptions';
        }
        // dd($name,$url);
        return $url;

    }
}
