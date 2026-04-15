<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Point;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\Qlib;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Controller para gerenciar pontos dos clientes
 * Sistema de pontuação/recompensas
 */
class PointController extends Controller
{
    public $partner_id;
    protected $permissionService;

    public function __construct()
    {
        $this->permissionService = new PermissionService;
        $this->partner_id = Qlib::qoption('permission_partner_id') ? Qlib::qoption('permission_partner_id') : 5;
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
            $validated['autor'] = $user->id ?? Auth::id();
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
     * Listar todos os pontos com filtros, ordenação e paginação.
     *
     * Parâmetros aceitos:
     * - client_id, tipo (credito|debito), status, origem, search
     * - dateFrom/dateTo ou data_inicio/data_fim ou date_from/date_to
     * - sort (ex.: createdAt, points, type, status, origin, data)
     * - order (asc|desc), per_page, page
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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

        // Filtro de tipo: aceita 'tipo' (snake_case) ou 'type' (camelCase do frontend)
        $typeParam = $request->get('type') ?? $request->get('tipo');
        if ($typeParam) {
            if ($typeParam === 'expired') {
                // Registros de expiração: tipo = 'expired'
                $query->where('tipo', 'expired');
            } elseif (in_array($typeParam, ['credito', 'debito'])) {
                $query->where('tipo', $typeParam);
            }
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

        // Período: suporta camelCase e snake_case
        $dateFrom = $request->get('dateFrom') ?? $request->get('date_from') ?? $request->get('data_inicio');
        $dateTo   = $request->get('dateTo')   ?? $request->get('date_to')   ?? $request->get('data_fim');
        if ($dateFrom && $dateTo) {
            $query->porPeriodo($dateFrom, $dateTo);
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

        // Ordenação: aceita sort/order e mapeia campos conhecidos
        $sortParam = $request->get('sort', $request->get('order_by', 'created_at'));
        $sortMap = [
            'createdAt' => 'created_at',
            'expirationDate' => 'data_expiracao',
            'points' => 'valor',
            'type' => 'tipo',
            'status' => 'status',
            'origin' => 'origem',
            'data' => 'data',
            'created_at' => 'created_at',
        ];
        $orderBy = $sortMap[$sortParam] ?? 'created_at';
        $orderDirection = strtolower($request->get('order', $request->get('order_direction', 'desc'))) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($orderBy, $orderDirection);

        // Paginação
        // Paginação: suporte ao parâmetro page
        $perPage = $request->get('per_page', 15);
        $page = (int) $request->get('page', 1);
        $points = $query->paginate($perPage, ['*'], 'page', $page);

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
            throw new \InvalidArgumentException('O client_id é obrigatório');
        }
        $client = User::find($data['client_id']);
        if(!$client){
            throw new \InvalidArgumentException('O client_id é inválido');
        }
        if($data['valor']==0){
            throw new \InvalidArgumentException('O valor não pode ser zero');
        }
        $point->client_id = $data['client_id'];
        if(!$point->autor && Auth::check()){
            $point->autor = Auth::id();
        }
        $point->fill($data);
        $point->save();
        return $point;
    }
    public function saldo($client_id){
        $saldo = Point::saldoCliente($client_id);
        return $saldo;
    }
    /**
     * Exibir uma movimentação de pontos específica
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        
        // Permite que administradores assumam a visão de um cliente (nível 5 ou menor)
        if ($user && isset($user->permission_id) && isset($this->partner_id) && $user->permission_id <= $this->partner_id && $request->filled('admin_client_id')) {
            $overriddenUser = \App\Models\User::find($request->admin_client_id);
            if ($overriddenUser) $user = $overriddenUser;
        }

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
     * Obter extrato de pontos do usuário autenticado
     * GET /api/v1/user/points/extract
     */
    public function getAuthenticatedUserPointsExtract(\Illuminate\Http\Request $request)
    {
        $user = $request->user();
        
        // Permite que administradores assumam a visão de um cliente (nível 5 ou menor)
        if ($user && isset($user->permission_id) && isset($this->partner_id) && $user->permission_id <= $this->partner_id && $request->filled('admin_client_id')) {
            $overriddenUser = \App\Models\User::find($request->admin_client_id);
            if ($overriddenUser) $user = $overriddenUser;
        }

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Acesso negado'], 403);
        }

        $userId = $user->id;

        // Parâmetros de paginação e filtros
        $perPage = $request->get('per_page', 15);
        $page = (int) $request->get('page', 1);
        $type = $request->get('type'); // credito, debito
        $search = $request->get('search');
        $dateFrom = $request->get('dateFrom') ?? $request->get('date_from');
        $dateTo = $request->get('dateTo') ?? $request->get('date_to');

        // Query base para pontos do usuário
        $queryBuilder = \App\Models\Point::where('client_id', $userId)
            ->where('excluido', 'n')
            ->where('deletado', 'n')
            ->where('ativo', 's');

        // Aplicar filtros
        if ($type) {
            if ($type === 'credito') {
                $queryBuilder->where('tipo', 'credito');
            } elseif ($type === 'debito') {
                $queryBuilder->where('tipo', 'debito')->where('origem', '!=', 'expiracao');
            } elseif ($type === 'expired' || $type === 'expiracao') {
                $queryBuilder->where('origem', 'expiracao');
            }
        }

        if ($dateFrom) {
            $queryBuilder->where('created_at', '>=', \Carbon\Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo) {
            $queryBuilder->where('created_at', '<=', \Carbon\Carbon::parse($dateTo)->endOfDay());
        }

        if ($search) {
            $queryBuilder->where(function($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('origem', 'like', "%{$search}%")
                  ->orWhere('pedido_id', 'like', "%{$search}%");
            });
        }

        $queryBuilder->orderBy('created_at', 'desc');

        $points = $queryBuilder->paginate($perPage, ['*'], 'page', $page);

        // Mapear para o formato esperado pelo frontend
        $items = collect($points->items())->map(function($point) {
            // Mapeamento de tipo para o frontend
            $typeMapping = [
                'credito' => 'earned',
                'debito' => 'redeemed',
                'bonus' => 'bonus',
                'ajuste' => 'adjustment',
                'reembolso' => 'refund',
                'expiracao' => 'expired'
            ];

            // Calcular saldo antes e depois da transação
            $balanceBefore = $this->calculateBalanceBefore($point);
            $balanceAfter = $this->calculateBalanceAfter($point);

            // Determinar o tipo mais específico baseado na origem, recaindo no tipo geral
            $resolvedType = 'adjustment';
            if (!empty($point->origem) && isset($typeMapping[$point->origem])) {
                $resolvedType = $typeMapping[$point->origem];
            } else if (isset($typeMapping[$point->tipo])) {
                $resolvedType = $typeMapping[$point->tipo];
            }

            return [
                'id' => (string)$point->id,
                'userId' => (string)$point->client_id,
                'type' => $resolvedType,
                'points' => (float)$point->valor,
                'valor_usado' => (float)($point->valor_usado ?? 0),
                'saldo_restante' => (float)($point->saldo_restante ?? $point->valor),
                'description' => $point->description,
                'reference' => $point->pedido_id,
                'balanceBefore' => (float)$balanceBefore,
                'balanceAfter' => (float)$balanceAfter,
                'expirationDate' => $point->data_expiracao ? \Carbon\Carbon::parse($point->data_expiracao)->toISOString() : null,
                'createdAt' => $point->created_at->toISOString(),
                'status' => $point->status
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $points->currentPage(),
                'last_page' => $points->lastPage(),
                'per_page' => $points->perPage(),
                'total' => $points->total(),
            ]
        ], 200);
    }

    /**
     * Obter extrato de pontos do usuário autenticado (estatísticas simplificadas)
     * GET /api/v1/user/points/balance
     */
    public function getAuthenticatedUserBalance(\Illuminate\Http\Request $request)
    {
        $user = $request->user();

        // Permite que administradores assumam a visão de um cliente (nível 5 ou menor)
        if ($user && isset($user->permission_id) && isset($this->partner_id) && $user->permission_id <= $this->partner_id && $request->filled('admin_client_id')) {
            $overriddenUser = \App\Models\User::find($request->admin_client_id);
            if ($overriddenUser) $user = $overriddenUser;
        }

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Acesso negado'], 403);
        }

        $userId = $user->id;

        // Parâmetros de filtros (opcionais para a totalização)
        $type = $request->get('type');
        $search = $request->get('search');
        $dateFrom = $request->get('dateFrom') ?? $request->get('date_from');
        $dateTo = $request->get('dateTo') ?? $request->get('date_to');

        // Base query para filtros comuns
        $baseQuery = \App\Models\Point::where('client_id', $userId)
            ->where('excluido', 'n')
            ->where('deletado', 'n')
            ->where('ativo', 's');

        // Aplicar filtros se informados
        $filteredQuery = clone $baseQuery;
        if ($dateFrom) {
            $filteredQuery->where('created_at', '>=', \Carbon\Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo) {
            $filteredQuery->where('created_at', '<=', \Carbon\Carbon::parse($dateTo)->endOfDay());
        }
        if ($search) {
            $filteredQuery->where(function($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('origem', 'like', "%{$search}%")
                  ->orWhere('pedido_id', 'like', "%{$search}%");
            });
        }

        // Total de pontos ganhos (ignorando saldo migrado para não inflacionar o total acumulado)
        $totalEarned = (float) (clone $filteredQuery)
            ->where('tipo', 'credito')
            ->where(function ($q) {
                $q->whereNull('origem')->orWhere('origem', '!=', 'migracao_legado');
            })
            ->sum('valor');

        // Total de pontos gastos (absoluto)
        $totalSpent = abs((float) (clone $filteredQuery)->where('tipo', 'debito')->sum('valor'));

        // Saldo disponível (global, não filtrado por data, pois saldo é cumulativo)
        $activeBalance = \App\Models\Point::saldoCliente($userId);

        // Pontos que expiram em breve (próximos 30 dias)
        $pointsExpiringSoon = \App\Models\Point::where('client_id', $userId)
            ->where('tipo', 'credito')
            ->ativos()
            ->where('status', '!=', 'expirado')
            ->whereNotNull('data_expiracao')
            ->whereBetween('data_expiracao', [\Carbon\Carbon::now(), \Carbon\Carbon::now()->addDays(30)])
            ->get()
            ->sum(function($p) {
                return (float) $p->valor - (float) ($p->valor_usado ?? 0);
            });

        $data = [
            'total_points' => (float) $activeBalance,
            'total_earned' => (float) $totalEarned,
            'total_spent' => (float) $totalSpent,
            'total_transactions' => (int) (clone $filteredQuery)->count(),
            'active_points' => (float) $activeBalance,
            'expired_points' => (float) (clone $filteredQuery)->where('tipo', 'credito')
                ->where('status', 'expirado')
                ->sum('valor'),
            'points_expiring_soon' => (float) $pointsExpiringSoon,
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
        $previousTransactions = Point::where('client_id', $transaction->client_id)
                                   ->where(function ($query) use ($transaction) {
                                       $query->where('created_at', '<', $transaction->created_at)
                                             ->orWhere(function ($q) use ($transaction) {
                                                 $q->where('created_at', '=', $transaction->created_at)
                                                   ->where('id', '<', $transaction->id);
                                             });
                                   })
                                   ->where('excluido', 'n')
                                   ->where('deletado', 'n')
                                   ->where('status', '!=', 'cancelado')
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
        $permission_id = $user->permission_id;
        $dataInicio = $request->get('data_inicio', now()->startOfMonth()->format('Y-m-d'));
        $dataFim = $request->get('data_fim', now()->endOfMonth()->format('Y-m-d'));
        // se o parcerio for diferente de 5, então filtra registros de pontos onde usuario_id for igual a id do usuário logado
        if ($permission_id >= $this->partner_id) {
            $query = Point::porPeriodo($dataInicio, $dataFim)
                          ->where('usuario_id', $permission_id);
        } else {
            $query = Point::porPeriodo($dataInicio, $dataFim);
        }

        $relatorio = [
            'periodo' => [
                'inicio' => $dataInicio,
                'fim' => $dataFim,
            ],
            'totais' => [
                'creditos' => $query->clone()->creditos()->where(function($q) {
                    $q->whereNull('origem')->orWhere('origem', '!=', 'migracao_legado');
                })->sum('valor'),
                'debitos' => abs((float) $query->clone()->debitos()->sum('valor')),
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
     * Listar o saldo total de pontos por cliente.
     */
    public function customerBalancesReport(Request $request)
    {
        $permissionCheck = $this->checkUserPermission('view');
        if (!$permissionCheck['success']) {
            return $permissionCheck['response'];
        }

        $user = $permissionCheck['user'];
        $clientPermissionId = (int) (Qlib::qoption('permission_client_id') ?? 6);
        $perPage = max(1, (int) $request->get('per_page', 15));
        $search = trim((string) $request->get('search', ''));
        $order = strtolower((string) $request->get('order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $requestedOrderBy = (string) $request->get('order_by', 'name');
        $orderByMap = [
            'name' => 'name',
            'email' => 'email',
            'created_at' => 'created_at',
            'saldo_total' => 'saldo_total',
        ];
        $orderBy = $orderByMap[$requestedOrderBy] ?? 'name';

        $query = DB::table('users as clients')
            ->leftJoin('points', function ($join) {
                $join->on('points.client_id', '=', 'clients.id')
                    ->where('points.excluido', '=', 'n')
                    ->where('points.deletado', '=', 'n');
            })
            ->where('clients.permission_id', '=', $clientPermissionId)
            ->where(function ($q) {
                $q->whereNull('clients.deletado')
                    ->orWhere('clients.deletado', '!=', 's');
            })
            ->where(function ($q) {
                $q->whereNull('clients.excluido')
                    ->orWhere('clients.excluido', '!=', 's');
            });

        if ((int) $user->permission_id >= 3) {
            $query->where('clients.autor', '=', $user->id);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('clients.name', 'like', "%{$search}%")
                    ->orWhere('clients.email', 'like', "%{$search}%")
                    ->orWhere('clients.cpf', 'like', "%{$search}%")
                    ->orWhere('clients.cnpj', 'like', "%{$search}%");
            });
        }

        $query->select([
            'clients.id',
            'clients.name',
            'clients.email',
            'clients.cpf',
            'clients.created_at',
        ])->selectRaw("
            COALESCE(
                SUM(
                    CASE
                        WHEN points.status != 'cancelado' THEN
                            CASE
                                WHEN points.tipo = 'credito' THEN points.valor
                                ELSE -ABS(points.valor)
                            END
                        ELSE 0
                    END
                ),
                0
            ) - COALESCE(
                SUM(
                    CASE
                        WHEN points.tipo = 'credito'
                            AND points.status = 'ativo'
                            AND points.ativo = 's'
                            AND points.data_expiracao IS NOT NULL
                            AND points.data_expiracao <= CURDATE()
                        THEN GREATEST(points.valor - COALESCE(points.valor_usado, 0), 0)
                        ELSE 0
                    END
                ),
                0
            ) AS saldo_total
        ");

        $query->groupBy([
            'clients.id',
            'clients.name',
            'clients.email',
            'clients.cpf',
            'clients.created_at',
        ]);

        $summaryBaseQuery = DB::query()->fromSub(clone $query, 'customer_balances');
        $summary = [
            'total_clients' => (int) (clone $summaryBaseQuery)->count(),
            'total_balance' => (float) (clone $summaryBaseQuery)->sum('saldo_total'),
            'clients_with_balance' => (int) (clone $summaryBaseQuery)
                ->where('saldo_total', '>', 0)
                ->count(),
        ];

        $report = $query
            ->orderBy($orderBy, $order)
            ->paginate($perPage);

        $report->getCollection()->transform(function ($item) {
            $item->saldo_total = (float) $item->saldo_total;
            return $item;
        });

        return response()->json([
            'data' => $report->items(),
            'total' => $report->total(),
            'current_page' => $report->currentPage(),
            'last_page' => $report->lastPage(),
            'per_page' => $report->perPage(),
            'summary' => $summary,
        ]);
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
        $user = $request->user();
        // Parâmetros de entrada
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');
        $type = $request->get('type'); // credito, debito ou expired
        $userId = $request->get('user_id');
        $status = $request->get('status'); // ativo, expirado, usado, cancelado
        // Suporte a camelCase e snake_case para datas
        $dateFrom = $request->get('dateFrom') ?? $request->get('date_from');
        $dateTo = $request->get('dateTo') ?? $request->get('date_to');
        // Ordenação: suporta sort=createdAt e sort=expirationDate
        $sortParam = $request->get('sort', 'created_at');
        $sortMap = [
            'createdAt' => 'created_at',
            'expirationDate' => 'data_expiracao',
        ];
        $sort = $sortMap[$sortParam] ?? $sortParam;
        $order = strtolower($request->get('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Validar parâmetros
        if ($type && !in_array($type, ['credito', 'debito', 'expired', 'expiracao'])) {
            return response()->json([
                'success' => false,
                'message' => 'Tipo de transação inválido. Use "credito", "debito" ou "expired".'
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
        //desconsidera pontos com excluido=s
        $query->where('excluido', '!=', 's');
        //caso seja um usuario com permissão maior que 5 (parceiros ou menores) listar apenas pontos em que autor = autor_id
        if ($user->permission_id > $this->partner_id) {
            $query->where('autor', $user->id);
        }
        // Filtro por usuário específico
        if ($userId) {
            $query->where('client_id', $userId);
        }

        // Filtro por tipo de transação (suporte a "expired" como tipo especial)
        if ($type === 'expired' || $type === 'expiracao') {
            $query->where(function($q) {
                $q->where('tipo', 'expired')
                  ->orWhere('status', 'expirado');
            });
        } elseif ($type) {
            $query->where('tipo', $type);
        }

        // Filtro por status (ativo, expirado, usado, cancelado)
        if ($status && in_array($status, ['ativo', 'expirado', 'usado', 'cancelado'])) {
            $query->where('status', $status);
        }

        // Filtro por período
        if ($dateFrom) {
            $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo) {
            $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        // Filtro por lote de expiração (batch_id)
        if ($request->has('batch_id')) {
            $query->where('config->batch_id', $request->batch_id);
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

            // Determinar tipo para o frontend
            $frontendType = 'adjustment';
            if ($point->tipo === 'expired' || $point->status === 'expirado') {
                $frontendType = 'expired';
            } elseif ($point->tipo === 'credito') {
                $frontendType = 'earned';
            } elseif ($point->tipo === 'debito') {
                $frontendType = 'redeemed';
            } elseif ($point->tipo === 'bonus') {
                $frontendType = 'bonus';
            } elseif ($point->tipo === 'refund' || $point->tipo === 'reembolso') {
                $frontendType = 'refund';
            }

            // Verificar se ponto está expirado mas sem status atualizado
            $isExpired = $point->status === 'expirado'
                || ($point->data_expiracao && Carbon::parse($point->data_expiracao)->isPast() && $point->tipo === 'credito');
            if ($isExpired && $frontendType !== 'expired') {
                $frontendType = 'expired';
            }

            return [
                'id' => (string) $point->id,
                'userId' => (string) $point->client_id,
                'userName' => $user ? $user->name : 'N/A',
                'userEmail' => $user ? $user->email : 'N/A',
                'userCpf' => $user ? $user->cpf : null,
                'type' => $frontendType,
                'points' => $point->valor,
                'valor_usado' => (float) ($point->valor_usado ?? 0),
                'saldo_restante' => (float) ($point->saldo_restante ?? $point->valor),
                'description' => $point->description ?? 'Via API',
                'reference' => $point->pedido_id ?? $point->origem ?? null,
                'balanceBefore' => (int) $balanceBefore,
                'balanceAfter' => (int) $balanceAfter,
                'expirationDate' => $point->data_expiracao ? Carbon::parse($point->data_expiracao)->toISOString() : null,
                'status' => $point->status,
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
    /**Metoodo para marcar pontos como excluido no extrato de pontos deve localizar registro pelo pedido_id tambem */
    /**
     * Excluir um ponto do extrato de pontos
     *
     * @param int $pointId id do ponto ou id do pedido
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteFromPointsHistory($pointId)
    {
        // Verificar se o usuário tem permissão para excluir
        $user = request()->user();
        if ($user->permission_id < $this->partner_id) {
            return response()->json([
                'success' => false,
                'message' => 'Permissão insuficiente'
            ], 403);
        }
        // Encontra o ponto pelo ID
        $point = Point::find($pointId);
        //se nao encontrar pelo id, tenta encontrar pelo pedido_id
        if (!$point) {
            $point = Point::where('pedido_id', $pointId)->first();
        }
        if (!$point) {
            return response()->json([
                'success' => false,
                'message' => 'Ponto não encontrado'
            ], 404);
        }

        // Marca o ponto como excluído
        $point->excluido = 's';
        // $point->updated_at = now();
        $point->reg_excluido = ['data' => now()->toDateTimeString(),'excluido_por' => $user->id];
        $point->save();

        return response()->json([
            'success' => true,
            'message' => 'Ponto excluído com sucesso'
        ]);
    }

    /**
     * Obter estatísticas dos extratos de pontos com filtros.
     * Aceita filtros via query string:
     * - `type`: "credito" ou "debito" (opcional)
     * - `dateFrom`/`date_from`: data inicial (YYYY-MM-DD)
     * - `dateTo`/`date_to`: data final (YYYY-MM-DD)
     * As estatísticas respeitam o escopo de permissões do usuário.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPointsExtractsStats(Request $request)
    {
        $user = $request->user();

        try {
            // Total de transações (respeita filtro de tipo quando fornecido)
            $type = $request->get('type'); // "credito", "debito" ou "expired"
            $dateFrom = $request->get('dateFrom') ?? $request->get('date_from');
            $dateTo   = $request->get('dateTo')   ?? $request->get('date_to');
            $search   = $request->get('search');

            // Validar tipo quando informado
            if ($type && !in_array($type, ['credito', 'debito', 'expired', 'expiracao'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parâmetro "type" inválido. Use "credito", "debito" ou "expired".'
                ], 422);
            }

            // Query base considerando permissões e registros não excluídos
            $baseQuery = Point::query()
                ->where('excluido', '!=', 's');

            if ($user && $user->permission_id > $this->partner_id) {
                // Ajustado para 'autor' para manter consistência com o getPointsExtracts
                $baseQuery->where('autor', $user->id);
            }

            // Aplicar filtros de período
            if ($dateFrom) {
                $baseQuery->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
            }
            if ($dateTo) {
                $baseQuery->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
            }

            // Aplica filtro de pesquisa se houver
            if ($search) {
                $baseQuery->where(function($q) use ($search) {
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

            // Se filtro por expired, aplicar na query base (transações ou créditos vencidos)
            $isExpiredFilter = ($type === 'expired' || $type === 'expiracao');
            if ($isExpiredFilter) {
                $baseQuery->where(function($q) {
                    $q->where('tipo', 'expired')
                      ->orWhere('status', 'expirado');
                });
            }

            // Total de transações (respeita filtro de tipo quando fornecido)
            $transactionsQuery = clone $baseQuery;
            if ($type && !$isExpiredFilter) {
                $transactionsQuery->where('tipo', $type);
            }
            $totalTransactions = (int) $transactionsQuery->count();

            // Total de pontos ganhos (crédito)
            if ($type === 'debito') {
                $totalEarned = 0; // escopo filtrado por débito
            } else {
                $earnedQuery = clone $baseQuery;
                if (!$isExpiredFilter) {
                    $earnedQuery->where('tipo', 'credito');
                }
                $totalEarned = (int) $earnedQuery->sum('valor');
            }

            // Total de pontos resgatados (débito)
            if ($type === 'credito' || $isExpiredFilter) {
                $totalRedeemed = 0; // escopo filtrado por crédito ou expirados
            } else {
                $redeemedQuery = clone $baseQuery;
                $redeemedQuery->where('tipo', 'debito');
                $totalRedeemed = abs((int) $redeemedQuery->sum('valor')); // Aplicado abs() para tratar valores negativos
            }

            // Total de pontos expirados (saldo real que expirou = valor - valor_usado)
            if ($type === 'debito') {
                $totalExpired = 0; // não há expiração para débitos
            } elseif ($isExpiredFilter) {
                // Já está filtrado por expirados: soma o saldo que realmente expirou
                $totalExpired = (int) (clone $baseQuery)
                    ->where('tipo', 'expired')
                    ->sum(DB::raw('ABS(valor)'));

                // Fallback: se não há registros tipo 'expired', usa créditos com status='expirado'
                if ($totalExpired === 0) {
                    $totalExpired = (int) (clone $baseQuery)
                        ->where('tipo', 'credito')
                        ->where('status', 'expirado')
                        ->sum(DB::raw('valor - valor_usado'));
                }
            } else {
                // Sem filtro de tipo: soma todos os registros de expiração reais (tipo=expired)
                $expiredQuery = clone $baseQuery;
                $totalExpired = (int) $expiredQuery
                    ->where('tipo', 'expired')
                    ->sum(DB::raw('ABS(valor)'));

                // Fallback: créditos marcados como expirados que não têm registro 'expired' separado
                if ($totalExpired === 0) {
                    $expiredQuery2 = clone $baseQuery;
                    $totalExpired = (int) $expiredQuery2
                        ->where('tipo', 'credito')
                        ->where('status', 'expirado')
                        ->sum(DB::raw('valor - valor_usado'));
                }
            }

            // Usuários ativos (com pelo menos uma transação no escopo)
            $activeUsersQuery = clone $baseQuery;
            if ($type && !$isExpiredFilter) {
                $activeUsersQuery->where('tipo', $type);
            }
            $activeUsers = (int) $activeUsersQuery->distinct('client_id')->count('client_id');

            // Saldo matemático total gerado pelos itens atuais listados
            $balanceQuery = clone $baseQuery;
            if ($type && !$isExpiredFilter) {
                $balanceQuery->where('tipo', $type);
            }
            $totalBalance = (float) $balanceQuery->sum(\Illuminate\Support\Facades\DB::raw("CASE WHEN tipo = 'credito' THEN valor ELSE -ABS(valor) END"));

            // Total de ajustes (origem/descrição sugerindo ajuste)
            $adjustmentsQuery = clone $baseQuery;
            $adjustmentsQuery->where(function ($q) {
                $q->where('origem', 'ajuste')
                  ->orWhere('origem', 'adjustment')
                  ->orWhere('description', 'like', '%ajuste%')
                  ->orWhere('description', 'like', '%adjustment%');
            });
            if ($type) {
                $adjustmentsQuery->where('tipo', $type);
            }
            $totalAdjustments = (int) $adjustmentsQuery->count();

            // Total de reembolsos (origem/descrição sugerindo reembolso)
            $refundsQuery = clone $baseQuery;
            $refundsQuery->where(function ($q) {
                $q->where('origem', 'reembolso')
                  ->orWhere('origem', 'refund')
                  ->orWhere('description', 'like', '%reembolso%')
                  ->orWhere('description', 'like', '%refund%');
            });
            if ($type) {
                $refundsQuery->where('tipo', $type);
            }
            $totalRefunds = (int) $refundsQuery->count();

            $stats = [
                'totalTransactions' => $totalTransactions,
                'totalEarned' => $totalEarned,
                'totalRedeemed' => $totalRedeemed,
                'totalExpired' => $totalExpired,
                'activeUsers' => $activeUsers,
                'totalBalance' => $totalBalance,
                'totalAdjustments' => $totalAdjustments,
                'totalRefunds' => $totalRefunds,
                'filters' => [
                    'type' => $type,
                    'dateFrom' => $dateFrom,
                    'dateTo' => $dateTo,
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);

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
