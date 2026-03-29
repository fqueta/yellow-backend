<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;
use App\Models\Point;
use App\Models\User;
use Carbon\Carbon;

$tenant = Tenant::find('api-mileto');
if ($tenant) {
    tenancy()->initialize($tenant);
    
    $u = User::where('name', 'Sergio Caldas Amado')->first();
    if ($u) {
        $p = Point::where('client_id', $u->id)
            ->where('tipo', 'credito')
            ->ativos()
            ->where('status', '!=', 'expirado')
            ->whereRaw('valor > IFNULL(valor_usado, 0)')
            ->first();

        if ($p) {
            $p->data_expiracao = Carbon::now()->addDays(15);
            $p->save();
            echo "TEST_OK: Ponto ID {$p->id} do usuario {$u->name} (Saldo: " . ($p->valor - $p->valor_usado) . ") atualizado para expirar em 15 dias.";
        } else {
            // Se nao tiver saldo, vamos resetar o uso de um ponto para teste
            $p = Point::where('client_id', $u->id)
                ->where('tipo', 'credito')
                ->ativos()
                ->first();
                
            if($p) {
                $p->valor_usado = 0;
                $p->status = 'ativo';
                $p->data_expiracao = Carbon::now()->addDays(15);
                $p->save();
                echo "TEST_OK_FORCED: Ponto ID {$p->id} do usuario {$u->name} foi resetado e configurado para expirar em 15 dias.";
            } else {
                echo "TEST_ERR: Nenhum ponto de credito encontrado para {$u->name}.";
            }
        }
    } else {
        echo "TEST_ERR: Usuario Sergio nao encontrado.";
    }
}
