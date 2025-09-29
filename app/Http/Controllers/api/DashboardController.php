<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\PermissionService;
use App\Services\Qlib;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    protected PermissionService $permissionService;
    public $routeName;
    public $sec;
    public $cliente_permission_id;
    public function __construct()
    {
        $this->cliente_permission_id = Qlib::qoption('cliente_permission_id')??6;
        $this->routeName = request()->route()->getName();
        $this->permissionService = new PermissionService();
        $this->sec = request()->segment(3);
    }
    /**
     * Retorna dados do dashboard incluindo atividades recentes,
     * dados de cadastros dos últimos 14 dias e pré-cadastros pendentes
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('view')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        try {
            // Buscar atividades recentes de clientes (últimos 30 dias)
            $recentClientActivities = $this->getRecentClientActivities();

            // Buscar dados de cadastros dos últimos 14 dias
            $clientRegistrationData = $this->getClientRegistrationData();

            // Buscar pré-cadastros pendentes
            $pendingPreRegistrations = $this->getPendingPreRegistrations();

            // Buscar totais para cards do dashboard
            $totals = $this->getDashboardTotals();

            return response()->json([
                'success' => true,
                'data' => [
                    'recentClientActivities' => $recentClientActivities,
                    'clientRegistrationData' => $clientRegistrationData,
                    'pendingPreRegistrations' => $pendingPreRegistrations,
                    'totals' => $totals
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar dados do dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca atividades recentes de clientes (últimos 30 dias)
     */
    private function getRecentClientActivities()
    {
        // O método Client::getRecentActivities já retorna um array formatado
        return Client::getRecentActivities(20);
    }

    /**
     * Busca dados de cadastros dos últimos 14 dias agrupados por data
     */
    private function getClientRegistrationData()
    {
        // Usar método do modelo Client
        return Client::getRegistrationDataByPeriod(14);
    }

    /**
     * Busca pré-cadastros pendentes
     */
    private function getPendingPreRegistrations()
    {
        $pending = Client::select('id', 'name', 'email', 'created_at')
            ->where('status', 'pre_registred')
            ->where('excluido', 'n')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return $pending->map(function ($client) {
            return [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => '(XX) XXXXX-XXXX', // Campo não existe na tabela, usar valor padrão
                'date' => $client->created_at->format('Y-m-d'),
                'type' => 'Pessoa Física', // Padrão já que não temos coluna tipo_pessoa
                'cpf' => '000.000.000-00', // Padrão já que não temos coluna cpf
                'cnpj' => '' // Padrão já que não temos coluna cnpj
            ];
        })->toArray();
    }

    /**
     * Busca totais para cards do dashboard
     */
    private function getDashboardTotals()
    {
        // Usar método estático do modelo Client
        return Client::getDashboardTotals();
    }


}
