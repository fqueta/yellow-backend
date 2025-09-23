<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Api\PointController;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Services\Qlib;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AlloyalController extends Controller
{
    protected $url_api_aloyall;
    protected $clientEmployeeEmail;
    protected $clientEmployeeToken;
    protected $business_id_alloyal;
    protected $endpoint;
    public function __construct()
    {
        $this->url_api_aloyall = Qlib::qoption('url_api_aloyall') ?? 'https://api.lecupon.com';
        $this->clientEmployeeEmail = Qlib::qoption('email_admin_api_alloyal') ?? '';
        $this->clientEmployeeToken = Qlib::qoption('token_api_alloyal') ?? '';
        $this->business_id_alloyal = Qlib::qoption('business_id_alloyal') ?? '2676';
        $this->endpoint = '/client/v2/businesses/' . $this->business_id_alloyal ;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * deposita na conta do clientes.
     */
    public function deposit($data,$idendificador='')
    {
        if(!isset($data['document']) || !isset($data['amount'])){
            return ['exec'=>false,'message'=>'Dados incompletos'];
        }
        if(!isset($data['description'])){
            $data['description'] = 'Depósito via API';
        }
        $token = uniqid();
        $headers = [
            'X-ClientEmployee-Token' => $this->clientEmployeeToken,
            'X-ClientEmployee-Email' => $this->clientEmployeeEmail,
            'Idempotency-Key' => $token,//Qlib::zerofill($idendificador,6),
            'Content-Type' => 'application/json'
        ];
        $client_id = Client::where('cpf',$data['document'])->value('id');
        if(!$client_id){
            return ['exec'=>false,'message'=>'Cliente não encontrado'];
        }
        $save_ident = Qlib::update_usermeta($client_id,'token_alloyal',$token);
        $body = [
            "document" => $data['document'],
            "amount" => $data['amount'],
            "wallet_type" => $data['wallet_type'] ?? 'external',
            "currency" => $data['currency'] ?? 'BRL',
            "description" => $data['description'],
        ];
        $ret['exec'] = false;
        $ret['message'] = 'Erro ao depositar na conta do cliente';
        try {
            $endpoint = $this->endpoint . '/deposits';
            $url = $this->url_api_aloyall . $endpoint;
            $response = Http::withHeaders($headers)->post($url, $body);
            // dd($url,$headers,$response->json(),$body);
            $ret['exec'] = true;
            $ret['data'] = $response->json();
            if(isset($ret['data']['error'])){
                $ret['message'] = $ret['data']['error'];
                $ret['data'] = $data;
                $ret['exec'] = false;
                return $ret;
            }else{
                //lançar baixa do credito
                $baixar = (new PointController)->createOrUpdate([
                    'client_id' => $client_id,
                    'tipo' => 'debito',
                    'valor' => (int)$data['amount']*(-1),
                    'description' => $data['description'],
                ]);
                $ret['baixar'] = $baixar;
                $ret['message'] = 'Depósito realizado com sucesso, status: ' . $response->status();
            }
            return $ret;
        } catch (\Throwable $th) {
            //throw $th;
            $ret['message'] = 'Erro ao depositar na conta do cliente, status: ' . $response->status();
            $ret['message'] .= $th->getMessage();
            $ret['error'] = $th->getMessage();
            $ret['data'] = $data;
        }
        // dd($headers,$ret);
        return $ret;
    }
    /**
     * cadastra um usuario ativo na alloyal
     */
    public function create_user_atived($d_send,$client_id=null){
        $headers = [
            'X-ClientEmployee-Email' => $this->clientEmployeeEmail,
            'X-ClientEmployee-Token' => $this->clientEmployeeToken,
            'Content-Type' => 'application/json'
        ];
        //manodar body atravez do esquema
        $body = [
            "name" => $d_send['name'],
            "cpf" => $d_send['cpf'],
            "email" => $d_send['email'],
            "password" => $d_send['password'],
        ];
        $ret['exec'] = false;
        $ret['message'] = 'Erro ao criar usuário';
        try {
            $endpoint = $this->endpoint . '/users';
            $url = $this->url_api_aloyall . $endpoint;
            $response = Http::withHeaders($headers)->post($url, $body);
            // dd($url,$headers,$response->json(),$body);
            $ret['exec'] = true;
            $ret['message'] = 'Usuário criado com sucesso';
            $data = $response->json();
            $ret['data'] = $data;
            $ret['message'] = 'Usuário criado com sucesso, status: ' . $response->status();
            if(!$client_id){
                $client_id = Client::where('cpf',$d_send['cpf'])->value('id');
            }
            if($client_id){
                $ret['message'] .= ', ID: ' . $client_id;
                $ret['client_id'] = Qlib::update_usermeta($client_id,'is_mileto_user',json_encode($ret));// $client_id;
                //depositar na carteira
                $ret['message'] .= ', ID do usuário: ' . $client_id;
                if(isset($data['cpf'])){
                    $ret['data']['deposit'] = $this->fazer_deposito(['cpf'=>$data['cpf'],'client_id'=>$client_id,'description'=>'Depósito via API']);
                }
            }
            return $ret;
        } catch (\Throwable $th) {
            //throw $th;
            $ret['message'] = 'Erro ao criar usuário, status: ' . $response->status();
            $ret['message'] .= $th->getMessage();
            return $ret;
        }
    }
    /**
     * Para fazer o depsito
     */
    public function fazer_deposito($config=[] ){
        $cpf = $config['cpf'] ?? null;
        // $amount = $config['amount'] ?? null;
        $description = $config['description'] ?? 'Depósito via API';
        $client_id = $config['client_id'] ?? null;
        if(!$cpf){
            return ['exec'=>false,'message'=>'Dados incompletos'];
        }
        if(!$client_id){
            $client_id = $client_id ?? $this->get_client_id($cpf);
        }
        if(!$client_id){
            return ['exec'=>false,'message'=>'Cliente não encontrado'];
        }
        $pc = new PointController();
        $points = $pc->saldo($client_id);

        if(!empty($points) && $points == null){
            return ['exec'=>false,'message'=>'Saldo de pontos '.$points.' insuficiente'];
        }
        $multinplicador = Qlib::qoption('factor_point_brl') ? Qlib::qoption('factor_point_brl') : 1;
        $amount = (int)$points * $multinplicador;

        $data = [
            'document' => $cpf,
            'amount' => $amount,
            'wallet_type' => 'external',
            'currency' => 'BRL',
            'description' => $description,
        ];
        $idendificador = Qlib::get_usermeta($client_id,'id_points');
        // dd($data,$ponts,$multinplicador);
        $ret = $this->deposit($data,$idendificador);
        if($ret['exec'] && $amount){
            // dump($client_id,$amount);
            $ret['data']['debito'] = $this->debitar($client_id,$amount);
        }
        // dd($ret);
        return $ret;
    }
    public function debitar($client_id,$valor){
        $client_id = $data['client_id'] ?? null;
        if(!$client_id){
            return ['exec'=>false,'message'=>'Dados incompletos'];
        }
        $data = [
            'client_id' => $client_id,
            'valor' => $valor*(-1),
            'tipo' => 'debito',
            'description' => 'Enviado para Alloyal',
        ];
        $pc = new PointController();
        $ret = $pc->createOrUpdate($data);
        return $ret;
    }
    public function get_client_id($cpf){
        $client = User::where('cpf',$cpf)->first();
        if($client){
            return $client->id;
        }
        return null;
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $cpf)
    {
        $headers = [
            'X-ClientEmployee-Email' => $this->clientEmployeeEmail,
            'X-ClientEmployee-Token' => $this->clientEmployeeToken,
            'Content-Type' => 'application/json'
        ];
        $endpoint = $this->endpoint . '/authorized_users/' . $cpf;
        $url = $this->url_api_aloyall . $endpoint;

        $response = Http::withHeaders($headers)->delete($url);
        // dd($url,$headers,$response->json());
        if($response->status() != 200){
            $ret['exec'] = false;
            $ret['message'] = 'Erro ao excluir usuário, status: ' . $response->status();
            $ret['message'] .= $response->json()['message'] ?? '';
            return $ret;
        }
        $client_id = $this->get_client_id($cpf);
        $save = Qlib::update_usermeta($client_id,'create_user_actived',json_encode($response));
        if($save){
            $ret['exec'] = true;
            $ret['message'] = 'Usuário excluído com sucesso';
            $ret['data'] = $response->json();
        }
        return $ret;
    }
}
