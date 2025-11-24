<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\ClientController;
use App\Models\Client;
use App\Models\User;
use App\Services\Qlib;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Exibir dados do dashboard.
     * - Permissões: quando `permission_id >= 5` (parceiro), filtra por `autor = user_id`.
     * - Admins (`permission_id < 5`) veem dados sem filtro por autor.
     * - Filtros aceitos: `limit`, `start_date`, `end_date`.
     *   - `limit`: número de itens nas listas (ex.: atividades recentes), padrão 10.
     *   - `start_date`/`end_date` (YYYY-MM-DD): quando fornecidos, filtra por período.
     */
    public function index(Request $request)
    {
        if (!$this->isHasPermission('view', $request)) {
            return response()->json([
                'success' => false,
                'message' => __('Você não tem permissão para visualizar este conteúdo!')
            ], 403);
        }

        $user = $request->user();
        $partnerPermissionId = Qlib::qoption('permission_partner_id') ?? 5;
        $authorId = ($user && (int)($user->permission_id) >= (int)$partnerPermissionId) ? $user->id : null;
        $period = $request->input('period', 14);
        $limit = (int) $request->input('limit', 10);
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Normalizar datas quando fornecidas
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : null;
        $end   = $endDate   ? Carbon::parse($endDate)->endOfDay()   : null;

        // Atividades recentes com suporte a limite e período
        $recentActivities = $this->getRecentClientActivities($authorId, $limit, $start, $end);
        // dd($authorId);
        // dd($user->toArray());
        // Dados de cadastro: usa período em dias ou intervalo de datas
        if ($start && $end) {
            $registrationData = $this->getClientRegistrationDataByRange($authorId, $start, $end);
        } else {
            $registrationData = $this->getClientRegistrationData($authorId, $period);
        }
        // Pré-cadastros pendentes com suporte a limite e período
        $pendingPreRegistrations = $this->getPendingPreRegistrations($authorId, $limit, $start, $end);
        $totals = $this->getDashboardTotals($authorId);
        // dd($pendingPreRegistrations);
        return response()->json([
            'success' => true,
            'data' => [
                'recent_activities' => $recentActivities,
                'registration_data' => $registrationData,
                'pending_pre_registrations' => $pendingPreRegistrations,
                'totals' => $totals,
                'filters' => [
                    'limit' => $limit,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ],
        ]);
    }

    /**
     * Atividades recentes dos clientes
     * @param int|string|null $authorId Filtra por autor (pode ser int ou string)
     * @return array
     */
    /**
     * Atividades recentes dos clientes com suporte a limite e período.
     *
     * @param int|string|null $authorId Filtra por autor (pode ser int ou string)
     * @param int $limit Quantidade máxima de itens
     * @param Carbon|null $start Data inicial do período
     * @param Carbon|null $end Data final do período
     * @return array
     */
    private function getRecentClientActivities(int|string|null $authorId = null, int $limit = 10, ?Carbon $start = null, ?Carbon $end = null): array
    {
        // Se período for fornecido, aplica faixa de datas; caso contrário, usa últimos N dias
        if ($start && $end) {
            $query = Client::select('id', 'name', 'email', 'status', 'created_at', 'updated_at')
                ->whereBetween('created_at', [$start, $end])
                ->where('excluido', 'n')
                ->orderBy('created_at', 'desc');

            if ($authorId) {
                $query->where('autor', $authorId);
            }

            return $query->limit($limit)->get()->map(function ($client) {
                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'status' => $client->status,
                    'type' => 'cadastro',
                    'title' => 'Novo cadastro de cliente',
                    'created_at' => $client->created_at->format('d/m/Y H:i'),
                ];
            })->toArray();
        }

        // Sem período explícito: usa helper existente com 20 dias padrão
        return Client::getRecentActivities(20, $limit, $authorId);
    }

    /**
     * Dados de cadastro por período (últimos 14 dias)
     * @param int|string|null $authorId Filtra por autor (pode ser int ou string)
     * @return array
     */
    private function getClientRegistrationData(int|string|null $authorId = null, ?int $period = null): array
    {
        //adicionar opção para personalizar o período
        $period = $period ?? 14;
        // dd($authorId);
        // Usar ClientController para obter os dados por período
        $clientController = new ClientController();
        return $clientController->getRegistrationDataByPeriod($period, $authorId);
    }

    /**
     * Pré-cadastros pendentes
     * @param int|string|null $authorId Filtra por autor (pode ser int ou string)
     * @return array
     */
    /**
     * Pré-cadastros pendentes com suporte a limite e período.
     *
     * @param int|string|null $authorId Filtra por autor (pode ser int ou string)
     * @param int $limit Quantidade máxima de itens
     * @param Carbon|null $start Data inicial do período
     * @param Carbon|null $end Data final do período
     * @return array
     */
    private function getPendingPreRegistrations(int|string|null $authorId = null, int $limit = 10, ?Carbon $start = null, ?Carbon $end = null): array
    {
        $query = Client::select('id', 'name', 'email', 'created_at')
            ->where('status', 'pre_registred')
            ->where('excluido', 'n')
            ->orderBy('created_at', 'desc');

        if ($authorId) {
            $query->where('autor', $authorId);
        }
        if ($start && $end) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        return $query->limit($limit)->get()->map(function ($client) {
            return [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'date' => $client->created_at->format('d/m/Y H:i'),
            ];
        })->toArray();
    }

    /**
     * Dados de cadastro por intervalo de datas.
     * Gera contagens diárias para `actived`, `inactived`, `pre_registred` entre `start` e `end` (inclusive).
     *
     * @param int|string|null $authorId Filtra por autor (pode ser int ou string)
     * @param Carbon $start Data inicial
     * @param Carbon $end Data final
     * @return array
     */
    private function getClientRegistrationDataByRange(int|string|null $authorId, Carbon $start, Carbon $end): array
    {
        $data = [];
        $cursor = $start->copy()->startOfDay();
        $finish = $end->copy()->endOfDay();

        while ($cursor->lte($finish)) {
            $dateStr = $cursor->format('Y-m-d');
            $data[] = [
                'date' => $dateStr,
                'actived' => Client::whereDate('created_at', $dateStr)
                    ->where('status', 'actived')
                    ->where('excluido', 'n')
                    ->when($authorId, function ($q) use ($authorId) { $q->where('autor', $authorId); })
                    ->count(),
                'inactived' => Client::whereDate('created_at', $dateStr)
                    ->where('status', 'inactived')
                    ->where('excluido', 'n')
                    ->when($authorId, function ($q) use ($authorId) { $q->where('autor', $authorId); })
                    ->count(),
                'pre_registred' => Client::whereDate('created_at', $dateStr)
                    ->where('status', 'pre_registred')
                    ->where('excluido', 'n')
                    ->when($authorId, function ($q) use ($authorId) { $q->where('autor', $authorId); })
                    ->count(),
            ];

            $cursor->addDay()->startOfDay();
        }

        return $data;
    }

    /**
     * Totais dos cards do dashboard
     * @param int|string|null $authorId Filtra por autor (pode ser int ou string)
     * @return array
     */
    private function getDashboardTotals(int|string|null $authorId = null): array
    {
        return Client::getDashboardTotals($authorId);
    }

    /**
     * Verifica se usuário tem permissão para determinada ação.
     * Caso o usuário esteja inativo, o token é revogado no PermissionService.
     */
    private function isHasPermission(string $acao = 'view', Request $request): bool
    {
        $PermissionService = new \App\Services\PermissionService();
        return $PermissionService->isHasPermission($acao, $request);
    }
}
