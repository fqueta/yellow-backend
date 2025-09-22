<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Point;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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

        $query = Point::with(['cliente', 'usuario']);

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
}
