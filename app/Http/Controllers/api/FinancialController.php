<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Financial;
use App\Services\PermissionService;
use App\Services\Qlib;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Controller para gerenciar monitorações financeiras
 * Inclui contas a receber e contas a pagar
 */
class FinancialController extends Controller
{
    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
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
     * Listar todas as movimentações financeiras
     */
    public function index(Request $request)
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('view')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $query = Financial::with(['cliente', 'fornecedor', 'usuario']);

        // Filtros
        if ($request->has('tipo') && in_array($request->tipo, ['receber', 'pagar'])) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->has('status') && in_array($request->status, ['pendente', 'pago', 'parcial', 'vencido', 'cancelado'])) {
            $query->where('status', $request->status);
        }

        if ($request->has('mes') && $request->has('ano')) {
            $query->whereMonth('data_vencimento', $request->mes)
                  ->whereYear('data_vencimento', $request->ano);
        }

        if ($request->has('vencidas') && $request->vencidas == 'true') {
            $query->vencidas();
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                  ->orWhere('descricao', 'like', "%{$search}%")
                  ->orWhere('documento', 'like', "%{$search}%");
            });
        }

        // Ordenação
        $orderBy = $request->get('order_by', 'data_vencimento');
        $orderDirection = $request->get('order_direction', 'asc');
        $query->orderBy($orderBy, $orderDirection);

        // Paginação
        $perPage = $request->get('per_page', 15);
        $financials = $query->paginate($perPage);

        // Converter config para array
        $financials->getCollection()->transform(function ($financial) {
            if (is_string($financial->config)) {
                $financial->config = json_decode($financial->config, true) ?? [];
            }
            return $financial;
        });

        return response()->json($financials);
    }

    /**
     * Criar nova movimentação financeira
     */
    public function store(Request $request)
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('create')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $data = $request->all();
        $data = $this->sanitizeInput($data);

        // Validação
        $validator = Validator::make($data, [
            'titulo' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'tipo' => 'required|in:receber,pagar',
            'categoria' => 'nullable|in:receita,despesa,transferencia',
            'valor' => 'required|numeric|min:0.01',
            'valor_pago' => 'nullable|numeric|min:0',
            'desconto' => 'nullable|numeric|min:0',
            'juros' => 'nullable|numeric|min:0',
            'multa' => 'nullable|numeric|min:0',
            'data_vencimento' => 'required|date',
            'data_pagamento' => 'nullable|date',
            'data_competencia' => 'nullable|date',
            'status' => 'nullable|in:pendente,pago,parcial,vencido,cancelado',
            'parcela_atual' => 'nullable|integer|min:1',
            'total_parcelas' => 'nullable|integer|min:1',
            'cliente_id' => 'nullable|string',
            'fornecedor_id' => 'nullable|string',
            'usuario_id' => 'nullable|string',
            'documento' => 'nullable|string|max:255',
            'forma_pagamento' => 'nullable|string|max:255',
            'conta_bancaria' => 'nullable|string|max:255',
            'config' => 'nullable|array',
        ], [
            'titulo.required' => 'O campo título é obrigatório',
            'tipo.required' => 'O campo tipo é obrigatório',
            'tipo.in' => 'O tipo deve ser "receber" ou "pagar"',
            'valor.required' => 'O campo valor é obrigatório',
            'valor.min' => 'O valor deve ser maior que zero',
            'data_vencimento.required' => 'A data de vencimento é obrigatória',
            'data_vencimento.date' => 'A data de vencimento deve ser uma data válida',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Definir valores padrão
        $validated['status'] = $validated['status'] ?? 'pendente';
        $validated['parcela_atual'] = $validated['parcela_atual'] ?? 1;
        $validated['total_parcelas'] = $validated['total_parcelas'] ?? 1;
        $validated['valor_pago'] = $validated['valor_pago'] ?? 0;
        $validated['desconto'] = $validated['desconto'] ?? 0;
        $validated['juros'] = $validated['juros'] ?? 0;
        $validated['multa'] = $validated['multa'] ?? 0;
        $validated['autor'] = $user->id;
        $validated['ativo'] = 's';
        $validated['excluido'] = 'n';
        $validated['deletado'] = 'n';

        // Tratar config se fornecido
        if (isset($validated['config']) && is_array($validated['config'])) {
            $validated['config'] = json_encode($validated['config']);
        }

        $financial = Financial::create($validated);

        // Converter config para array na resposta
        if (is_string($financial->config)) {
            $financial->config = json_decode($financial->config, true) ?? [];
        }

        $financial->load(['cliente', 'fornecedor', 'usuario']);

        return response()->json([
            'data' => $financial,
            'message' => 'Movimentação financeira criada com sucesso',
            'status' => 201
        ], 201);
    }

    /**
     * Exibir uma movimentação financeira específica
     */
    public function show(Request $request, string $id)
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('view')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $financial = Financial::with(['cliente', 'fornecedor', 'usuario'])->findOrFail($id);

        // Converter config para array
        if (is_string($financial->config)) {
            $financial->config = json_decode($financial->config, true) ?? [];
        }

        return response()->json($financial);
    }

    /**
     * Atualizar uma movimentação financeira
     */
    public function update(Request $request, string $id)
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('edit')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $financial = Financial::findOrFail($id);
        $data = $request->all();
        $data = $this->sanitizeInput($data);

        // Validação
        $validator = Validator::make($data, [
            'titulo' => 'sometimes|required|string|max:255',
            'descricao' => 'nullable|string',
            'tipo' => 'sometimes|required|in:receber,pagar',
            'categoria' => 'nullable|in:receita,despesa,transferencia',
            'valor' => 'sometimes|required|numeric|min:0.01',
            'valor_pago' => 'nullable|numeric|min:0',
            'desconto' => 'nullable|numeric|min:0',
            'juros' => 'nullable|numeric|min:0',
            'multa' => 'nullable|numeric|min:0',
            'data_vencimento' => 'sometimes|required|date',
            'data_pagamento' => 'nullable|date',
            'data_competencia' => 'nullable|date',
            'status' => 'nullable|in:pendente,pago,parcial,vencido,cancelado',
            'parcela_atual' => 'nullable|integer|min:1',
            'total_parcelas' => 'nullable|integer|min:1',
            'cliente_id' => 'nullable|string',
            'fornecedor_id' => 'nullable|string',
            'usuario_id' => 'nullable|string',
            'documento' => 'nullable|string|max:255',
            'forma_pagamento' => 'nullable|string|max:255',
            'conta_bancaria' => 'nullable|string|max:255',
            'config' => 'nullable|array',
        ], [
            'titulo.required' => 'O campo título é obrigatório',
            'tipo.required' => 'O campo tipo é obrigatório',
            'tipo.in' => 'O tipo deve ser "receber" ou "pagar"',
            'valor.required' => 'O campo valor é obrigatório',
            'valor.min' => 'O valor deve ser maior que zero',
            'data_vencimento.required' => 'A data de vencimento é obrigatória',
            'data_vencimento.date' => 'A data de vencimento deve ser uma data válida',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Tratar config se fornecido
        if (isset($validated['config']) && is_array($validated['config'])) {
            $validated['config'] = json_encode($validated['config']);
        }

        $financial->update($validated);

        // Converter config para array na resposta
        if (is_string($financial->config)) {
            $financial->config = json_decode($financial->config, true) ?? [];
        }

        $financial->load(['cliente', 'fornecedor', 'usuario']);

        return response()->json([
            'data' => $financial,
            'message' => 'Movimentação financeira atualizada com sucesso',
            'status' => 200
        ]);
    }

    /**
     * Remover uma movimentação financeira (soft delete)
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

        $financial = Financial::findOrFail($id);
        $financial->delete();

        return response()->json([
            'message' => 'Movimentação financeira removida com sucesso',
            'status' => 200
        ]);
    }

    /**
     * Listar movimentações financeiras na lixeira
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

        $query = Financial::onlyTrashed()->with(['cliente', 'fornecedor', 'usuario']);

        // Filtros
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                  ->orWhere('descricao', 'like', "%{$search}%")
                  ->orWhere('documento', 'like', "%{$search}%");
            });
        }

        // Ordenação
        $orderBy = $request->get('order_by', 'deleted_at');
        $orderDirection = $request->get('order_direction', 'desc');
        $query->orderBy($orderBy, $orderDirection);

        // Paginação
        $perPage = $request->get('per_page', 15);
        $financials = $query->paginate($perPage);

        // Converter config para array
        $financials->getCollection()->transform(function ($financial) {
            if (is_string($financial->config)) {
                $financial->config = json_decode($financial->config, true) ?? [];
            }
            return $financial;
        });

        return response()->json($financials);
    }

    /**
     * Restaurar uma movimentação financeira da lixeira
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

        $financial = Financial::onlyTrashed()->findOrFail($id);
        $financial->restore();

        return response()->json([
            'message' => 'Movimentação financeira restaurada com sucesso',
            'status' => 200
        ]);
    }

    /**
     * Excluir permanentemente uma movimentação financeira
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

        $financial = Financial::onlyTrashed()->findOrFail($id);
        $financial->forceDelete();

        return response()->json([
            'message' => 'Movimentação financeira excluída permanentemente',
            'status' => 200
        ]);
    }

    /**
     * Marcar como pago/recebido
     */
    public function markAsPaid(Request $request, string $id)
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('edit')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $financial = Financial::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'valor_pago' => 'nullable|numeric|min:0',
            'data_pagamento' => 'nullable|date',
            'forma_pagamento' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }

        $valorPago = $request->valor_pago ?? $financial->valor;
        $dataPagamento = $request->data_pagamento ?? now()->format('Y-m-d');
        
        $financial->update([
            'valor_pago' => $valorPago,
            'data_pagamento' => $dataPagamento,
            'status' => $valorPago >= $financial->valor ? 'pago' : 'parcial',
            'forma_pagamento' => $request->forma_pagamento ?? $financial->forma_pagamento,
        ]);

        $financial->load(['cliente', 'fornecedor', 'usuario']);

        return response()->json([
            'data' => $financial,
            'message' => 'Status de pagamento atualizado com sucesso',
            'status' => 200
        ]);
    }

    /**
     * Relatório financeiro resumido
     */
    public function summary(Request $request)
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('view')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $mes = $request->get('mes', now()->month);
        $ano = $request->get('ano', now()->year);

        $query = Financial::whereMonth('data_vencimento', $mes)
                         ->whereYear('data_vencimento', $ano);

        $summary = [
            'contas_receber' => [
                'total' => $query->clone()->contasReceber()->sum('valor'),
                'pago' => $query->clone()->contasReceber()->sum('valor_pago'),
                'pendente' => $query->clone()->contasReceber()->where('status', 'pendente')->sum('valor'),
                'vencidas' => $query->clone()->contasReceber()->vencidas()->sum('valor'),
            ],
            'contas_pagar' => [
                'total' => $query->clone()->contasPagar()->sum('valor'),
                'pago' => $query->clone()->contasPagar()->sum('valor_pago'),
                'pendente' => $query->clone()->contasPagar()->where('status', 'pendente')->sum('valor'),
                'vencidas' => $query->clone()->contasPagar()->vencidas()->sum('valor'),
            ],
            'saldo' => 0,
        ];

        $summary['saldo'] = $summary['contas_receber']['pago'] - $summary['contas_pagar']['pago'];

        return response()->json($summary);
    }
}