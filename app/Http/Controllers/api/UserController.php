<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\Qlib;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use function PHPUnit\Framework\isArray;

class UserController extends Controller
{
    /**
     * Sanitiza os dados recebidos, inclusive arrays como config
     */
    protected function sanitizeInput($input)
    {
        if (is_array($input)) {
            $sanitized = [];
            foreach ($input as $key => $value) {
                $sanitized[$key] = $this->sanitizeInput($value);
            }
            return $sanitized;
        } elseif (is_string($input)) {
            return trim(strip_tags($input));
        }
        return $input;
    }
    protected $permissionService;
    public $routeName;
    public $sec;
    public function __construct()
    {
        $this->routeName = request()->route()->getName();
        $this->permissionService = new PermissionService();
        $this->sec = request()->segment(3);
    }
    /**
     * Listar todos os usuários
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
        //listar usuarios com permissões dele pra cima
        $permission_id = $request->user()->permission_id;
        $query = User::query()
                ->where('permission_id','>=',$permission_id)
                ->where('permission_id','!=',Qlib::qoption('permission_partner_id')??5)
                ->where('permission_id','!=',Qlib::qoption('permission_client_id')??6)
                ->orderBy($order_by,$order);

        // Não exibir registros marcados como deletados ou excluídos
        $query->where(function($q) {
            $q->whereNull('deletado')->orWhere('deletado', '!=', 's');
        });
        $query->where(function($q) {
            $q->whereNull('excluido')->orWhere('excluido', '!=', 's');
        });

        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->input('email') . '%');
        }
        if ($request->filled('cpf')) {
            $query->where('cpf', 'like', '%' . $request->input('cpf') . '%');
        }
        if ($request->filled('cnpj')) {
            $query->where('cnpj', 'like', '%' . $request->input('cnpj') . '%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        // dd($request->input('propertys'));
        if ($request->filled('propertys')) {
            $propertys = $request->input('propertys');
            if(is_array($propertys)){
                $query->where(function($q) use ($propertys){
                    foreach($propertys as $property){
                        $q->orWhere('permission_id','=', $property);
                    }
                });
            }else{
                $query->where('permission_id','<=', Qlib::qoption('permission_partner_id')??5);
            }
        }

        $users = $query->paginate($perPage);
        // Converter config para array em cada usuário
        $users->getCollection()->transform(function ($user) {
            if (is_string($user->config)) {
                $configArr = json_decode($user->config, true) ?? [];
                array_walk($configArr, function (&$value) {
                    if (is_null($value)) {
                        $value = (string)'';
                        // dd($value);
                    }
                    // dump($value);
                });
                $user->config = $configArr;
            }
            return $user;
        });
        // dd($users);
        return response()->json($users);
    }
    /**
     * lista todos os usuario que podem ser proprietários, são usuario do permission_id 1 ate 5
     *
     */
    public function propertys(Request $request)
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if($user->permission_id > Qlib::qoption('permission_partner_id')??5){
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        // if (!$this->permissionService->isHasPermission('view')) {
        //     return response()->json(['error' => 'Acesso negado'], 403);
        // }
        $propertys = User::query()
                ->where('permission_id','<=', Qlib::qoption('permission_partner_id')??5)
                ->where('permission_id','!=', 0)
                ->where('status','=', "actived")
                ->get();
        return response()->json($propertys);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)

    {
        $user = $request->user();
        if(!$user){
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('create')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        // Verifica se já existe usuário deletado com o mesmo CPF
        if (!empty($request->cpf)) {
            $userCpfDel = User::where('cpf', $request->cpf)
                ->where(function($q){
                    $q->where('deletado', 's')->orWhere('excluido', 's');
                })->first();
            if ($userCpfDel) {
                return response()->json([
                    'message' => 'Este cadastro já está em nossa base de dados, verifique na lixeira.',
                    'errors'  => ['cpf' => ['Cadastro com este CPF está na lixeira']],
                ], 422);
            }
        }
        // Verifica se já existe usuário deletado com o mesmo EMAIL
        if (!empty($request->email)) {
            $userEmailDel = User::where('email', $request->email)
                ->where(function($q){
                    $q->where('deletado', 's')->orWhere('excluido', 's');
                })->first();
            if ($userEmailDel) {
                return response()->json([
                    'message' => 'Este cadastro já está em nossa base de dados, verifique na lixeira.',
                    'errors'  => ['email' => ['Cadastro com este e-mail está na lixeira']],
                ], 422);
            }
        }
        $validator = Validator::make($request->all(), [
            'tipo_pessoa'   => ['required', Rule::in(['pf','pj'])],
            'name'          => 'required|string|max:255',
            'razao'         => 'nullable|string|max:255',
            'cpf'           => 'nullable|string|max:20|unique:users,cpf',
            'cnpj'          => 'nullable|string|max:20|unique:users,cnpj',
            'email'         => 'nullable|email|unique:users,email',
            'password'      => 'required|string|min:6',
            // 'status'        => ['required', Rule::in(['actived','inactived','pre_registred'])],
            'genero'        => ['required', Rule::in(['ni','m','f'])],
            // 'verificado'    => ['required', Rule::in(['n','s'])],
            'permission_id' => 'nullable|integer',
            'config'        => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json([
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
        $validated['token'] = Qlib::token();
        $validated['password'] = Hash::make($validated['password']);
        $validated['ativo'] = isset($validated['ativo']) ? $validated['ativo'] : 's';
        $validated['status'] = isset($validated['status']) ? $validated['status'] : 'actived';
        $validated['tipo_pessoa'] = isset($validated['tipo_pessoa']) ? $validated['tipo_pessoa'] : 'pf';
        $validated['permission_id'] = isset($validated['permission_id']) ? $validated['permission_id'] : 5;
        $validated['config'] = isset($validated['config']) ? $this->sanitizeInput($validated['config']) : [];
        if(is_array($validated['config'])){
            $validated['config'] = json_encode($validated['config']);
        }

        $user = User::create($validated);
        $ret['data'] = $user;
        $ret['message'] = 'Usuário criado com sucesso';
        $ret['status'] = 201;
        return response()->json($ret, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('view')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        $user = User::findOrFail($id);
        return response()->json($user,201);
    }
    /**
     * retorna dados do usuario
     */
    public function can_access(Request $request)
    {
        $user = $request->user();
        // dd($user);
        $pc = new PointController();
        $saldo = $pc->saldo($user->id);
        $user->points = $saldo;
        $user->avatar = $user->config['avatar'] ?? null;
        if(!$user){
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        return response()->json($user);
    }
    public function perfil(Request $request)
    {
        $user = $request->user();
        if(!$user){
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        $pc = new PointController();
        $saldo = $pc->saldo($user->id);
        $user->points = $saldo;
        $user->avatar = $user->config['avatar'] ?? null;

        return response()->json($user);
    }

     /**
     * Lista usuários marcados como deletados/excluídos (lixeira)
     */
    public function trash(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $query = User::query();
        $query->where(function($q) {
            $q->where('deletado', 's')->orWhere('excluido', 's');
        });

        // Filtros opcionais
        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->input('email') . '%');
        }
        if ($request->filled('cpf')) {
            $query->where('cpf', 'like', '%' . $request->input('cpf') . '%');
        }
        if ($request->filled('cnpj')) {
            $query->where('cnpj', 'like', '%' . $request->input('cnpj') . '%');
        }

        $users = $query->paginate($perPage);
        return response()->json($users);
    }

    /**
     * Update the specified resource in storage.
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

        $userToUpdate = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'tipo_pessoa'   => ['sometimes', Rule::in(['pf','pj'])],
            'name'          => 'sometimes|required|string|max:255',
            'razao'         => 'nullable|string|max:255',
            'cpf'           => ['nullable','string','max:20', Rule::unique('users','cpf')->ignore($userToUpdate->id)],
            'cnpj'          => ['nullable','string','max:20', Rule::unique('users','cnpj')->ignore($userToUpdate->id)],
            'email'         => ['nullable','email', Rule::unique('users','email')->ignore($userToUpdate->id)],
            'password'      => 'nullable|string|min:6',
            // 'status'        => ['sometimes', Rule::in(['actived','inactived','pre_registred'])],
            'genero'        => ['sometimes', Rule::in(['ni','m','f'])],
            'verificado'    => ['sometimes', Rule::in(['n','s'])],
            'permission_id' => 'nullable|integer',
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
                'exec' => false,
                'message' => 'Erro de validação',
                'errors'  => ['cpf' => ['CPF inválido']],
            ], 422);
        }

        $validated = $validator->validated();
        // dd($validated,$request->all());
        // Sanitização dos dados
        $validated = $this->sanitizeInput($validated);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if (isset($validated['config']) && is_array($validated['config'])) {
            $validated['config'] = json_encode($validated['config']);
        }

        $userToUpdate->update($validated);

        return response()->json([
            'exec' => true,
            'data' => $userToUpdate,
            'message' => 'Usuário atualizado com sucesso',
            'status' => 200,
        ]);
    }

    /**
     * Remove the specified resource from storage.
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
        $userToDelete = User::find($id);
        if (!$userToDelete) {
            return response()->json(['error' => 'Usuário não encontrado'], 404);
        }
        $userToDelete->update([
            'excluido'     => 's',
            'deletado'     => 's',
            'reg_deletado' =>['data'=>now()->toDateTimeString(),'user_id'=>request()->user()->id] ,
        ]);
        return response()->json([
            'message' => 'Usuário marcado como deletado com sucesso'
        ], 200);
    }

    /**
     * Atualiza a senha do usuário
     */
    public function changePassword(Request $request)
    {
        // Validação dos dados recebidos
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Dados inválidos',
                'messages' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        // Verificar se a senha atual está correta
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'error' => 'Senha atual incorreta'
            ], 422);
        }

        // Atualizar a senha
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Senha atualizada com sucesso'
        ], 200);
    }

    /**
     * Atualiza o perfil do usuário salvando os dados na tabela usermeta
     */
    public function updateProfile(Request $request)
    {
        // dd($request->all());
        // Validação dos dados recebidos
        $validator = Validator::make($request->all(), [
            'company' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Dados inválidos',
                'messages' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $data = $validator->validated();
        $results = [];
        $errors = [];

        // Salva cada campo como um meta campo separado na tabela usermeta
        foreach ($data as $key => $value) {
            if ($value !== null && $key != 'name' && $key != 'email') {
                $result = Qlib::update_usermeta($user->id, 'profile_' . $key, $value);
                if ($result) {
                    $results[$key] = 'Atualizado com sucesso';
                } else {
                    $errors[$key] = 'Erro ao atualizar';
                }
            }
        }

        if (empty($errors)) {
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->save();
            $newData = User::find($user->id);
            // $newData->load('profile');
            return response()->json([
                'message' => 'Perfil atualizado com sucesso',
                'data' => $newData
            ], 200);
        } else {
            return response()->json([
                'message' => 'Alguns campos não foram atualizados',
                'data' => $results,
                'errors' => $errors
            ], 207); // 207 Multi-Status
        }
    }
}
