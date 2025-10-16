<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\PointController;
use App\Models\Product;
use App\Services\PermissionService;
use App\Services\Qlib;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    protected $permissionService;
    protected $post_type;

    /**
     * Construtor do controller
     */
    public function __construct()
    {
        $this->permissionService = new PermissionService();
        $this->post_type = 'products';
    }

    /**
     * Sanitiza os dados de entrada
     */
    private function sanitizeInput($data)
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = strip_tags($value);
            }
        }
        return $data;
    }

    /**
     * Converte status booleano para post_status
     */
    private function get_status($active)
    {
        return $active ? 'publish' : 'draft';
    }

    /**
     * Converte post_status para booleano
     */
    private function decode_status($post_status)
    {
        return $post_status === 'publish';
    }

    /**
     * Mapeia dados do frontend para campos do banco de dados
     */
    private function mapFrontendToDatabase($validated, $includeDefaults = false)
    {
        $mappedData = [];

        // Mapeamento básico de campos
        if (isset($validated['name'])) {
            $mappedData['post_title'] = $validated['name']; // name -> post_title
        }
        if (isset($validated['description'])) {
            $mappedData['post_content'] = $validated['description'] ?? ''; // description -> post_content
        }
        if (isset($validated['active'])) {
            $mappedData['post_status'] = $this->get_status($validated['active'] ?? true); // active -> post_status
        }
        if (isset($validated['category'])) {
            $mappedData['guid'] = $validated['category'] ?? null; // category -> guid
        }
        if (isset($validated['costPrice'])) {
            $mappedData['post_value1'] = $validated['costPrice'] ?? 0; // costPrice -> post_value1
        }
        if (isset($validated['salePrice'])) {
            $mappedData['post_value2'] = $validated['salePrice'] ?? 0; // salePrice -> post_value2
        }
        if (isset($validated['stock'])) {
            $mappedData['comment_count'] = $validated['stock'] ?? 0; // stock -> comment_count
        }

        // Incluir valores padrão se solicitado (para criação)
        if ($includeDefaults) {
            $mappedData['post_content'] = $mappedData['post_content'] ?? '';
            $mappedData['post_status'] = $mappedData['post_status'] ?? $this->get_status(true);
            $mappedData['guid'] = $mappedData['guid'] ?? null;
            $mappedData['post_value1'] = $mappedData['post_value1'] ?? 0;
            $mappedData['post_value2'] = $mappedData['post_value2'] ?? 0;
            $mappedData['comment_count'] = $mappedData['comment_count'] ?? 0;
        }

        return $mappedData;
    }

    /**
     * Mapeia dados do banco para o formato do frontend
     */
    private function mapDatabaseToFrontend($product)
    {
        $user_id = request()->user()->id;
        if(is_array($product)){
            $product = (object)$product;
        }
        $image = $product->config['image'] ?? null;
        $image2 = $product->config['image2'] ?? null;
        if($image){
            $image = str_replace('{image}',$image, Qlib::qoption('link_files'));
        }
        if($image2){
            $image2 = str_replace('{image}',$image2, Qlib::qoption('link_files'));
        }
        $product_image = $image;
        $pc = new PointController();
        $saldo = $pc->saldo($user_id);
        $categoryData = Qlib::get_category_by_id($product->guid);
        $stock = $product->comment_count ?? 0;
        $isActive = $stock > 0;
        return [
            'id' => $product->ID,
            'name' => $product->post_title, // post_title -> name
            'description' => $product->post_content, // post_content -> description
            'shortDescription' => $product->post_excerpt, // post_excerpt -> shortDescription
            'slug' => $product->post_name,
            'active' => $this->decode_status($product->post_status), // post_status -> active
            'isActive' => $isActive, // true se estoque > 0, false caso contrário
            'category' => $categoryData['name'] ?? null, // guid -> category
            'costPrice' => $product->post_value1, // post_value1 -> costPrice
            'salePrice' => $product->post_value2, // post_value2 -> salePrice
            'stock' => $stock, // comment_count -> stock
            'categoryData' => $categoryData,
            'unitId' => Qlib::get_unit_id_by_name($product->config['unit'] ?? null),
            'unit' => $product->config['unit'] ?? null,
            'pointsRequired' => $product->config['points'] ?? null,
            'image' => $product_image,
            'image2' => $image2,
            'rating' => $product->config['rating'] ?? null,
            'reviews' => $product->config['reviews'] ?? null,
            'slug' => $product->post_name ?? null,
            'availability' => $product->config['availability'] ?? null,
            'terms' => $product->config['terms'] ?? null,
            'validUntil' => $product->config['validUntil'] ?? null,
            'inStock' => $product->config['inStock'] ?? null,
            'originalPrice' => $product->config['originalPrice'] ?? null,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
            'points_saldo' => $saldo,
        ];
    }

    /**
     * Listar todos os produtos
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

        $query = Product::query()
            ->orderBy($order_by, $order);

        // Filtros opcionais
        if ($request->filled('name')) {
            $query->where('post_title', 'like', '%' . $request->input('name') . '%');
        }
        if ($request->filled('slug')) {
            $query->where('post_name', 'like', '%' . $request->input('slug') . '%');
        }
        if ($request->filled('active')) {
            $status = $this->get_status($request->boolean('active'));
            $query->where('post_status', $status);
        }
        if ($request->filled('category')) {
            $query->where('guid', $request->input('category'));
        }

        $products = $query->paginate($perPage);
        // Transformar dados para o formato do frontend
        $products->getCollection()->transform(function ($item) {
            return $this->mapDatabaseToFrontend($item);
        });
        // dd($products);

        return response()->json($products);
    }
    public function index_public(Request $request){
        $perPage = $request->input('per_page', 10);
        $order_by = $request->input('order_by', 'created_at');
        $order = $request->input('order', 'desc');
        $query = Product::query()
            ->orderBy($order_by, $order);

        // Filtros opcionais
        if ($request->filled('name')) {
            $query->where('post_title', 'like', '%' . $request->input('name') . '%');
        }
        if ($request->filled('slug')) {
            $query->where('post_name', 'like', '%' . $request->input('slug') . '%');
        }
        if ($request->filled('active')) {
            $status = $this->get_status($request->boolean('active'));
            $query->where('post_status', $status);
        }
        if ($request->filled('category')) {
            $query->where('guid', $request->input('category'));
        }

        $products = $query->paginate($perPage);
        // Transformar dados para o formato do frontend
        $products->getCollection()->transform(function ($item) {
            return $this->mapDatabaseToFrontend($item);
        });
        return response()->json($products);
    }
    /**
     * Mapeia um produto para o formato do frontend     *
     * @param Product $product
     * @return array
     */
    public function map_product($product)
    {
        if(is_array($product)){
            $product = (object)$product;
        }
        dd(Qlib::qoption('link_files'));
        $image = $product->config['image'] ?? null;
        if($image){
            $image = str_replace('{image}', Qlib::qoption('link_files'), $image);
        }
        return [
            'id' => $product->ID,
            'name' => $product->post_title,
            'description' => $product->post_content,
            'slug' => $product->post_name,
            'active' => $this->decode_status($product->post_status),
            'category' => $product->guid,
            'costPrice' => $product->post_value1,
            'salePrice' => $product->post_value2,
            // 'stock' => $product->comment_count,
            'categoryData' => Qlib::get_category_by_id($product->guid),
            'unitData' => Qlib::get_unit_by_id($product->config['unit'] ?? null),
            'unit' => $product->config['unit'] ?? null,
            'points' => $product->config['points'] ?? null,
            'image' => $image,
            'rating' => $product->config['rating'] ?? null,
            'reviews' => $product->config['reviews'] ?? null,
            'availability' => $product->config['availability'] ?? null,
            'terms' => $product->config['terms'] ?? null,
            'validUntil' => $product->config['validUntil'] ?? null,
            'inStock' => $product->config['inStock'] ?? null,
            'originalPrice' => $product->config['originalPrice'] ?? null,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ];
    }
    public function array_filder_validate(){
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'active' => 'boolean',
            'category' => 'nullable|string|max:255',
            'costPrice' => 'nullable|numeric|min:0',
            'salePrice' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'unit' => 'nullable|string|max:100',
            'points' => 'nullable|integer|min:0',
            'image' => 'nullable|string|max:255',
            'rating' => 'nullable|numeric|min:0|max:5',
            'reviews' => 'nullable|integer|min:0',
            'availability' => 'nullable|string|max:255',
            'terms' => 'nullable|string|max:255',
            'validUntil' => 'nullable|date',
            'inStock' => 'nullable|boolean',
            'originalPrice' => 'nullable|numeric|min:0',
        ];
    }
    /**
     * Criar um novo produto
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

        $validator = Validator::make($request->all(), $this->array_filder_validate());

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        // dd($validated);
        // Verificar se já existe um produto deletado com o mesmo nome
        $existingProduct = Product::withoutGlobalScope('notDeleted')
            ->where('post_title', $validated['name'])
            ->where(function($query) {
                $query->where('excluido', 's')->orWhere('deletado', 's');
            })
            ->first();
        if ($existingProduct) {
            return response()->json([
                'message' => 'Já existe um produto com este nome que foi excluído. Restaure-o ou use outro nome.',
                'error' => 'duplicate_name'
            ], 409);
        }

        // Mapear campos do frontend para campos do banco usando o método dedicado
        $mappedData = $this->mapFrontendToDatabase($validated, true);
        // Configurar unidade no campo config
        if (isset($validated['unit'])) {
            $mappedData['config'] = ['unit' => $validated['unit']];
        }

        // Gerar slug automaticamente
        $mappedData['post_name'] = (new Product())->generateSlug($validated['name']);

        // Sanitização dos dados
        $mappedData = $this->sanitizeInput($mappedData);

        // Gerar token único
        $mappedData['token'] = Qlib::token();

        // Definir autor como usuário logado
        $mappedData['post_author'] = $user->id;

        // Valores padrão
        $mappedData['comment_status'] = 'closed';
        $mappedData['ping_status'] = 'closed';
        $mappedData['post_type'] = $this->post_type; // Forçar tipo products
        $mappedData['menu_order'] = 0;
        $mappedData['to_ping'] = 's';
        $mappedData['excluido'] = 'n';
        $mappedData['deletado'] = 'n';
        // dd($mappedData);
        $product = Product::create($mappedData);
        // Preparar resposta no formato do frontend
        $responseData = $this->mapDatabaseToFrontend($product);

        return response()->json([
            'data' => $responseData,
            'message' => 'Produto criado com sucesso',
            'status' => 201,
        ], 201);
    }
    /**
     * Exibir um produto específico
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
        //adicionar um função para fazer consulta atraves do slug caso não encontra por id
        $product = Product::where('ID',$id)->first();
        if(!$product){
            $product = Product::where('post_name', $id)->firstOrFail();
        }
        if($product->excluido == 's' || $product->deletado == 's'){
            return response()->json(['error' => 'Produto excluído ou deletado'], 404);
        }
        //se não encontrar retornar erro 404
        if(!$product){
            return response()->json(['error' => 'Produto não encontrado'], 404);
        }

        // Preparar resposta no formato do frontend
        $responseData = $this->mapDatabaseToFrontend($product);
        $user = request()->user();
        $pc = new PointController();
        $saldo = $pc->saldo($user->id);
        $user->points_saldo = $saldo;
        $responseData['user'] = $user->toArray();
        return response()->json([
            'data' => $responseData,
            'message' => 'Produto encontrado com sucesso',
            'status' => 200,
        ], 200);
    }

    /**
     * Atualizar um produto
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

        // Validação dos dados
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'active' => 'boolean',
            'category' => 'nullable|string|max:255',
            'costPrice' => 'nullable|numeric|min:0',
            'salePrice' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'unit' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $productToUpdate = Product::findOrFail($id);

        // Mapear campos do frontend para campos do banco usando o método dedicado
        $mappedData = $this->mapFrontendToDatabase($validated);

        // Gerar novo slug se o nome foi alterado
        if (isset($validated['name'])) {
            $mappedData['post_name'] = $productToUpdate->generateSlug($validated['name']);
        }

        // Configurar unidade no campo config
        if (isset($validated['unit'])) {
            $config = $productToUpdate->config ?? [];
            $config['unit'] = $validated['unit'];
            $mappedData['config'] = $config;
        }

        // Sanitização dos dados
        $mappedData = $this->sanitizeInput($mappedData);

        // Garantir que o post_type permaneça como products
        $mappedData['post_type'] = $this->post_type;

        $productToUpdate->update($mappedData);

        // Preparar resposta no formato do frontend
        $responseData = $this->mapDatabaseToFrontend($productToUpdate);

        return response()->json([
            'exec' => true,
            'data' => $responseData,
            'message' => 'Produto atualizado com sucesso',
            'status' => 200,
        ]);
    }

    /**
     * Excluir um produto (soft delete)
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

        $productToDelete = Product::find($id);

        if (!$productToDelete) {
            return response()->json([
                'message' => 'Produto não encontrado',
                'status' => 404,
            ], 404);
        }

        // Soft delete - marcar como excluído
        $productToDelete->update([
            'excluido' => 's',
            'reg_excluido' => json_encode([
                'excluido_por' => $user->id,
                'excluido_em' => now()->toDateTimeString(),
                'motivo' => 'Exclusão via API'
            ])
        ]);

        return response()->json([
            'exec' => true,
            'message' => 'Produto excluído com sucesso',
            'status' => 200,
        ]);
    }

    /**
     * Listar produtos na lixeira
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
        $order_by = $request->input('order_by', 'updated_at');
        $order = $request->input('order', 'desc');

        $products = Product::withoutGlobalScope('notDeleted')
            ->where(function($query) {
                $query->where('excluido', 's')->orWhere('deletado', 's');
            })
            ->orderBy($order_by, $order)
            ->paginate($perPage);

        // Transformar dados para o formato do frontend
        $products->getCollection()->transform(function ($item) {
            return $this->mapDatabaseToFrontend($item);
        });

        return response()->json($products);
    }

    /**
     * Restaurar um produto da lixeira
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

        $product = Product::withoutGlobalScope('notDeleted')
            ->where('ID', $id)
            ->where(function($query) {
                $query->where('excluido', 's')->orWhere('deletado', 's');
            })
            ->first();

        if (!$product) {
            return response()->json([
                'message' => 'Produto não encontrado na lixeira',
                'status' => 404,
            ], 404);
        }

        // Restaurar produto
        $product->update([
            'excluido' => 'n',
            'deletado' => 'n',
            'reg_excluido' => null,
            'reg_deletado' => null,
        ]);

        return response()->json([
            'exec' => true,
            'message' => 'Produto restaurado com sucesso',
            'status' => 200,
        ]);
    }

    /**
     * Excluir permanentemente um produto
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

        $product = Product::withoutGlobalScope('notDeleted')
            ->where('ID', $id)
            ->where(function($query) {
                $query->where('excluido', 's')->orWhere('deletado', 's');
            })
            ->first();

        if (!$product) {
            return response()->json([
                'message' => 'Produto não encontrado na lixeira',
                'status' => 404,
            ], 404);
        }

        // Excluir permanentemente
        $product->forceDelete();

        return response()->json([
            'exec' => true,
            'message' => 'Produto excluído permanentemente',
            'status' => 200,
        ]);
    }

    /**
     * Processar resgate de pontos por produto
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function redeem(Request $request)
    {
        // dd($request->all());
        try {
            // Validar dados de entrada
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|integer|exists:posts,ID',
                'quantity' => 'required|integer|min:1',
                'config' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'exec' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors(),
                    'status' => 422,
                ], 422);
            }

            $user = $request->user();
            $productId = $request->product_id;
            $quantity = $request->quantity;

            // Buscar o produto
            $product = Qlib::buscaPostsPorId($productId);
            if (!$product) {
                return response()->json([
                    'exec' => false,
                    'message' => 'Produto não encontrado',
                    'status' => 404,
                ], 404);
            }

            // Verificar se o produto está ativo
            if ($product['post_status'] !== 'publish') {
                return response()->json([
                    'exec' => false,
                    'message' => 'Produto não está disponível para resgate',
                    'status' => 400,
                ], 400);
            }

            // Verificar estoque disponível
            // $stock = $product['config']['stock'] ?? 0;
            $stock = $product['comment_count'] ?? 0;
            if ($stock < $quantity) {
                return response()->json([
                    'exec' => false,
                    'message' => 'Estoque insuficiente. Disponível: ' . $stock,
                    'status' => 400,
                ], 400);
            }

            // Obter pontos necessários por unidade
            $unitPoints = floatval($product['config']['points'] ?? 0);
            if ($unitPoints <= 0) {
                return response()->json([
                    'exec' => false,
                    'message' => 'Produto não possui pontos configurados',
                    'status' => 400,
                ], 400);
            }

            // Calcular total de pontos necessários
            $totalPointsNeeded = $unitPoints * $quantity;

            // Verificar saldo de pontos do usuário
            $pointController = new PointController();
            $userPointsBalance = $pointController->saldo($user->id);

            if ($userPointsBalance < $totalPointsNeeded) {
                return response()->json([
                    'exec' => false,
                    'message' => 'Pontos insuficientes. Necessário: ' . $totalPointsNeeded . ', Disponível: ' . $userPointsBalance,
                    'status' => 400,
                ], 400);
            }
            $config = $request->config ?? [];
            //data no padrão brasileiro
            // $dataHoraAtual = now()->format('d/m/Y H:i:s');
            $nomeCliente = $user->name;
            $ipCliente = $request->header('X-Forwarded-For') ?? $request->ip();
            $dataSave = [
                'user_id' => $user->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'points_used' => $totalPointsNeeded,
                'unit_points' => $unitPoints,
                'status' => 'pending',
                'config' => $config,
                'notes' => 'Resgate solicitado via Loja de pontos por ' . $nomeCliente.' ::: IP: '.$ipCliente,
            ];
            // dd($dataSave);
            //ip da conexão do cliente
            // Criar o registro de resgate
            $redemption = \App\Models\Redemption::create($dataSave);

            // Criar snapshot do produto
            $redemption->createProductSnapshot();

            // Registrar débito de pontos
            \App\Models\Point::create([
                'client_id' => $user->id,
                'valor' => $totalPointsNeeded*(-1),
                'data' => now(),
                'description' => 'Resgate de produto: ' . $product['post_title'] . ' (Qtd: ' . $quantity . ')',
                'tipo' => 'debito',
                'origem' => 'resgate_produto',
                'status' => 'ativo',
                'pedido_id' => $redemption->id,
                'config' => [
                    'redemption_id' => $redemption->id,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                ],
            ]);

            // Atualizar estoque do produto
            $newStock = $stock - $quantity;
            $config = $product['config'];
            $config['stock'] = $newStock;

            Qlib::update_postmeta($productId, 'stock', $newStock);
            //atualizar o estoque no campo comment_count da tabela post
            Product::where('ID', $productId)->update(['comment_count' => $newStock]);

            // Disparar job para envio de notificações por email
            $productModel = Product::find($productId);
            \App\Jobs\SendRedemptionNotification::dispatch(
                $user,
                $productModel,
                $redemption,
                $quantity,
                $totalPointsNeeded
            );

            return response()->json([
                'exec' => true,
                'message' => 'Resgate processado com sucesso',
                'data' => [
                    'redemption_id' => $redemption->id,
                    'product_name' => $product['post_title'],
                    'quantity' => $quantity,
                    'points_used' => $totalPointsNeeded,
                    'remaining_points' => $userPointsBalance - $totalPointsNeeded,
                    'status' => $redemption->status,
                    'estimated_delivery' => $product['config']['delivery_time'] ?? 'Não informado',
                ],
                'status' => 200,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'exec' => false,
                'message' => 'Erro interno do servidor: ' . $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    /**
     * Lista os resgates de um usuário
     * Retorna os resgates com informações detalhadas do produto
     */
    public function getUserRedemptions(Request $request)
    {
        try {
            $user = $request->user();
            $user_id = $user->id;
            // Buscar resgates do usuário com relacionamentos
            $redemptions = \App\Models\Redemption::with(['product', 'user'])
                ->doUsuario($user_id)
                ->ativos()
                ->orderBy('created_at', 'desc')
                ->get();

            // Mapear dados para o formato solicitado
            $mappedRedemptions = $redemptions->map(function ($redemption) {
                $product = $redemption->product;
                $categoryData = null;
                $productImage = null;

                if ($product) {
                     // Obter dados da categoria
                     $categoryData = \App\Services\Qlib::get_category_by_id($product->guid);

                     // Obter imagem do produto
                     $image = $product->config['image'] ?? null;
                     if ($image) {
                         $productImage = str_replace('{image}', $image, \App\Services\Qlib::qoption('link_files'));
                     }
                 }

                // Gerar código de rastreamento fictício baseado no ID
                // $trackingCode = 'BR' . str_pad($redemption->id, 9, '0', STR_PAD_LEFT);
                $trackingCode = false;

                return [
                    'id' => Qlib::redeem_code($redemption->id),
                    'productId' => (string)$redemption->product_id,
                    'productName' => $product ? $product->post_title : 'Produto não encontrado',
                    'productImage' => $productImage ?: 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=400',
                    'pointsUsed' => (int)$redemption->points_used,
                    'redemptionDate' => $redemption->created_at->format('Y-m-d'),
                    'status' => $this->mapRedemptionStatus($redemption->status),
                    'trackingCode' => $trackingCode,
                    'category' => $categoryData['name'] ?? 'Categoria não definida'
                ];
            });
            //incluir um no de pontos do usuario
            $pc = new PointController();
            $saldo = $pc->saldo($user_id);
            $user->points = $saldo;
            $responseData['user'] = $user->toArray();

            return response()->json([
                'success' => true,
                'data' => $mappedRedemptions,
                'user' => $responseData['user'],
                'message' => 'Resgates listados com sucesso'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar resgates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mapeia o status do resgate para um formato mais amigável
     */
    private function mapRedemptionStatus($status)
    {
        $statusMap = [
            'pending' => 'pending',
            'processing' => 'processing',
            'confirmed' => 'confirmed',
            'shipped' => 'shipped',
            'delivered' => 'delivered',
            'cancelled' => 'cancelled'
        ];

        return $statusMap[$status] ?? $status;
    }
}
