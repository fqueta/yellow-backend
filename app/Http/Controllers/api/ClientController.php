<?php

namespace App\Http\Controllers\api;

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
use Illuminate\Support\Facades\Log;

class ClientController extends Controller
{
    protected PermissionService $permissionService;
    public $routeName;
    public $sec;
    public $permission_client_id;
    public $permission_id;
    public function __construct()
    {
        $this->permission_id = Qlib::qoption('permission_client_id')??6;
        $this->permission_client_id = $this->permission_id;
        $route = request()->route();
        $this->routeName = $route ? $route->getName() : 'api.clients';
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
        $query = Client::query()->where('permission_id','=', $this->permission_id)->orderBy($order_by, $order);

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
        //incluir o parametro search para buscar por email, cpf ou cnpj remova qualquer espaço antes ou depois
        if($request->filled('search')){
            $query->where(function($q) use ($request){
                $q->where('email', 'like', '%' . trim($request->input('search')) . '%')
                ->orWhere('cpf', 'like', '%' . trim($request->input('search')) . '%')
                ->orWhere('cnpj', 'like', '%' . trim($request->input('search')) . '%')
                ->orWhere('name', 'like', '%' . trim($request->input('search')) . '%');
            });
        }

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

        $clients = $query->paginate($perPage);
        // Converter config para array em cada cliente
        try {
            $clients->getCollection()->transform(function ($client) {
                if (is_string($client->config)) {
                    $configArr = json_decode($client->config, true) ?? [];
                    if (is_array($configArr)) {
                        array_walk($configArr, function (&$value) {
                            if (is_null($value)) {
                                $value = (string)'';
                            }
                        });
                        $client->config = $configArr;
                    } else {
                        $client->config = [];
                    }
                } else {
                    $client->config = is_array($client->config) ? $client->config : [];
                }
                $client->is_alloyal = Qlib::get_usermeta($client->id,'is_alloyal');
                return $client;
            });
        } catch (\Exception $e) {
            Log::error('Error in ClientController transform: ' . $e->getMessage());
            throw $e;
        }
        // dd($clients);
        if($request->segment(4) == 'registred'){
            $ret = $clients->getCollection()->map(function ($client) {
                return $this->map_client($client);
            });
            return $ret;
        }
        return response()->json($clients);
    }

    /**
     * Mapeia os dados do cliente para o formato desejado
     */
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
                        // dd($name,$is_mileto_user);
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
        $validated['permission_id'] = $this->permission_id;
        $validated['config'] = isset($validated['config']) ? $this->sanitizeInput($validated['config']) : [];
        // dd($validated,$this->permission_id);
        // if(isset($validated['config']) && is_array($validated['config'])){
        //     $validated['config'] = json_encode($validated['config']);
        // }

        return $validated;
    }
    /**
     * Processar apenas pontos (método PUT) para ativar cliente inativo.
     * Aceita valores positivos (crédito) e negativos (débito).
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    private function processPointsOnly(Request $request)
    {
        // Validar apenas os campos necessários para pontos pode aceitar numero negativos tambem
        $validator = Validator::make($request->all(), [
            'cpf' => 'required|string|max:20',
            'points' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'exec' => false,
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Buscar cliente pelo CPF
        $cpf = str_replace(['.','-'],'', $requestData['cpf'] ?? $request->cpf);
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
                // $link_active_cad = Qlib::qoption('link_active_cad');
                // $link_active_cad = str_replace('{cpf}', $client->cpf, $link_active_cad);
                // $ret['link_active_cad'] = $link_active_cad;
                $ret['cpf'] = $client->cpf;
            }
        } elseif ($pontos < 0) {
            // Registrar débito de pontos (valores negativos)
            $pc = new PointController();
            $data = [
                'valor' => $pontos,
                'tipo' => 'debito',
                'client_id' => $client->id,
            ];

            $savePoints = $pc->createOrUpdate($data);
            $identificador = uniqid();

            $ret['identificador'] = Qlib::update_usermeta($client->id,'id_points',$identificador);
            $ret['points'] = $savePoints;

            if($savePoints){
                $ret['cpf'] = $client->cpf;
            }
        }
        $ret['exec'] = true;
        $ret['message'] = trim($message.' Pontos processados com sucesso');
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
     * Metodo para reativar um cliente que está na lixeira
     */
    public function reativar_client($cpf){
        $client = Client::where('cpf', $cpf)
            ->where('excluido', 's')
            ->first();
        if(!$client){
            return response()->json([
                'exec' => false,
                'message' => 'Cliente não encontrado na lixeira',
                'errors' => ['cpf' => ['Cliente com este CPF não foi encontrado na lixeira']],
            ], 404);
        }
        $client->status = 'actived';
        $client->ativo = 's';
        $client->excluido = 'n';
        $client->save();
        //verifica se é um cliente alloyal
        $is_alloyal = (new AlloyalController)->is_alloyal($cpf);
        if($is_alloyal['exec']){
            //retivar na alloyal table também
            (new AlloyalController)->ativate([
                'cpf'=>$cpf,
                'name'=>$client->name,
            ]);
        }

        return response()->json([
            'exec' => true,
            'message' => 'Cliente reativado com sucesso',
        ], 200);
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
            'permission_id' => $this->permission_id,
            'cpf' => $cpf,
            'autor' => $request->user()->id,
        ]);
        // dd($request->all());
        //verifica se o CPF ja Existe
        $clientCheck = Client::where('cpf', $request->cpf)->first();
        //verificar se está desativado é so ativar
        if($clientCheck && $clientCheck->status == 'inactived'){
           $response = $this->reativar_client($cpf);
           if($response){
               return $response;
           }
        }
        if($clientCheck){
            // return response()->json([
            //     'exec' => false,
            //     'message' => 'CPF já existe',
            //     'errors'  => ['cpf' => ['CPF já existe']],
            // ], 422);
            return $this->processPointsOnly($request);
        }
        // Validação específica para pré-cadastro
        $validator = Validator::make($request->all(), [
            'tipo_pessoa'   => ['required', Rule::in(['pf','pj'])],
            'name'          => 'required|string|max:255',
            'points'        => 'nullable|string|max:255',
            'cpf'           => 'nullable|string|max:20|unique:users,cpf',
            'genero'        => ['required', Rule::in(['ni','m','f'])],
            'permission_id' => ['required', Rule::in([$this->permission_id])],
            'status'        => ['required', Rule::in(['actived','inactived','pre_registred'])],
            'autor'         => 'nullable|string|max:255',
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
        // dd($clientData);
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
            'status'        => ['required', Rule::in(['actived','inactived','pre_registred'])],
            'autor'         => 'nullable|string|max:255',
        ]);
        $password = $request->password;

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
        //verifica se o CPF ja existe se não for vazio
        if($clientData['cpf']){
            $clientCheck = Client::where('cpf', $clientData['cpf'])->first();
            if($clientCheck){
                    return response()->json([
                        'exec' => false,
                        'message' => 'CPF '.$clientData['cpf'].' já existe na base de dados',
                        'errors'  => ['cpf' => ['CPF já existe']],
                ], 422);
            }
        }
        //verifica se o CNPJ ja existe
        // if($clientData['cnpj']){
        //     $clientCheck = Client::where('cnpj', $clientData['cnpj'])->first();
        //     if($clientCheck){
        //         return response()->json([
        //             'exec' => false,
        //             'message' => 'CNPJ '.$clientData['cnpj'].' já existe na base de dados',
        //             'errors'  => ['cnpj' => ['CNPJ já existe']],
        //         ], 422);
        //     }
        // }
        $clientData['permission_id'] = $this->permission_id;
        $client = Client::create($clientData);
        $alloyal = null;
        //verifica se foi salvo com sucesso antes de enviar para Alloyal
        if($client){
            $d_send_api['password'] = $password;
            $d_send_api['id'] = $client['id'];
            $d_send_api['cpf'] = $client['cpf'];
            $d_send_api['name'] = $client['name'];
            $d_send_api['email'] = $client['email'];
            $alloyal = $this->sendCadastroToAlloyal($d_send_api);
            // dd($alloyal);
        }
        return response()->json([
            'data' => $client,
            'alloyal' => $alloyal,
            'message' => 'Cliente criado com sucesso',
            'status' => 201,
        ], 201);
    }
    public function store_active(Request $request, $etp = null)
    {

        if (empty($requestData) && $request->getContent()) {
            // Limpar caracteres UTF-8 malformados
            $content = $request->getContent();
            $cleanContent = mb_convert_encoding($content, 'UTF-8', 'UTF-8');

            $jsonData = json_decode($cleanContent, true);
            Log::info('Tentativa de parse JSON', [
                'original_content' => $content,
                'clean_content' => $cleanContent,
                'json_error' => json_last_error(),
                'json_error_msg' => json_last_error_msg(),
                'is_array' => is_array($jsonData),
                'parsed_data' => $jsonData
            ]);

            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                $requestData = $jsonData;
                Log::info('JSON parseado manualmente com sucesso', ['parsed_data' => $requestData]);
            }
        }

        $cpf = str_replace(['.','-'],'', $requestData['cpf'] ?? $request->cpf);
        $request->merge(['cpf' => $cpf]);
        $client = Client::where('cpf', $cpf)->first();

        Log::info('Buscando cliente', ['cpf_original' => $requestData['cpf'] ?? $request->cpf, 'cpf_limpo' => $cpf]);
        // dd($request->all());
        if (!$client) {
            return response()->json(['error' => 'Cliente não encontrado ou invalido'], 404);
        }
        // if($client->status == 'actived'){
        //     return response()->json(['message' => 'Cliente já está cadastrado e ativado'], 422);
        // }
        //verificar se aceitou os termos

        if(!isset($requestData['termsAccepted']) || !$requestData['termsAccepted'] || $requestData['termsAccepted'] != true){
            return response()->json(['message' => 'É necessário aceitar os termos'], 422);
        }
        if(!isset($requestData['privacyAccepted']) || !$requestData['privacyAccepted'] || $requestData['privacyAccepted'] != true){
            return response()->json(['message' => 'É necessário aceitar a política de privacidade'], 422);
        }

        $campos_validacao = [
            'cpf'           => ['nullable','string','max:20', Rule::unique('users','cpf')->ignore($client->id)],
            'cnpj'          => ['nullable','string','max:20', Rule::unique('users','cnpj')->ignore($client->id)],
            'email'         => ['nullable','email', Rule::unique('users','email')->ignore($client->id)],
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:6',
        ];
        $mensage = [
            'email.required' => 'O campo e-mail é obrigatório',
            'email.email' => 'O campo e-mail deve ser um endereço de e-mail válido',
            'email.unique' => 'O e-mail já está em uso',
            'name.required' => 'O campo nome é obrigatório',
            'password.required' => 'O campo senha é obrigatório',
            'password.min' => 'A senha deve ter pelo menos 6 caracteres',
        ];
        $data_update = $request->all();
        /**Sanitizar dados antes de atualizar */
        $data_update = $this->sanitizeInput($data_update);
        /**Validar os campos antes de atualizar */
        $validator = Validator::make($data_update, $campos_validacao,$mensage);
        // dd($client->id);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors'  => $validator->errors(),
            ], 422);
        }
        //Gavar senha sem hash em uma variavel temporaria
        $d_salvar = $validator->validated();
        $password = $d_salvar['password'] ?? null;
        if(isset($d_salvar['password'])){
            $d_salvar['password'] = Hash::make($d_salvar['password']);
        }
        $d_salvar['status'] = 'actived';
        // $d_salvar['permission_id'] = $client->permission_id;

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

            // Log de debug antes do return
            Log::info('ClientController::store_active - Retornando resposta', [
                'response_data' => $ret,
                'timestamp' => now()
            ]);

            // dd($ret);
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
        $ret = $response;
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
        if (is_string($client['config'])) {
            $client['config'] = json_decode($client['config'], true) ?? [];
        }
        //carregar os pontos
        $pc = new PointController();
        $points = $pc->saldo($client['id']);
        // dd($points,$client);
        if(isset($points)){
            $client['points'] = (int)$points;
        }else{
            $client['points'] = 0;
        }
        //link de ativição para quando é pre cadastro
        $link_active_cad = Qlib::qoption('link_active_cad') ?? null;
        $link_active_cad = str_replace('{cpf}',$client['cpf'],$link_active_cad);
        $client['link_active_cad'] = $link_active_cad;
        $client['is_alloyal'] = Qlib::get_usermeta($client['id'],'is_alloyal') ?? false;
        if(is_string($client['is_alloyal'])){
            $client['is_alloyal'] = json_decode($client['is_alloyal'], true) ?? false;
            if(isset($client['is_alloyal']['data'])){
                $client['is_alloyal'] = $client['is_alloyal']['data'];
            }
        }else{
            $client['is_alloyal'] = false;
        }

        return response()->json(['data' => $client]);
    }

    /**
     * Verificar se o usuário tem permissão para criar clientes
     */
    public function create(Request $request)
    {
        // Verificar se o usuário está autenticado
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        // Verificar permissão de criação
        if (!$this->permissionService->isHasPermission('create')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        // Se chegou até aqui, o usuário tem permissão para criar
        return response()->json([
            'success' => true,
            'message' => 'Usuário tem permissão para criar clientes',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'permission_id' => $user->permission_id
            ]
        ]);
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
            'autor'         =>'nullable|string|max:255',
            'status'        => ['sometimes', Rule::in(['actived', 'inactived', 'pre_registred'])],
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
        $clientData = $this->prepareClientData($validated, 'actived');
        // Tratar senha se fornecida
        // if (isset($clientData['password']) && !empty($clientData['password'])) {
        //     $clientData['password'] = Hash::make($clientData['password']);
        // } else {
        //     unset($clientData['password']);
        // }
        // Garantir que permission_id seja sempre 5 (cliente)
        // $clientData['permission_id'] = $this->permission_client_id;
        // dd($clientData,$this->permission_client_id);

        // Tratar config se fornecido
        if (isset($clientData['config'])) {
            $clientData['config'] = $this->sanitizeInput($clientData['config']);
            if (isArray($clientData['config'])) {
                $clientData['config'] = json_encode($clientData['config']);
            }
        }
        $clientToUpdate->update($clientData);

        // Converter config para array na resposta
        if (is_string($clientToUpdate['config'])) {
            $clientToUpdate['config'] = json_decode($clientToUpdate['config'], true) ?? [];
            // dd($clientData);
        }
        // dd($clientToUpdate);
        //verifica se foi atualizado com sucesso
        if($clientToUpdate->wasChanged()){
            $d_send_api = $clientToUpdate->toArray();
            $d_send_api['password'] = $validated['password'] ?? $clientToUpdate['password'];
            $d_send_api['id'] = $clientToUpdate['id'];
            $d_send_api['cpf'] = $clientToUpdate['cpf'];
            $ret['alloyal'] = $this->sendCadastroToAlloyal($d_send_api);
            $ret['message'] = 'Cliente atualizado com sucesso';
            $ret['status'] = 200;
            $ret['data'] = $clientToUpdate;
        }else{
            $ret['success'] = false;
            $ret['message'] = 'Nenhum dado foi atualizado';
            $ret['status'] = 400;
        }
        // $ret['data'] = $clientToUpdate;
        // $ret['message'] = 'Cliente atualizado com sucesso';
        // $ret['status'] = 200;

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
            'ativo' => 'n',
            'status' => 'inactived',
            'excluido' => 's',
            'reg_deletado' => json_encode([
                'usuario' => $user->id,
                'nome' => $user->name,
                'created_at' => now(),
            ])
        ]);

        // Atualizar status na alloyal
        $cpf = $client->cpf;
        if(!$cpf){
            return response()->json([
                'exec' => false,
                'message' => 'Erro de validação',
                'errors' => ['cpf' => ['CPF inválido']],
            ], 422);
        }
        $deleAlloyal = (new AlloyalController)->destroy($client->cpf);
        // $this->sendCadastroToAlloyal($client->toArray());

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
        //ativar na alloyal
        try{
            $activateAlloyal = (new AlloyalController)->ativate([
                'cpf' => $client->cpf,
                'name' => $client->name,
            ]);
            $message = $activateAlloyal['message'] ?? '';
        }catch(\Exception $e){
            $message = $e->getMessage();
            // dd($message);
            if($type=='json'){
                return response()->json([
                    'exec' => false,
                    'message' => 'Erro ao ativar cliente no provedor',
                    'errors' => $message,
                ], 400);
            }else{
                return [
                    'exec' => false,
                    'message' => 'Erro ao ativar cliente no provedor',
                    'errors' => $message,
                ];
            }
            return $ret;
        }
        //ativa localmente
        try{
            $client->status = 'actived';
            $client->ativo = 's';
            $client->excluido = 'n';
            $client->save();
        }catch(\Exception $e){
            $message = $e->getMessage();
            if($type=='json'){
                return response()->json([
                    'exec' => false,
                    'message' => 'Erro ao ativar cliente',
                    'errors' => $message,
                ], 400);
            }else{
                return [
                    'exec' => false,
                    'message' => 'Erro ao ativar cliente',
                    'errors' => $message,
                ];
            }
            return $ret;
        }
        if($type=='json'){
            if($activateAlloyal['exec']){
                return response()->json([
                    'exec' => true,
                    'message' => 'Cliente ativado com sucesso',
                    'data' => $client,
                ], 200);
            }else{
                return response()->json([
                    'exec' => false,
                    'message' => 'Erro ao ativar cliente no provedor',
                    'errors' => $activateAlloyal['message'],
                ], 400);
            }
        }else{
            if($activateAlloyal['exec']){
                return [
                    'exec' => true,
                    'message' => 'Cliente ativado com sucesso',
                    'data' => $client,
                ];
            }else{
                return [
                    'exec' => false,
                    'message' => 'Erro ao ativar cliente no provedor',
                    'errors' => $activateAlloyal['message'],
                ];
            }
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
            ->where('permission_id', $this->permission_client_id)
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
            ->where('permission_id', $this->permission_client_id)
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
            ->where('permission_id', $this->permission_client_id)
            ->firstOrFail();

        $client->delete();

        return response()->json([
            'message' => 'Cliente excluído permanentemente',
            'status' => 200
        ]);
    }
}
