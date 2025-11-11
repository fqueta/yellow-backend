<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\ClientController;
use App\Models\Client;
use App\Models\User;
use App\Services\Qlib;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Exibir dados do dashboard
     * - Quando `permission_id >= 5` (parceiro), filtra por `autor = user_id`.
     * - Administradores (permission_id < 5) veem dados sem filtro de autor.
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
        $recentActivities = $this->getRecentClientActivities($authorId);
        // dd($authorId);
        // dd($user->toArray());
        $registrationData = $this->getClientRegistrationData($authorId, $period);
        $pendingPreRegistrations = $this->getPendingPreRegistrations($authorId);
        $totals = $this->getDashboardTotals($authorId);
        // dd($pendingPreRegistrations);
        return response()->json([
            'success' => true,
            'data' => [
                'recent_activities' => $recentActivities,
                'registration_data' => $registrationData,
                'pending_pre_registrations' => $pendingPreRegistrations,
                'totals' => $totals,
            ],
        ]);
    }

    /**
     * Atividades recentes dos clientes
     * @param int|string|null $authorId Filtra por autor (pode ser int ou string)
     * @return array
     */
    private function getRecentClientActivities(int|string|null $authorId = null): array
    {
        // No código original, o método do model recebe dias como primeiro parâmetro.
        return Client::getRecentActivities(20, 20, $authorId);
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
    private function getPendingPreRegistrations(int|string|null $authorId = null): array
    {
        $query = Client::select('id', 'name', 'email', 'created_at')
            ->where('status', 'pre_registred')
            ->where('excluido', 'n')
            ->orderBy('created_at', 'desc');

        if ($authorId) {
            $query->where('autor', $authorId);
        }
        // dd($query->toSql());
        // dd($query->get());
        return $query->limit(10)->get()->map(function ($client) {
            return [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'date' => $client->created_at->format('d/m/Y H:i'),
            ];
        })->toArray();
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
