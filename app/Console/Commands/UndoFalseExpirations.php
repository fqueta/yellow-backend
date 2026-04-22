<?php

namespace App\Console\Commands;

use App\Models\Point;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UndoFalseExpirations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'points:undo-false-expiration {--dry-run : Apenas simula as alterações sem salvar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige créditos marcados como "expirado" que já estavam totalmente usados (saldo zero) no momento da expiração.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->warn('MODO SIMULAÇÃO ATIVADO - Nenhuma alteração será salva.');
        }

        if (class_exists(Tenant::class)) {
            $tenants = Tenant::all();
            foreach ($tenants as $tenant) {
                $this->info("Processando Tenant: {$tenant->id}...");
                $tenant->run(function () use ($dryRun) {
                    $this->fixExpirations($dryRun);
                });
            }
        } else {
            $this->fixExpirations($dryRun);
        }

        $this->info('Processo concluído!');
    }

    private function fixExpirations($dryRun)
    {
        // 1. Buscar todos os créditos que estão com status 'expirado'
        $creditosExpirados = Point::where('tipo', 'credito')
            ->where('status', 'expirado')
            ->where('excluido', 'n')
            ->where('deletado', 'n')
            ->get();

        $totalCorrigidos = 0;
        $totalIgnorados = 0;

        foreach ($creditosExpirados as $ponto) {
            // 2. Verificar se existe um débito de expiração vinculado a este crédito
            // O comando anterior salvava o ID do crédito no config['referencia_credito_id']
            $temDebitoExpiracao = Point::where('tipo', 'expired')
                ->where('origem', 'expiracao')
                ->where(function($query) use ($ponto) {
                    $query->where('config->referencia_credito_id', $ponto->id)
                          ->orWhere('description', 'like', "%#{$ponto->id}%");
                })
                ->exists();

            if (!$temDebitoExpiracao) {
                // Se não tem débito de expiração, significa que o saldo era zero (ou algo deu errado)
                // e ele foi marcado como 'expirado' indevidamente.
                $totalCorrigidos++;
                
                if (!$dryRun) {
                    $ponto->update(['status' => 'ativo']);
                    $this->line("  [FIX] Crédito #{$ponto->id} restaurado para 'ativo'.");
                } else {
                    $this->line("  [SIMULAÇÃO] Crédito #{$ponto->id} seria restaurado para 'ativo'.");
                }
            } else {
                $totalIgnorados++;
            }
        }

        $this->info("  Resultado: {$totalCorrigidos} corrigidos, {$totalIgnorados} mantidos (expirações legítimas).");
    }
}
