<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Point;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/**
 * Controller para gerenciar pontos dos clientes
 * Sistema de pontuação/recompensas
 */
class PointController extends Controller
{
    protected $permissionService;

    public function __construct()
    {
        $this->permissionService = new PermissionService;
    }

    /**
     * Sanitizar dados de entrada
     */
    private function sanitizeInput($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($value)) {
                    $data[$key] = strip_tags(trim($value));
                } elseif (is_array($value)) {
                    $data[$key] = $this->sanitizeInput($value);
                }
            }
        }
        return $data;
    }

    /**
     * Regras de validação para pontos
     */
    private function getValidationRules($isUpdate = false)
    {
        $required = $isUpdate ? 'sometimes|required' : 'required';

        return [
            'client_id' => $required . '|string',
            'valor' => $required . '|numeric|min:0.01',
            'data' => $required . '|date',
            'description' => $required . '|string|max:1000',
            'tipo' => 'nullable|in:credito,debito',
            'origem' => 'nullable|string|max:255',
            'valor_referencia' => 'nullable|numeric|min:0',
            'data_expiracao' => 'nullable|date|after:data',
            'status' => 'nullable|in:ativo,expirado,usado,cancelado',
            'usuario_id' => 'nullable|string',
            'pedido_id' => 'nullable|string|max:255',
            'config' => 'nullable|array',
        ];
    }

    /**
     * Mensagens de validação personalizadas
     */
    private function getValidationMessages()
    {
        return [
            'client_id.required' => 'O campo cliente é obrigatório',
            'valor.required' => 'O campo valor é obrigatório',
            'valor.min' => 'O valor deve ser maior que zero',
            'data.required' => 'A data é obrigatória',
            'data.date' => 'A data deve ser uma data válida',
            'description.required' => 'A descrição é obrigatória',
            'description.max' => 'A descrição não pode ter mais de 1000 caracteres',
            'tipo.in' => 'O tipo deve ser "credito" ou "debito"',
            'data_expiracao.after' => 'A data de expiração deve ser posterior à data da movimentação',
        ];
    }

    /**
     * Validar dados de entrada
     */
    private function validatePointData($data, $isUpdate = false)
    {
        $validator = Validator::make(
            $data,
            $this->getValidationRules($isUpdate),
            $this->getValidationMessages()
        );

        if ($validator->fails()) {
            return [
                'success' => false,
                'errors' => $validator->errors()
            ];
        }

        return [
            'success' => true,
            'data' => $validator->validated()
        ];
    }

    /**
     * Processar dados validados para criação/atualização
     */
    private function processPointData($validated, $user, $isUpdate = false)
    {
        // Definir valores padrão apenas para criação
        if (!$isUpdate) {
            $validated['tipo'] = $validated['tipo'] ?? 'credito';
            $validated['status'] = $validated['status'] ?? 'ativo';
            $validated['autor'] = $user->id;
            $validated['usuario_id'] = $validated['usuario_id'] ?? $user->id;
            $validated['ativo'] = 's';
            $validated['excluido'] = 'n';
            $validated['deletado'] = 'n';
        }

        // Tratar config se fornecido
        if (isset($validated['config']) && is_array($validated['config'])) {
            $validated['config'] = json_encode($validated['config']);
        }

        return $validated;
    }

    /**
     * Preparar resposta do ponto com relacionamentos
     */
    private function preparePointResponse($point, $message, $statusCode = 200)
    {
        // Converter config para array na resposta
        if (is_string($point->config)) {
            $point->config = json_decode($point->config, true) ?? [];
        }

        $point->load(['cliente', 'usuario']);

        $response = [
            'data' => $point,
            'message' => $message,
            'status' => $statusCode
        ];

        return response()->json($response, $statusCode);
    }

    /**
     * Verificar permissões do usuário
     */
    private function checkUserPermission($permission)
    {
        $user = request()->user();

        if (!$user) {
            return [
                'success' => false,
                'response' => response()->json(['error' => 'Acesso negado'], 403)
            ];
        }

        if (!$this->permissionService->isHasPermission($permission)) {
            return [
                'success' => false,
                'response' => response()->json(['error' => 'Acesso negado'], 403)
            ];
        }

        return [
            'success' => true,
            'user' => $user
        ];
    }

    /**
     * Listar todos os pontos
     */
    public function index(Request $request)
    {
        $permissionCheck = $this->checkUserPermission('view');
        if (!$permissionCheck['success']) {
            return $permissionCheck['response'];
        }

        $query = Point::with(['cliente']);

        // Filtros
        if ($request->has('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->has('tipo') && in_array($request->tipo, ['credito', 'debito'])) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->has('status') && in_array($request->status, ['ativo', 'expirado', 'usado', 'cancelado'])) {
            $query->where('status', $request->status);
        }

        if ($request->has('origem')) {
            $query->where('origem', 'like', "%{$request->origem}%");
        }

        if ($request->has('data_inicio') && $request->has('data_fim')) {
            $query->porPeriodo($request->data_inicio, $request->data_fim);
        }

        if ($request->has('expirando') && $request->expirando == 'true') {
            $dias = $request->get('dias_expiracao', 30);
            $query->vencendoEm($dias);
        }

        if ($request->has('expirados') && $request->expirados == 'true') {
            $query->expirados();
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('origem', 'like', "%{$search}%")
                  ->orWhere('pedido_id', 'like', "%{$search}%")
                  ->orWhereHas('cliente', function($clienteQuery) use ($search) {
                      $clienteQuery->where('name', 'like', "%{$search}%")
                                  ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Ordenação
        $orderBy = $request->get('order_by', 'data');
        $orderDirection = $request->get('order_direction', 'desc');
        $query->orderBy($orderBy, $orderDirection);

        // Paginação
        $perPage = $request->get('per_page', 15);
        $points = $query->paginate($perPage);

        // Converter config para array
        $points->getCollection()->transform(function ($point) {
            if (is_string($point->config)) {
                $point->config = json_decode($point->config, true) ?? [];
            }
            return $point;
        });

        return response()->json($points);
    }

    /**
     * Criar nova movimentação de pontos
     */
    public function store(Request $request)
    {
        // Verificar permissões
        $permissionCheck = $this->checkUserPermission('create');
        if (!$permissionCheck['success']) {
            return $permissionCheck['response'];
        }
        $user = $permissionCheck['user'];

        // Sanitizar e validar dados
        $data = $this->sanitizeInput($request->all());
        $validation = $this->validatePointData($data);

        if (!$validation['success']) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validation['errors'],
            ], 422);
        }

        // Processar dados e criar ponto
        $processedData = $this->processPointData($validation['data'], $user);
        $point = Point::create($processedData);

        return $this->preparePointResponse(
            $point,
            'Movimentação de pontos criada com sucesso',
            201
        );
    }
    /**
     * Para cadastrar ou atualizar registros de pontos
     * Se $id for fornecido, atualiza o registro existente, caso contrário, cria um novo
     * @param int $cliente_id ID do cliente associado ao ponto
     * @param array $data Dados para criação ou atualização do ponto
     * @param int|false $id ID do ponto a ser atualizado, ou false para criação
     * @return Point O ponto recém-criado ou atualizado
     */
    public function createOrUpdate($data,$id=false){
        if($id){
            $point = Point::findOrFail($id);
        }else{
            $point = new Point();
        }
        $data['client_id'] = $data['client_id'] ?? '';
        $data['valor'] = $data['valor'] ?? 0;
        $data['data'] = $data['data'] ?? date('Y-m-d H:i:s');
        $data['tipo'] = $data['tipo'] ?? 'credito';
        $data['status'] = $data['status'] ?? 'ativo';
        // if($data['tipo'] == 'debito'){
        //     $data['valor'] = ($data['valor'])*-1;
        // }
        // dd($data);
        if($data['client_id'] == ''){
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => ['client_id' => 'O client_id é obrigatório'],
            ], 422);
        }
        $client = User::find($data['client_id']);
        if(!$client){
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => ['client_id' => 'O client_id é inválido'],
            ], 422);
        }
        if($data['valor']==0){
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => ['valor' => 'O valor não pode ser zero'],
            ], 422);
        }
        $point->client_id = $data['client_id'];
        $point->fill($data);
        $point->save();
        return $point;
    }
    public function saldo($client_id){
        $saldo = Point::where('client_id',$client_id)->sum('valor');
        return $saldo;
    }
    /**
     * Exibir uma movimentação de pontos específica
     */
    public function show(Request $request, string $id)
    {
        $permissionCheck = $this->checkUserPermission('view');
        if (!$permissionCheck['success']) {
            return $permissionCheck['response'];
        }

        $point = Point::with(['cliente', 'usuario'])->findOrFail($id);

        // Converter config para array
        if (is_string($point->config)) {
            $point->config = json_decode($point->config, true) ?? [];
        }

        return response()->json($point);
    }

    /**
     * Atualizar uma movimentação de pontos
     */
    public function update(Request $request, string $id)
    {
        // Verificar permissões
        $permissionCheck = $this->checkUserPermission('edit');
        if (!$permissionCheck['success']) {
            return $permissionCheck['response'];
        }
        $user = $permissionCheck['user'];

        // Buscar ponto existente
        $point = Point::findOrFail($id);

        // Sanitizar e validar dados
        $data = $this->sanitizeInput($request->all());
        $validation = $this->validatePointData($data, true);

        if (!$validation['success']) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validation['errors'],
            ], 422);
        }

        // Processar dados e atualizar ponto
        $processedData = $this->processPointData($validation['data'], $user, true);
        $point->update($processedData);

        return $this->preparePointResponse(
            $point,
            'Movimentação de pontos atualizada com sucesso'
        );
    }

    /**
     * Remover uma movimentação de pontos (soft delete)
     */
    public function destroy(string $id)
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('delete')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $point = Point::findOrFail($id);
        $point->delete();

        return response()->json([
            'message' => 'Movimentação de pontos removida com sucesso',
            'status' => 200
        ]);
    }

    /**
     * Listar movimentações de pontos na lixeira
     */
    public function trash(Request $request)
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('view')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $query = Point::onlyTrashed()->with(['cliente', 'usuario']);

        // Filtros
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('origem', 'like', "%{$search}%")
                  ->orWhereHas('cliente', function($clienteQuery) use ($search) {
                      $clienteQuery->where('name', 'like', "%{$search}%")
                                  ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Ordenação
        $orderBy = $request->get('order_by', 'deleted_at');
        $orderDirection = $request->get('order_direction', 'desc');
        $query->orderBy($orderBy, $orderDirection);

        // Paginação
        $perPage = $request->get('per_page', 15);
        $points = $query->paginate($perPage);

        // Converter config para array
        $points->getCollection()->transform(function ($point) {
            if (is_string($point->config)) {
                $point->config = json_decode($point->config, true) ?? [];
            }
            return $point;
        });

        return response()->json($points);
    }

    /**
     * Restaurar uma movimentação de pontos da lixeira
     */
    public function restore(string $id)
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('edit')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $point = Point::onlyTrashed()->findOrFail($id);
        $point->restore();

        return response()->json([
            'message' => 'Movimentação de pontos restaurada com sucesso',
            'status' => 200
        ]);
    }

    /**
     * Excluir permanentemente uma movimentação de pontos
     */
    public function forceDelete(string $id)
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('delete')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $point = Point::onlyTrashed()->findOrFail($id);
        $point->forceDelete();

        return response()->json([
            'message' => 'Movimentação de pontos excluída permanentemente',
            'status' => 200
        ]);
    }

    /**
     * Obter saldo de pontos de um cliente
     */
    public function saldoCliente(Request $request, string $clienteId)
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('view')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $saldo = Point::saldoCliente($clienteId);
        $pontosVencendo = Point::pontosVencendoCliente($clienteId, 30);

        return response()->json([
            'client_id' => $clienteId,
            'saldo_atual' => $saldo,
            'pontos_vencendo_30_dias' => $pontosVencendo,
            'saldo_formatado' => number_format($saldo, 0, ',', '.') . ' pts',
        ]);
    }

    /**
     * Obter extrato de pontos de um usuário específico
     * GET /admin/users/{userId}/points-balance
     */
    public function getAuthenticatedUserBalance(\Illuminate\Http\Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Acesso negado'], 403);
        }
    
        $userId = $user->id;
    
        // Total de pontos ganhos (créditos)
        $totalEarned = (float) \App\Models\Point::where('client_id', $userId)
            ->where('tipo', 'credito')
            ->where('ativo', 's')
            ->where('excluido', 'n')
            ->where('deletado', 'n')
            ->sum('valor');
    
        // Total de pontos gastos (débitos)
        $totalSpent = (float) \App\Models\Point::where('client_id', $userId)
            ->where('tipo', 'debito')
            ->where('ativo', 's')
            ->where('excluido', 'n')
            ->where('deletado', 'n')
            ->sum('valor');
    
        // Total de transações
        $totalTransactions = (int) \App\Models\Point::where('client_id', $userId)
            ->where('ativo', 's')
            ->where('excluido', 'n')
            ->where('deletado', 'n')
            ->count();
    
        // Pontos ativos (créditos não expirados - débitos)
        $activeBalance = (float) (\App\Models\Point::where('client_id', $userId)
            ->where('tipo', 'credito')
            ->where(function ($q) {
                $q->whereNull('data_expiracao')
                  ->orWhere('data_expiracao', '>', now());
            })
            ->sum('valor')
        ) - (float) (\App\Models\Point::where('client_id', $userId)
            ->where('tipo', 'debito')
            ->sum('valor'));
    
        // Pontos expirados (créditos marcados como expirados)
        $expiredPoints = (float) \App\Models\Point::where('client_id', $userId)
            ->where('tipo', 'credito')
            ->where('status', 'expirado')
            ->where('ativo', 's')
            ->where('excluido', 'n')
            ->where('deletado', 'n')
            ->sum('valor');
    
        // Total de pontos (saldo disponível); igualamos ao activeBalance
        $totalPoints = $activeBalance;
    
        $data = [
            'total_points' => (string) (int) $totalPoints,
            'total_earned' => (string) (int) $totalEarned,
            'total_spent' => (string) (int) $totalSpent,
            'total_transactions' => $totalTransactions,
            'active_points' => (string) (int) $activeBalance,
            'expired_points' => (int) $expiredPoints,
        ];
    
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $data,
        ], 200);
    }

    /**
     * Calcular saldo anterior à transação
     */
    private function calculateBalanceBefore($transaction)
    {
        // dd($transaction);
        $previousTransactions = Point::where('client_id', $transaction->client_id)
                                   ->where('created_at', '<', $transaction->created_at)
                                   ->ativos()
                                   ->get();

        $balance = 0;
        foreach ($previousTransactions as $prev) {
            if ($prev->tipo === 'credito') {
                $balance += $prev->valor;
            } else {
                $balance -= abs($prev->valor);
            }
        }

        return $balance;
    }

    /**
     * Calcular saldo posterior à transação
     */
    private function calculateBalanceAfter($transaction)
    {
        $balanceBefore = $this->calculateBalanceBefore($transaction);

        if ($transaction->tipo === 'credito') {
            return $balanceBefore + $transaction->valor;
        } else {
            return $balanceBefore - abs($transaction->valor);
        }
    }

    /**
     * Relatório de pontos por período
     */
    public function relatorio(Request $request)
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('view')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $dataInicio = $request->get('data_inicio', now()->startOfMonth()->format('Y-m-d'));
        $dataFim = $request->get('data_fim', now()->endOfMonth()->format('Y-m-d'));

        $query = Point::porPeriodo($dataInicio, $dataFim);

        $relatorio = [
            'periodo' => [
                'inicio' => $dataInicio,
                'fim' => $dataFim,
            ],
            'totais' => [
                'creditos' => $query->clone()->creditos()->sum('valor'),
                'debitos' => $query->clone()->debitos()->sum('valor'),
                'movimentacoes' => $query->clone()->count(),
            ],
            'por_status' => [
                'ativo' => $query->clone()->where('status', 'ativo')->sum('valor'),
                'usado' => $query->clone()->where('status', 'usado')->sum('valor'),
                'expirado' => $query->clone()->where('status', 'expirado')->sum('valor'),
                'cancelado' => $query->clone()->where('status', 'cancelado')->sum('valor'),
            ],
            'por_origem' => Point::porPeriodo($dataInicio, $dataFim)
                                ->selectRaw('origem, SUM(valor) as total, COUNT(*) as quantidade')
                                ->whereNotNull('origem')
                                ->groupBy('origem')
                                ->orderBy('total', 'desc')
                                ->get(),
        ];

        $relatorio['saldo_liquido'] = $relatorio['totais']['creditos'] - $relatorio['totais']['debitos'];

        return response()->json($relatorio);
    }

    /**
     * Expirar pontos vencidos
     */
    public function expirarPontos(Request $request)
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('edit')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $pontosExpirados = Point::expirados()->update(['status' => 'expirado']);

        return response()->json([
            'message' => "$pontosExpirados movimentações de pontos foram marcadas como expiradas",
            'pontos_expirados' => $pontosExpirados,
            'status' => 200
        ]);
    }

    /**
     * Listar extratos de pontos com paginação, filtros e busca
     * GET /admin/points-extracts
     */
    public function getPointsExtracts(Request $request)
    {
        // Verificar permissões
        $permissionCheck = $this->checkUserPermission('view');
        if (!$permissionCheck['success']) {
            return $permissionCheck['response'];
        }

        // Parâmetros de entrada
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');
        $type = $request->get('type'); // credito ou debito
        $userId = $request->get('user_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $sort = $request->get('sort', 'created_at');
        $order = $request->get('order', 'desc');

        // Validar parâmetros
        if ($type && !in_array($type, ['credito', 'debito'])) {
            return response()->json([
                'success' => false,
                'message' => 'Tipo de transação inválido. Use "credito" ou "debito".'
            ], 400);
        }

        if ($order && !in_array($order, ['asc', 'desc'])) {
            return response()->json([
                'success' => false,
                'message' => 'Direção de ordenação inválida. Use "asc" ou "desc".'
            ], 400);
        }

        // Construir query
        $query = Point::query();

        // Filtro por usuário específico
        if ($userId) {
            $query->where('client_id', $userId);
        }

        // Filtro por tipo de transação
        if ($type) {
            $query->where('tipo', $type);
        }

        // Filtro por período
        if ($dateFrom) {
            $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo) {
            $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        // Busca por nome, email, descrição ou ID
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('origem', 'like', "%{$search}%")
                  ->orWhere('pedido_id', 'like', "%{$search}%")
                  ->orWhereHas('cliente', function($clienteQuery) use ($search) {
                      $clienteQuery->where('name', 'like', "%{$search}%")
                                  ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Ordenação
        $allowedSortFields = ['id', 'created_at', 'valor', 'tipo', 'status', 'data'];
        if (in_array($sort, $allowedSortFields)) {
            $query->orderBy($sort, $order);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Executar query com paginação
        $points = $query->paginate($perPage, ['*'], 'page', $page);
        // dd($points);
        // Mapear dados para o formato solicitado
        $mappedData = $points->getCollection()->map(function ($point) {
            // Buscar dados do usuário
            $user = User::find($point->client_id);

            // Calcular saldo antes e depois da transação
            $balanceBefore = $this->calculateBalanceBefore($point);
            $balanceAfter = $this->calculateBalanceAfter($point);

            return [
                'id' => (string) $point->id,
                'userId' => (string) $point->client_id,
                'userName' => $user ? $user->name : 'N/A',
                'userEmail' => $user ? $user->email : 'N/A',
                'type' => $point->tipo === 'credito' ? 'earned' : 'redeemed',
                // 'points' => $point->tipo === 'credito' ? (int) $point->valor : -(int) $point->valor,
                'points' => $point->valor,
                'description' => $point->description ?? 'Via API',
                'reference' => $point->pedido_id ?? $point->origem ?? null,
                'balanceBefore' => (int) $balanceBefore,
                'balanceAfter' => (int) $balanceAfter,
                'expirationDate' => $point->data_expiracao ? Carbon::parse($point->data_expiracao)->toISOString() : null,
                'createdAt' => Carbon::parse($point->created_at)->toISOString(),
                'createdBy' => $point->autor ? (string) $point->autor : null
            ];
        });
        // dd($mappedData);
        // Preparar resposta com metadados de paginação
        return response()->json([
            'success' => true,
            'data' => $mappedData,
            'pagination' => [
                'current_page' => $points->currentPage(),
                'per_page' => $points->perPage(),
                'total' => $points->total(),
                'last_page' => $points->lastPage(),
                'from' => $points->firstItem(),
                'to' => $points->lastItem(),
                'has_more_pages' => $points->hasMorePages(),
                'next_page_url' => $points->nextPageUrl(),
                'prev_page_url' => $points->previousPageUrl(),
                'total_pages' => $points->lastPage(),
            ]
        ]);
    }

    /**
     * Obter estatísticas dos extratos de pontos
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPointsExtractsStats()
    {
        try {
            // Total de transações
            $totalTransactions = Point::count();

            // Total de pontos ganhos (crédito)
            $totalEarned = Point::where('tipo', 'credito')->sum('valor');

            // Total de pontos resgatados (débito)
            $totalRedeemed = Point::where('tipo', 'debito')->sum('valor');

            // Total de pontos expirados (assumindo que há uma coluna status ou data_expiracao)
            $totalExpired = Point::where('data_expiracao', '<', now())
                ->where('tipo', 'credito')
                ->sum('valor');

            // Usuários ativos (que têm pelo menos uma transação)
            $activeUsers = Point::distinct('client_id')->count('client_id');

            // Total de ajustes (assumindo que ajustes são transações com origem específica)
            $totalAdjustments = Point::where('origem', 'ajuste')
                ->orWhere('origem', 'adjustment')
                ->orWhere('description', 'like', '%ajuste%')
                ->orWhere('description', 'like', '%adjustment%')
                ->count();

            // Total de reembolsos (assumindo que reembolsos são transações com origem específica)
            $totalRefunds = Point::where('origem', 'reembolso')
                ->orWhere('origem', 'refund')
                ->orWhere('description', 'like', '%reembolso%')
                ->orWhere('description', 'like', '%refund%')
                ->count();

            $stats = [
                'totalTransactions' => (int) $totalTransactions,
                'totalEarned' => (int) $totalEarned,
                'totalRedeemed' => (int) $totalRedeemed,
                'totalExpired' => (int) $totalExpired,
                'activeUsers' => (int) $activeUsers,
                'totalAdjustments' => (int) $totalAdjustments,
                'totalRefunds' => (int) $totalRefunds
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter estatísticas dos pontos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Criar ajuste manual no extrato de pontos
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createPointsAdjustment(Request $request): JsonResponse
    {
        try {
            // Validar dados de entrada
            $validated = $request->validate([
                'user_id' => 'required|string',
                'points' => 'required|numeric',
                'description' => 'required|string|max:255',
                'reason' => 'nullable|string|max:500'
            ]);

            // Verificar se o usuário existe
            $user = User::find($validated['user_id']);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

            // Determinar tipo da transação baseado no valor dos pontos
            $tipo = $validated['points'] >= 0 ? 'credito' : 'debito';
            $valorAbsoluto = abs($validated['points']);

            // Criar registro de ajuste
            $adjustment = Point::create([
                'client_id' => $validated['user_id'],
                'valor' => $valorAbsoluto,
                'tipo' => $tipo,
                'data' => now()->toDateString(), // Campo obrigatório
                'origem' => 'ajuste',
                'description' => $validated['description'],
                'data_expiracao' => $tipo === 'credito' ? now()->addYear() : null, // Créditos expiram em 1 ano
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Calcular novo saldo do usuário
            $currentBalance = Point::where('client_id', $validated['user_id'])
                ->where(function($query) {
                    $query->where('tipo', 'credito')
                          ->where(function($subQuery) {
                              $subQuery->whereNull('data_expiracao')
                                       ->orWhere('data_expiracao', '>', now());
                          });
                })
                ->sum('valor') - Point::where('client_id', $validated['user_id'])
                ->where('tipo', 'debito')
                ->sum('valor');

            return response()->json([
                'success' => true,
                'message' => 'Ajuste criado com sucesso',
                'data' => [
                    'adjustment_id' => $adjustment->id,
                    'user_id' => $validated['user_id'],
                    'points_adjusted' => $validated['points'],
                    'type' => $tipo,
                    'description' => $validated['description'],
                    'reason' => $validated['reason'],
                    'new_balance' => (int) $currentBalance,
                    'created_at' => $adjustment->created_at->toISOString()
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar ajuste manual',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter histórico de pontos de um usuário específico
     * GET /admin/users/{userId}/points-extracts
     */
    public function getUserPointsHistory(Request $request, $userId)
    {
        $permissionCheck = $this->checkUserPermission('view');
        if (!$permissionCheck['success']) {
            return $permissionCheck['response'];
        }

        // Verificar se o usuário existe
        $user = User::find($userId);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário não encontrado'
            ], 404);
        }

        // Parâmetros de paginação e filtros
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $type = $request->get('type'); // credito, debito
        $status = $request->get('status'); // ativo, usado, expirado, cancelado
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $search = $request->get('search');
        $sort = $request->get('sort', 'created_at');
        $order = $request->get('order', 'desc');

        // Query base para pontos do usuário
        $query = Point::where('client_id', $userId)
                     ->where('ativo', 's')
                     ->where('excluido', 'n')
                     ->where('deletado', 'n');

        // Aplicar filtros
        if ($type && in_array($type, ['credito', 'debito'])) {
            $query->where('tipo', $type);
        }

        if ($status && in_array($status, ['ativo', 'usado', 'expirado', 'cancelado'])) {
            $query->where('status', $status);
        }

        // Filtro por período
        if ($dateFrom) {
            $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo) {
            $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        // Busca por descrição, origem ou ID do pedido
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('origem', 'like', "%{$search}%")
                  ->orWhere('pedido_id', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        // Ordenação
        $allowedSortFields = ['id', 'created_at', 'valor', 'tipo', 'status', 'data'];
        if (in_array($sort, $allowedSortFields)) {
            $query->orderBy($sort, $order);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Executar query com paginação
        $points = $query->paginate($perPage, ['*'], 'page', $page);

        // Calcular estatísticas do usuário
        $userStats = [
            'total_points' => Point::where('client_id', $userId)
                                  ->where('ativo', 's')
                                  ->where('excluido', 'n')
                                  ->where('deletado', 'n')
                                  ->sum('valor'),
            'total_earned' => Point::where('client_id', $userId)
                                  ->where('tipo', 'credito')
                                  ->where('ativo', 's')
                                  ->where('excluido', 'n')
                                  ->where('deletado', 'n')
                                  ->sum('valor'),
            'total_spent' => Point::where('client_id', $userId)
                                 ->where('tipo', 'debito')
                                 ->where('ativo', 's')
                                 ->where('excluido', 'n')
                                 ->where('deletado', 'n')
                                 ->sum('valor'),
            'total_transactions' => Point::where('client_id', $userId)
                                        ->where('ativo', 's')
                                        ->where('excluido', 'n')
                                        ->where('deletado', 'n')
                                        ->count(),
            'active_points' => Point::where('client_id', $userId)
                                   ->where('status', 'ativo')
                                   ->where('ativo', 's')
                                   ->where('excluido', 'n')
                                   ->where('deletado', 'n')
                                   ->sum('valor'),
            'expired_points' => Point::where('client_id', $userId)
                                    ->where('status', 'expirado')
                                    ->where('ativo', 's')
                                    ->where('excluido', 'n')
                                    ->where('deletado', 'n')
                                    ->sum('valor')
        ];

        // Formatar dados de resposta
        $responseData = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'cpf' => $user->cpf ?? null
            ],
            'stats' => $userStats,
            'points' => $points->items(),
            'pagination' => [
                'current_page' => $points->currentPage(),
                'last_page' => $points->lastPage(),
                'per_page' => $points->perPage(),
                'total' => $points->total(),
                'from' => $points->firstItem(),
                'to' => $points->lastItem()
            ],
            'filters' => [
                'type' => $type,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'search' => $search,
                'sort' => $sort,
                'order' => $order
            ]
        ];

        return response()->json([
            'success' => true,
            'message' => 'Histórico de pontos obtido com sucesso',
            'data' => $responseData
        ], 200);
    }
}
