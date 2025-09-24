<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\api\AlloyalController;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\PermissionService;
use App\Services\Qlib;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use function PHPUnit\Framework\isArray;
use App\Http\Controllers\api\PointController;

class ClientController extends Controller
{
    protected PermissionService $permissionService;
    public $routeName;
    public $sec;
    public $cliente_permission_id;
    public function __construct()
    {
        $this->cliente_permission_id = Qlib::qoption('cliente_permission_id')??5;
        $this->routeName = request()->route()->getName();
        $this->permissionService = new PermissionService();
        $this->sec = request()->segment(3);
    }

    /**
     * Listar todos os clientes
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

        $query = Client::query()->orderBy($order_by, $order);

        // Não exibir registros marcados como deletados ou excluídos
        $query->where(function($q) {
            $q->whereNull('deletado')->orWhere('deletado', '!=', 's');
        });
        $query->where(function($q) {
            $q->whereNull('excluido')->orWhere('excluido', '!=', 's');
        });

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->input('email') . '%');
        }
        if ($request->filled('cpf')) {
            $query->where('cpf', 'like', '%' . $request->input('cpf') . '%');
        }
        if ($request->filled('cnpj')) {
            $query->where('cnpj', 'like', '%' . $request->input('cnpj') . '%');
        }

        $clients = $query->paginate($perPage);

        // Converter config para array em cada cliente
        $clients->getCollection()->transform(function ($client) {
            if (is_string($client->config)) {
                $configArr = json_decode($client->config, true) ?? [];
                array_walk($configArr, function (&$value) {
                    if (is_null($value)) {
                        $value = (string)'';
                    }
                });
                $client->config = $configArr;
            }
            return $client;
        });
        if($request->segment(4) == 'registred'){
            $ret = $clients->getCollection()->map(function ($client) {
                return $this->map_client($client);
            });
            return $ret;
        }
        return response()->json($clients);
    }
    public function map_client($client)
    {
        if(is_array($client)){
            $client = (object)$client;
        }
        // $pc = new PointController();
        // $points = $pc->saldo($client->id);
        return [
            'id' => $client->id,
            'name' => $client->name,
            'cpf' => $client->cpf,
            'email' => $client->email,
            'status' => $client->status,
            // 'saldo' => Qlib::get_usermeta($client->id,'saldo'),
            'created_at' => $client->created_at,
            'updated_at' => $client->updated_at,
        ];
    }
    /**
     * Sanitiza os dados de entrada
     */
    private function sanitizeInput($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = $this->sanitizeInput($value);
                } elseif (is_string($value)) {
                    $data[$key] = trim($value);
                }
            }
        } elseif (is_string($data)) {
            $data = trim($data);
        }
        return $data;
    }

    /**
     * Verificar permissões de acesso
     */
    private function checkPermissions($permission = 'create')
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission($permission)) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        return null;
    }
    /**
     * Verificar se é um usuário do Mileto
     */
    public function isMiletoUser($client_id)
    {
        $client = Qlib::get_usermeta($client_id,'is_mileto_user');
        if ($client) {
            return $client ?? false;
        }
        return false;
    }
    /**
     * Verificar se cliente já existe na lixeira
     */
    private function checkClientInTrash(Request $request)
    {
        // Verificar se o email já existe na lixeira
        if ($request->filled('email')) {
            $existingUser = Client::withoutGlobalScope('client')
                ->where('email', $request->email)
                ->where(function($q) {
                    $q->where('deletado', 's')->orWhere('excluido', 's');
                })
                ->first();

            if ($existingUser) {
                return response()->json([
                    'message' => 'Este cadastro já está em nossa base de dados, verifique na lixeira.',
                    'errors'  => ['email' => ['Cadastro com este e-mail está na lixeira']],
                ], 422);
            }
        }

        // Verificar se o CPF ou CNPJ já existe na lixeira
        if ($request->filled('cpf') || $request->filled('cnpj')) {
            $existingUser = Client::withoutGlobalScope('client')
                // ->where(function($q) use ($request) {
                //     $q->where('cpf', $request->cpf)->orWhere('cnpj', $request->cnpj);
                // })
                // ->where(function($q) {
                //     $q->where('deletado', 's')->orWhere('excluido', 's');
                // })
                ->where('cpf', $request->cpf)
                ->where('excluido', 's')
                ->first();
                if ($existingUser) {
                    $is_mileto_user = $this->isMiletoUser($existingUser->id);
                    if($is_mileto_user){
                        $name = $existingUser->nome;
                        dd($name,$is_mileto_user);
                        // return response()->json([
                            //     'message' => 'Este cadastro já está em nossa base de dados, verifique na lixeira.',
                            //     'errors'  => ['cpf' => ['Cadastro com este CPF está na lixeira']],
                            // ], 422);
                        }else{
                            // dd($is_mileto_user);
                            return response()->json([
                                'message' => 'Este cadastro já está em nossa base de dados, verifique na lixeira.',
                                'errors'  => ['cpf' => ['Cadastro com este CPF está na lixeira '.$existingUser->cpf]],
                    ], 422);
                }
            }
        }


        return null;
    }

    /**
     * Preparar dados padrão do cliente
     */
    private function prepareClientData($validated, $status = 'actived')
    {
        $validated = $this->sanitizeInput($validated);
        $validated['token'] = Qlib::token();

        if(isset($validated['password'])){
            $validated['password'] = Hash::make($validated['password']);
        }
        if(isset($validated['cpf'])){
            $validated['cpf'] = str_replace(['.','-'],'',$validated['cpf']);
        }
        $validated['ativo'] = isset($validated['ativo']) ? $validated['ativo'] : 's';
        $validated['status'] = $status;
        $validated['tipo_pessoa'] = isset($validated['tipo_pessoa']) ? $validated['tipo_pessoa'] : 'pf';
        $validated['permission_id'] = $this->cliente_permission_id;
        $validated['config'] = isset($validated['config']) ? $this->sanitizeInput($validated['config']) : [];

        if(isset($validated['config']) && is_array($validated['config'])){
            $validated['config'] = json_encode($validated['config']);
        }

        return $validated;
    }
    /**
     * Processar apenas pontos (método PUT) usar para ativar o cliente inavido
     */
    private function processPointsOnly(Request $request)
    {
        // Validar apenas os campos necessários para pontos
        $validator = Validator::make($request->all(), [
            'cpf' => 'required|string|max:20',
            'points' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'exec' => false,
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Buscar cliente pelo CPF
        $cpf = str_replace(['.','-'],'',$request->cpf);
        $client = Client::where('cpf', $cpf)
            // ->where('status', 'actived')
            ->first();
        if (!$client) {
            return response()->json([
                'exec' => false,
                'message' => 'Cliente não encontrado',
                'errors' => ['cpf' => ['Cliente com este CPF não foi encontrado']],
            ], 404);
        }
        $message = '';
        //Verificar se o cliente estiver inativo ativar
        if($client->status == 'inactived'){
            $ativar = $this->activate($client->cpf,'array');
            if($ativar['exec']){
                $message = 'Cliente ativado com sucesso';
                $client = Client::where('cpf', $cpf)
                    ->where('status', 'actived')
                    ->first();
            }else{
                $message = $ativar['message'] ?? 'Erro ao ativar cliente';
                return response()->json([
                    'exec' => false,
                    'data' => $ativar,
                    'message' => $message,
                    'errors' => ['cpf' => [$message]],
                ], 422);
            }
        }
        // Processar pontos
        $pontos = $request->points;
        $ret = [];

        if($pontos > 0){
            $pc = new PointController();
            $data = [
                'valor' => $pontos,
                'tipo' => 'credito',
                'client_id' => $client->id,
            ];

            $savePoints = $pc->createOrUpdate($data);
            $identificador = uniqid();//$savePoints['id'] ?? 0;

            $ret['identificador'] = Qlib::update_usermeta($client->id,'id_points',$identificador);
            $ret['points'] = $savePoints;

            if($savePoints){
                //enviar deposito para alloyal
                // $ret['deposit'] = (new AlloyalController)->fazer_deposito([
                //     'cpf'=>$client->cpf,
                //     'client_id'=>$client->id,
                //     'description'=>'Depósito via API'
                // ]);
                $link_active_cad = Qlib::qoption('link_active_cad');
                $link_active_cad = str_replace('{cpf}', $client->cpf, $link_active_cad);
                $ret['link_active_cad'] = $link_active_cad;
                $ret['cpf'] = $client->cpf;
            }
        }
        $ret['exec'] = true;
        $ret['message'] = $message.' Pontos processados com sucesso';
        $ret['status'] = 200;

        return response()->json($ret, 200);
    }

    /**
     * Processar pontos do cliente
     */
    private function processClientPoints($client, $pontos)
    {
        $ret = [];

        if($pontos > 0 && isset($client->id)){
            $pc = new PointController();
            $data = [
                'valor' => $pontos,
                'tipo' => 'credito',
                'client_id' => $client->id,
            ];

            $savePoints = $pc->createOrUpdate($data);
            $identificador = uniqid();//$savePoints['id'] ?? 0;

            $ret['identificador'] = Qlib::update_usermeta($client->id,'id_points',$identificador);
            $ret['points'] = $savePoints;

            if($savePoints){
                 //enviar deposito para alloyal
                // $ret['deposit'] = (new AlloyalController)->fazer_deposito([
                //     'cpf'=>$client->cpf,
                //     'client_id'=>$client->id,
                //     'description'=>'Depósito via API'
                // ]);
                $link_active_cad = Qlib::qoption('link_active_cad');
                $link_active_cad = str_replace('{cpf}', $client->cpf, $link_active_cad);
                $ret['link_active_cad'] = $link_active_cad;
                $ret['cpf'] = $client->cpf;
            }
        }

        return $ret;
    }

    /**
     * Validar CPF
     */
    private function validateCpf($cpf)
    {
        if (!empty($cpf) && !Qlib::validaCpf($cpf)) {
            return response()->json([
                'exec' => false,
                'message' => 'Erro de validação',
                'errors'  => ['cpf' => ['CPF inválido']],
            ], 422);
        }
        return null;
    }

    /**
     * Criar um novo cliente (pré-cadastro) ou processar pontos (PUT)
     */
    public function pre_registred(Request $request)
    {
        // Verificar permissões
        $type_permission = 'create';
        if ($request->isMethod('GET')) {
            $type_permission = 'view';
        }
        if ($request->isMethod('PUT')) {
            $type_permission = 'edit';
        }
        $permissionCheck = $this->checkPermissions($type_permission);
        if ($permissionCheck) return $permissionCheck;

        // Se for requisição PUT, processar apenas pontos
        if ($request->isMethod('GET')) {
            $clients = $this->index($request);
            return response()->json($clients);
        }
        if ($request->isMethod('PUT')) {
            return $this->processPointsOnly($request);
        }
        $cpf = str_replace(['.','-'],'',$request->cpf);
        // Verificar se cliente já existe na lixeira (apenas para POST)
        $trashCheck = $this->checkClientInTrash($request);
        if ($trashCheck) return $trashCheck;

        // Preparar dados específicos do pré-cadastro
        $request->merge([
            'tipo_pessoa' => 'pf',
            'genero' => $request->get('genero') ? $request->get('genero') : 'ni',
            'name' => $request->get('name') ? $request->get('name') : 'Pre cadastro '.$request->get('cpf'),
            'status' => 'pre_registred',
            'cpf' => $cpf,
        ]);
        //verifica se o CPF ja Existe
        $clientCheck = Client::where('cpf', $request->cpf)->first();
        if($clientCheck){
            return response()->json([
                'exec' => false,
                'message' => 'CPF já existe',
                'errors'  => ['cpf' => ['CPF já existe']],
            ], 422);
        }
        // Validação específica para pré-cadastro
        $validator = Validator::make($request->all(), [
            'tipo_pessoa'   => ['required', Rule::in(['pf','pj'])],
            'name'          => 'required|string|max:255',
            'points'        => 'nullable|string|max:255',
            'cpf'           => 'nullable|string|max:20|unique:users,cpf',
            'genero'        => ['required', Rule::in(['ni','m','f'])],
            'status'        => ['required', Rule::in(['actived','inactived','pre_registred'])],
        ]);
        // Extrair pontos antes da validação
        $pontos = 0;
        if(isset($request->points)){
            $pontos = $request->points;
            unset($request->points);
        }

        if ($validator->fails()) {
            return response()->json([
                'exec' => false,
                'message' => 'Erro de validação',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Validar CPF
        $cpfValidation = $this->validateCpf($request->cpf);
        if ($cpfValidation) return $cpfValidation;

        // Preparar e criar cliente
        $validated = $validator->validated();
        $clientData = $this->prepareClientData($validated, 'pre_registred');
        $client = Client::create($clientData);

        // Processar pontos se fornecidos
        $pointsResult = $this->processClientPoints($client, $pontos);

        // Preparar resposta
        $ret = array_merge($pointsResult, [
            'exec' => true,
            'message' => 'Cliente criado com sucesso',
            'status' => 201,
        ]);

        return response()->json($ret, 201);
    }

    /**
     * Criar um novo cliente (cadastro completo)
     */
    public function store(Request $request)
    {
        // Verificar permissões
        $permissionCheck = $this->checkPermissions('create');
        if ($permissionCheck) return $permissionCheck;

        // Verificar se cliente já existe na lixeira
        $trashCheck = $this->checkClientInTrash($request);
        if ($trashCheck) return $trashCheck;

        // Preparar dados padrão
        $request->merge([
            'tipo_pessoa' => $request->get('tipo_pessoa') ? $request->get('tipo_pessoa') : 'pf',
            'genero' => $request->get('genero') ? $request->get('genero') : 'ni',
        ]);

        // Validação para cadastro completo
        $validator = Validator::make($request->all(), [
            'tipo_pessoa'   => ['required', Rule::in(['pf','pj'])],
            'name'          => 'required|string|max:255',
            'razao'         => 'nullable|string|max:255',
            'cpf'           => 'nullable|string|max:20|unique:users,cpf',
            'cnpj'          => 'nullable|string|max:20|unique:users,cnpj',
            'email'         => 'nullable|email|unique:users,email',
            'password'      => 'nullable|string|min:6',
            'genero'        => ['required', Rule::in(['ni','m','f'])],
            'config'        => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Validar CPF
        $cpfValidation = $this->validateCpf($request->cpf);
        if ($cpfValidation) return $cpfValidation;

        // Preparar e criar cliente
        $validated = $validator->validated();
        $clientData = $this->prepareClientData($validated, 'actived');
        $client = Client::create($clientData);

        return response()->json([
            'data' => $client,
            'message' => 'Cliente criado com sucesso',
            'status' => 201,
        ], 201);
    }
    public function store_active(Request $request)
    {
        $cpf = str_replace(['.','-'],'',$request->cpf);
        $request->merge(['cpf' => $cpf]);
        $client = Client::where('cpf', $cpf)->first();
        // dd($request->all());
        if (!$client) {
            return response()->json(['error' => 'Cliente não encontrado ou invalido'], 404);
        }
        // if($client->status == 'actived'){
        //     return response()->json(['message' => 'Cliente já está cadastrado e ativado'], 422);
        // }
        $data_update = $request->all();
        /**Sanitizar dados antes de atualizar */
        $data_update = $this->sanitizeInput($data_update);
        /**Validar os campos antes de atualizar */
        $validator = Validator::make($data_update, [
            'cpf'           => ['nullable','string','max:20', Rule::unique('users','cpf')->ignore($client->id)],
            'cnpj'          => ['nullable','string','max:20', Rule::unique('users','cnpj')->ignore($client->id)],
            'email'         => ['nullable','email', Rule::unique('users','email')->ignore($client->id)],
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:6',
        ],[
            'email.required' => 'O campo e-mail é obrigatório',
            'email.email' => 'O campo e-mail deve ser um endereço de e-mail válido',
            'email.unique' => 'O e-mail já está em uso',
            'name.required' => 'O campo nome é obrigatório',
            'password.required' => 'O campo senha é obrigatório',
            'password.min' => 'A senha deve ter pelo menos 6 caracteres',
        ]);
        // dd($client->id);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors'  => $validator->errors(),
            ], 422);
        }
        //Gavar senha sem hash em uma variavel temporaria
        $d_salvar = $validator->validated();
        $password = $d_salvar['password'];
        if(isset($d_salvar['password'])){
            $d_salvar['password'] = Hash::make($d_salvar['password']);
        }
        $d_salvar['status'] = 'actived';
        try {
            //code...
            //Enviar cadastro para API da alloyal
            $client->update($d_salvar);
            $d_send_api = $d_salvar;
            $d_send_api['password'] = $password;
            $d_send_api['id'] = $client->id;
            $d_send_api['cpf'] = $client->cpf;
            $ret['success'] = $this->sendCadastroToAlloyal($d_send_api);
            $ret['message'] = 'Cliente ativado com sucesso';
            $ret['status'] = 200;
            return response()->json($ret, 200);
        } catch (\Throwable $th) {
            $ret['message'] = 'Erro ao ativar cliente';
            $ret['status'] = 500;
            $ret['erro'] = $th->getMessage();
            return response()->json($ret, 500);
        }
    }
    public function sendCadastroToAlloyal($data_client=[]){
        $client_id = isset($data_client['id']) ? $data_client['id'] : null;
        // dd($data_client);
        $ret['exec'] = false;
        $response = (new AlloyalController())->create_user_atived([
            'name' => $data_client['name'],
            'cpf' => $data_client['cpf'],
            'email' => $data_client['email'],
            'password' => $data_client['password'],
        ],$client_id);
        // dd($response);
        $ret['data'] = isset($response['data']) ? $response['data'] : null;
        if(!isset($ret['data']['id']) && empty($ret['data'])){
            $ret = $response;
            return $ret;
        }
        // dd($response);
        if($response['exec']){
            // $client = Client::findOrFail($client_id);
            // $client->update(['config' => ['alloyal_id'=>$response['data']['id']]]);
            $ret['exec'] = true;
        }
        return $ret;
    }
    /**
     * Exibir um cliente específico
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

        $client = Client::findOrFail($id);

        // Converte config para array se necessário
        if (is_string($client->config)) {
            $client->config = json_decode($client->config, true) ?? [];
        }

        return response()->json($client);
    }

    /**
     * Retorna dados do cliente
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
     * Atualizar um cliente específico
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

        $clientToUpdate = Client::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'tipo_pessoa'   => ['sometimes', Rule::in(['pf','pj'])],
            'name'          => 'sometimes|required|string|max:255',
            'razao'         => 'nullable|string|max:255',
            'cpf'           => ['nullable','string','max:20', Rule::unique('users','cpf')->ignore($clientToUpdate->id)],
            'cnpj'          => ['nullable','string','max:20', Rule::unique('users','cnpj')->ignore($clientToUpdate->id)],
            'email'         => ['nullable','email', Rule::unique('users','email')->ignore($clientToUpdate->id)],
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

        // Garantir que permission_id seja sempre 5 (cliente)
        $validated['permission_id'] = $this->cliente_permission_id;

        // Tratar config se fornecido
        if (isset($validated['config'])) {
            $validated['config'] = $this->sanitizeInput($validated['config']);
            if (isArray($validated['config'])) {
                $validated['config'] = json_encode($validated['config']);
            }
        }

        $clientToUpdate->update($validated);

        // Converter config para array na resposta
        if (is_string($clientToUpdate->config)) {
            $clientToUpdate->config = json_decode($clientToUpdate->config, true) ?? [];
        }

        $ret['data'] = $clientToUpdate;
        $ret['message'] = 'Cliente atualizado com sucesso';
        $ret['status'] = 200;

        return response()->json($ret, 200);
    }

    /**
     * Remover um cliente específico
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

        $client = Client::findOrFail($id);

        // Mover para lixeira em vez de excluir permanentemente
        $client->update([
            'deletado' => 's',
            'reg_deletado' => json_encode([
                'usuario' => $user->id,
                'nome' => $user->name,
                'created_at' => now(),
            ])
        ]);

        return response()->json([
            'exec' => true,
            'message' => 'Cliente movido para lixeira com sucesso',
            'status' => 200,
        ], 200);
    }
    /**
     * Ativar um cliente específico
     */
    public function activate($cpf=false,$type='json'){
        if(!$cpf){
            if($type=='json'){
                return response()->json([
                    'exec' => false,
                    'message' => 'Erro de validação',
                    'errors' => ['cpf' => ['CPF inválido']],
                ], 422);
            }else{
                return [
                    'exec' => false,
                    'message' => 'Erro de validação',
                    'errors' => ['cpf' => ['CPF inválido']],
                ];
            }
        }
        $cpf = str_replace(['.', '-'], '', $cpf);
        $client = Client::where('cpf', $cpf)
            ->first();
        if (!$client) {
            if($type=='json'){
                return response()->json([
                    'exec' => false,
                    'message' => 'Cliente não encontrado',
                    'errors' => ['cpf' => ['Cliente com este CPF não foi encontrado']],
                ], 404);
            }else{
                return [
                    'exec' => false,
                    'message' => 'Cliente não encontrado',
                    'errors' => ['cpf' => ['Cliente com este CPF não foi encontrado']],
                ];
            }
        }
        $client->status = 'actived';
        $client->ativo = 's';
        $client->excluido = 'n';
        $client->save();
        if($type=='json'){
            return response()->json([
                'exec' => true,
                'message' => 'Cliente ativado com sucesso',
                'data' => $client,
            ], 200);
        }else{
            return [
                'exec' => true,
                'message' => 'Cliente ativado com sucesso',
                'data' => $client,
            ];
        }
    }
    /**
     * Inativar um cliente específico
     */
    public function inactivate(Request $request, string $cpf)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'exec' => false,
                'error' => 'Acesso negado',
            ], 403);
        }
        if (!$this->permissionService->isHasPermission('delete')) {
            return response()->json([
                'exec' => false,
                'error' => 'Acesso negado',
            ], 403);
        }
        $cpf = str_replace(['.', '-'], '', $cpf);
        $client = Client::where('cpf',$cpf)->first();
        if (!$client) {
            return response()->json([
                'exec'=>false,
                'message' => 'Cliente não encontrado',
                'status' => 404
            ], 404);
        }
        $client->update([
            'ativo' => 'n',
            'status' => 'inactived',
            'excluido' => 's',
            'reg_deletado' => json_encode([
                'usuario' => $user->id,
                'nome' => $user->name,
                'created_at' => now(),
            ])
        ]);

        if(!$client){
            return response()->json([
                'exec'=>false,
                'message' => 'Cliente não encontrado',
                'status' => 404
            ]);
        }
        // dd($client);
        // if($client->status != 'actived'){
        //     return response()->json([
        //         'exec'=>false,
        //         'message' => 'Cliente não está ativo',
        //         'status' => 400
        //     ]);
        // }
        //Verifica que ele está na aloyal
        //solicitar inativação na aloyal
        $aloyal = (new AlloyalController)->destroy($client->cpf);
        // dump($aloyal);
        if($aloyal['exec']){
            // Mover para lixeira em vez de excluir permanentemente
            $client->update([
                'excluido' => 's',
                'status' => 'inactived',
                'ativo' => 'n',
                'reg_deletado' => json_encode([
                    'usuario' => $request->user()->id,
                    'nome' => $request->user()->name,
                    'created_at' => now(),
                ])
            ]);
        }
        if($client->excluido == 's'){
            return response()->json([
                'exec' => true,
                'message' => 'Cliente inativado com sucesso',
                'status' => 200,
            ], 200);
        }
        return response()->json([
            'exec'=>false,
            'message' => 'Erro ao inativar cliente',
            'status' => 400
        ]);
    }

    /**
     * Listar clientes na lixeira
     */
    public function trash(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('view')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $perPage = $request->input('per_page', 10);
        $order_by = $request->input('order_by', 'created_at');
        $order = $request->input('order', 'desc');

        $query = Client::withoutGlobalScope('client')
            ->where('permission_id', $this->cliente_permission_id)
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

        $clients = $query->paginate($perPage);

        // Converter config para array em cada cliente
        $clients->getCollection()->transform(function ($client) {
            if (is_string($client->config)) {
                $configArr = json_decode($client->config, true) ?? [];
                array_walk($configArr, function (&$value) {
                    if (is_null($value)) {
                        $value = (string)'';
                    }
                });
                $client->config = $configArr;
            }
            return $client;
        });

        return response()->json($clients);
    }

    /**
     * Restaurar cliente da lixeira
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

        $client = Client::withoutGlobalScope('client')
            ->where('id', $id)
            ->where('deletado', 's')
            ->where('permission_id', $this->cliente_permission_id)
            ->firstOrFail();

        $client->update([
            'deletado' => 'n',
            'reg_deletado' => null
        ]);

        return response()->json([
            'message' => 'Cliente restaurado com sucesso',
            'status' => 200
        ]);
    }

    /**
     * Excluir cliente permanentemente
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

        $client = Client::withoutGlobalScope('client')
            ->where('id', $id)
            ->where('permission_id', $this->cliente_permission_id)
            ->firstOrFail();

        $client->delete();

        return response()->json([
            'message' => 'Cliente excluído permanentemente',
            'status' => 200
        ]);
    }
}
