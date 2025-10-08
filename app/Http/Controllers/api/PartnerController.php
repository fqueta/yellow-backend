<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Services\PermissionService;
use App\Services\Qlib;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use function PHPUnit\Framework\isArray;

/**
 * Controller para gerenciamento de parceiros/fornecedores
 * Implementa CRUD completo com soft delete
 */
class PartnerController extends Controller
{
    protected PermissionService $permissionService;
    public $routeName;
    public $sec;
    public $partner_permission_id;

    /**
     * Construtor do controlador
     */
    public function __construct()
    {
        $this->partner_permission_id = Qlib::qoption('permission_partner_id') ?? 5;
        $this->routeName = request()->route()->getName();
        $this->permissionService = new PermissionService();
        $this->sec = request()->segment(3);
    }

    /**
     * Sanitiza dados de entrada removendo caracteres perigosos
     */
    private function sanitizeInput($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }

        if (is_string($data)) {
            return strip_tags(trim($data));
        }

        return $data;
    }

    /**
     * Listar todos os parceiros
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

        $perPage = $request->input('per_page', 10);
        $order_by = $request->input('order_by', 'created_at');
        $order = $request->input('order', 'desc');

        $query = Partner::where('deletado', 'n')->orderBy($order_by, $order);

        // Filtros de busca
        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->input('email') . '%');
        }
        if ($request->filled('cpf')) {
            $query->where('cpf', 'like', '%' . $request->input('cpf') . '%');
        }
        if ($request->filled('cnpj')) {
            $query->where('cnpj', 'like', '%' . $request->input('cnpj') . '%');
        }

        $partners = $query->paginate($perPage);

        // Converter config para array em cada parceiro
        $partners->getCollection()->transform(function ($partner) {
            if (is_string($partner->config)) {
                $configArr = json_decode($partner->config, true) ?? [];
                array_walk($configArr, function (&$value) {
                    if (is_null($value)) {
                        $value = (string)'';
                    }
                });
                $partner->config = $configArr;
            }
            return $partner;
        });

        return response()->json($partners);
    }

    /**
     * Criar um novo parceiro
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('create')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $validator = Validator::make($request->all(), [
            'tipo_pessoa'   => ['required', Rule::in(['pf','pj'])],
            'name'          => 'required|string|max:255',
            'razao'         => 'nullable|string|max:255',
            'cpf'           => 'nullable|string|max:20|unique:users,cpf',
            'cnpj'          => 'nullable|string|max:20|unique:users,cnpj',
            'email'         => 'nullable|email|unique:users,email',
            'password'      => 'nullable|string|min:6',
            'genero'        => ['sometimes', Rule::in(['ni','m','f'])],
            'verificado'    => ['sometimes', Rule::in(['n','s'])],
            'config'        => 'array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'exec'=>false,
                'message' => 'Erro de validação',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Validação extra de CPF
        if (!empty($request->cpf) && !Qlib::validaCpf($request->cpf)) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors'  => ['cpf' => ['CPF inválido']],
            ], 422);
        }

        $validated = $validator->validated();

        // Sanitização dos dados
        $validated = $this->sanitizeInput($validated);

        // Tratar senha se fornecida
        if (isset($validated['password']) && !empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Garantir que permission_id seja sempre 5 (parceiro)
        $validated['permission_id'] = $this->partner_permission_id;
        $validated['deletado'] = 'n';
        $validated['ativo'] = 's';
        // $validated['status'] = 'ativo';
        $validated['autor'] = $user->id;

        // Tratar config se fornecido
        if (isset($validated['config'])) {
            $validated['config'] = $this->sanitizeInput($validated['config']);
            if (isArray($validated['config'])) {
                $validated['config'] = json_encode($validated['config']);
            }
        }

        $ret = [];
        // dd($validated);
        try {
            $partner = Partner::create($validated);
            $ret['data'] = $partner;
            $ret['message'] = 'Parceiro criado com sucesso';
            $ret['status'] = 201;

            return response()->json($ret, 201);
        } catch (\Exception $e) {
            return response()->json([
                'exec'=>false,
                'message' => 'Erro ao criar parceiro: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exibir um parceiro específico
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

        $partner = Partner::findOrFail($id);

        // Converter config para array
        if (is_string($partner->config)) {
            $partner->config = json_decode($partner->config, true) ?? [];
        }

        return response()->json($partner);
    }

    /**
     * Retorna dados do parceiro
     */
    public function can_access(Request $request)
    {
        $user = $request->user();
        if(!$user){
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        return response()->json($user);
    }

    /**
     * Atualizar um parceiro específico
     */
    public function update(Request $request, string $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('edit')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $partnerToUpdate = Partner::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'tipo_pessoa'   => ['sometimes', Rule::in(['pf','pj'])],
            'name'          => 'sometimes|required|string|max:255',
            'razao'         => 'nullable|string|max:255',
            'cpf'           => ['nullable','string','max:20', Rule::unique('users','cpf')->ignore($partnerToUpdate->id)],
            'cnpj'          => ['nullable','string','max:20', Rule::unique('users','cnpj')->ignore($partnerToUpdate->id)],
            'email'         => ['nullable','email', Rule::unique('users','email')->ignore($partnerToUpdate->id)],
            'password'      => 'nullable|string|min:6',
            'genero'        => ['sometimes', Rule::in(['ni','m','f'])],
            'verificado'    => ['sometimes', Rule::in(['n','s'])],
            'config'        => 'array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'exec'=>false,
                'message' => 'Erro de validação',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Validação extra de CPF
        if (!empty($request->cpf) && !Qlib::validaCpf($request->cpf)) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors'  => ['cpf' => ['CPF inválido']],
            ], 422);
        }

        $validated = $validator->validated();

        // Sanitização dos dados
        $validated = $this->sanitizeInput($validated);

        // Tratar senha se fornecida
        if (isset($validated['password']) && !empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Garantir que permission_id seja sempre 5 (parceiro)
        $validated['permission_id'] = $this->partner_permission_id;

        // Tratar config se fornecido
        if (isset($validated['config'])) {
            $validated['config'] = $this->sanitizeInput($validated['config']);
            if (isArray($validated['config'])) {
                $validated['config'] = json_encode($validated['config']);
            }
        }
        // dd($validated);
        $partnerToUpdate->update($validated);

        // Converter config para array na resposta
        if (is_string($partnerToUpdate->config)) {
            $partnerToUpdate->config = json_decode($partnerToUpdate->config, true) ?? [];
        }

        $ret['data'] = $partnerToUpdate;
        $ret['message'] = 'Parceiro atualizado com sucesso';
        $ret['status'] = 200;

        return response()->json($ret);
    }

    /**
     * Mover parceiro para a lixeira
     */
    public function destroy(Request $request, string $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('delete')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $partner = Partner::findOrFail($id);

        // Mover para lixeira em vez de excluir permanentemente
        $partner->update([
            'deletado' => 's',
            'reg_deletado' => json_encode([
                'usuario' => $user->id,
                'nome' => $user->name,
                'created_at' => now(),
            ])
        ]);

        return response()->json([
            'exec'=>true,
            'message' => 'Parceiro movido para a lixeira com sucesso',
            'status' => 200
        ]);
    }

    /**
     * Listar parceiros na lixeira
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

        $perPage = $request->input('per_page', 10);
        $order_by = $request->input('order_by', 'created_at');
        $order = $request->input('order', 'desc');

        $query = Partner::withoutGlobalScope('partner')
            ->where('permission_id', $this->partner_permission_id)
            ->where('deletado', 's')
            ->orderBy($order_by, $order);

        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->input('email') . '%');
        }
        if ($request->filled('cpf')) {
            $query->where('cpf', 'like', '%' . $request->input('cpf') . '%');
        }
        if ($request->filled('cnpj')) {
            $query->where('cnpj', 'like', '%' . $request->input('cnpj') . '%');
        }

        $partners = $query->paginate($perPage);

        // Converter config para array em cada parceiro
        $partners->getCollection()->transform(function ($partner) {
            if (is_string($partner->config)) {
                $configArr = json_decode($partner->config, true) ?? [];
                array_walk($configArr, function (&$value) {
                    if (is_null($value)) {
                        $value = (string)'';
                    }
                });
                $partner->config = $configArr;
            }
            return $partner;
        });

        return response()->json($partners);
    }

    /**
     * Restaurar parceiro da lixeira
     */
    public function restore(Request $request, string $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('delete')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $partner = Partner::withoutGlobalScope('partner')
            ->where('id', $id)
            ->where('deletado', 's')
            ->where('permission_id', $this->partner_permission_id)
            ->firstOrFail();

        $partner->update([
            'deletado' => 'n',
            'reg_deletado' => null
        ]);

        return response()->json([
            'message' => 'Parceiro restaurado com sucesso',
            'status' => 200
        ]);
    }

    /**
     * Excluir parceiro permanentemente
     */
    public function forceDelete(Request $request, string $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('delete')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $partner = Partner::withoutGlobalScope('partner')
            ->where('id', $id)
            ->where('permission_id', $this->partner_permission_id)
            ->firstOrFail();

        $partner->delete();

        return response()->json([
            'message' => 'Parceiro excluído permanentemente',
            'status' => 200
        ]);
    }
}
