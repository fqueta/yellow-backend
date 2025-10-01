<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use App\Services\Qlib;

class Client extends User
{
    protected $table = 'users';

    // Global scope para filtrar apenas clientes com permission_id=6
    protected static function booted()
    {
        static::addGlobalScope('clients_only', function (Builder $builder) {
            $builder->where('permission_id', Qlib::qoption('permission_client_id')??6);
        });
        
        // Definir permission_id automaticamente ao criar
        static::creating(function ($client) {
            if (empty($client->permission_id)) {
                $client->permission_id = Qlib::qoption('permission_client_id')??6;
            }
        });
        
        // Definir permission_id automaticamente ao atualizar
        static::updating(function ($client) {
            if (empty($client->permission_id)) {
                $client->permission_id = Qlib::qoption('permission_client_id')??6;
            }
        });
    }

    protected $fillable = [
        'tipo_pessoa',
        'name',
        'razao',
        'cpf',
        'cnpj',
        'email',
        'password',
        'status',
        'genero',
        'verificado',
        'permission_id',
        'config',
        'preferencias',
        'foto_perfil',
        'ativo',
        'autor',
        'token',
        'excluido',
        'reg_excluido',
        'deletado',
        'reg_deletado',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Scope para clientes ativos (todos os clientes já que não temos coluna status)
     */
    public function scopeActive($query)
    {
        return $query; // Retorna todos já que não temos coluna status
    }

    /**
     * Scope para clientes inativos (vazio já que não temos coluna status)
     */
    public function scopeInactive($query)
    {
        return $query->whereRaw('1 = 0'); // Retorna vazio já que não temos coluna status
    }

    /**
     * Scope para clientes pré-registrados (vazio já que não temos coluna status)
     */
    public function scopePreRegistered($query)
    {
        return $query->whereRaw('1 = 0'); // Retorna vazio já que não temos coluna status
    }

    /**
     * Scope para buscar clientes criados nos últimos N dias
     */
    public function scopeRecentDays($query, $days = 30)
    {
        // Subtrai (days - 1) para incluir o dia de hoje no período
        return $query->where('created_at', '>=', now()->subDays($days - 1)->startOfDay());
    }

    /**
     * Scope para buscar clientes criados em uma data específica
     */
    public function scopeCreatedOnDate($query, $date)
    {
        return $query->whereDate('created_at', $date);
    }

    /**
     * Accessor para tipo de pessoa formatado (padrão já que não temos essa coluna)
     */
    public function getTipoPessoaFormattedAttribute()
    {
        return 'Pessoa Física'; // Padrão já que não temos coluna tipo_pessoa
    }

    /**
     * Accessor para documento principal (padrão já que não temos CPF/CNPJ)
     */
    public function getPrimaryDocumentAttribute()
    {
        return '000.000.000-00'; // Padrão já que não temos colunas cpf/cnpj
    }

    /**
     * Buscar atividades recentes de clientes
     * @param int $days
     * @param int $limit
     * @return array
     */
    public static function getRecentActivities($days = 30, $limit = 20)
    {
        // Subtrai (days - 1) para incluir o dia de hoje no período
        $activities = static::select('id', 'name', 'email', 'status', 'created_at', 'updated_at')
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->where('excluido', 'n')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email, // Usando email no lugar de cpf
                    'status' => $client->status, // Padrão já que não temos coluna status
                    'type' => 'cadastro',
                    'title' => 'Novo cadastro de cliente',
                    'created_at' => $client->created_at->format('d/m/Y H:i'),
                ];
            })
            ->toArray();

        return $activities;
    }

    /**
     * Buscar dados de registro por período
     * @param int $days
     * @return array
     */
    public static function getRegistrationDataByPeriod($days = 14)
    {
        // Subtrai (days - 1) para incluir o dia de hoje no período
        $startDate = now()->subDays($days - 1);
        $data = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dateStr = $date->format('Y-m-d');
            // dump($dateStr);

            $data[] = [
                // 'date' => $date->format('d/m'),
                'date' => $dateStr,
                'actived' => static::whereDate('created_at', $dateStr)->where('status', 'actived')->where('excluido', 'n')->count(),
                'inactived' =>  static::whereDate('created_at', $dateStr)->where('status', 'inactived')->where('excluido', 'n')->count(), // Sempre 0 já que não temos coluna status
                'pre_registred' => static::whereDate('created_at', $dateStr)->where('status', 'pre_registred')->where('excluido', 'n')->count(),
            ];
        }

        return $data;
    }

    /**
     * Buscar totais do dashboard com variação percentual
     * @return array
     */
    public static function getDashboardTotals()
    {
        // Período atual (últimos 30 dias incluindo hoje)
        $currentPeriod = [
            'actived' => static::where('created_at', '>=', now()->subDays(29)->startOfDay())->where('status', 'actived')->where('excluido', 'n')->count(),
            'inactived' => static::where('created_at', '>=', now()->subDays(29)->startOfDay())->where('status', 'inactived')->where('excluido', 'n')->count(),
            'pre_registred' => static::where('created_at', '>=', now()->subDays(29)->startOfDay())->where('status', 'pre_registred')->where('excluido', 'n')->count(),
        ];

        // Período anterior (30 dias anteriores ao período atual)
        $previousPeriod = [
            'actived' => static::whereBetween('created_at', [
                now()->subDays(59)->startOfDay(),
                now()->subDays(30)->endOfDay()
            ])->where('status', 'actived')->where('excluido', 'n')->count(),
            'inactived' => static::whereBetween('created_at', [
                now()->subDays(59)->startOfDay(),
                now()->subDays(30)->endOfDay()
            ])->where('status', 'inactived')->where('excluido', 'n')->count(),
            'pre_registred' => static::whereBetween('created_at', [
                now()->subDays(59)->startOfDay(),
                now()->subDays(30)->endOfDay()
            ])->where('status', 'pre_registred')->where('excluido', 'n')->count(),
        ];

        // Calcular variação percentual
        $totalCurrent = array_sum($currentPeriod);
        $totalPrevious = array_sum($previousPeriod);

        $variationPercentage = $totalPrevious > 0
            ? round((($totalCurrent - $totalPrevious) / $totalPrevious) * 100, 1)
            : ($totalCurrent > 0 ? 100 : 0);

        return array_merge($currentPeriod, [
            'variation_percentage' => $variationPercentage
        ]);
    }
}
