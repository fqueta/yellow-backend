<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Redemption;
use App\Models\RedemptionStatusHistory;
use App\Models\Point;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Notifications\AdminRedemptionRefundNotification;
use App\Notifications\UserRedemptionRefundNotification;
use App\Services\PermissionService;
use App\Services\Qlib;
use App\Jobs\SendRedemptionStatusUpdateNotification;
use App\Jobs\SendRedemptionNotification;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RedeemController extends Controller
{
    protected $permissionService;
    protected $post_type;
    protected $pointController;
    /**
     * Construtor do controller
     */
    public function __construct()
    {
        $this->permissionService = new PermissionService();
        $this->post_type = 'products';
        $this->pointController = new PointController();
    }

    /**
     * Processar resgate de pontos por produto
     *
     * Recebe `product_id`, `quantity` e opcionalmente `config`, valida o estoque
     * e o saldo de pontos do usuário, cria o registro de resgate, debita pontos
     * e atualiza o estoque do produto. Também dispara notificação assíncrona.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function redeem(Request $request)
    {
        try {
            // Validar dados de entrada
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
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

            // Verificar estoque disponível (usa comment_count como estoque)
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
            $nomeCliente = $user->name;
            $ipCliente = $request->header('X-Forwarded-For') ?? $request->ip();
            //preciso saber o proprietário do cliente para associar o resgate ao cliente
            $autor = $user->autor;
            $dataSave = [
                'user_id' => $user->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'points_used' => $totalPointsNeeded,
                'unit_points' => $unitPoints,
                'status' => 'pending',
                'autor' => $autor,
                'config' => $config,
                'notes' => 'Resgate solicitado via Loja de pontos por ' . $nomeCliente . ' ::: IP: ' . $ipCliente,
            ];
            // dd($dataSave,$user);

            // Criar o registro de resgate
            $redemption = \App\Models\Redemption::create($dataSave);

            // Criar snapshot do produto
            $redemption->createProductSnapshot();

            // Registrar débito de pontos
            \App\Models\Point::create([
                'client_id' => $user->id,
                'valor' => $totalPointsNeeded * (-1),
                'data' => now(),
                'description' => 'Resgate de produto: ' . $product['post_title'] . ' (Qtd: ' . $quantity . ')',
                'tipo' => 'debito',
                'origem' => 'resgate_produto',
                'status' => 'ativo',
                'autor' => $autor,
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
            // atualizar o estoque no campo comment_count da tabela post
            Product::where('ID', $productId)->update(['comment_count' => $newStock]);

            // Disparar job para envio de notificações por email
            $productModel = Product::find($productId);
            SendRedemptionNotification::dispatch(
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
     * Lista resgates (admin) com filtros e paginação
     *
     * Aceita filtros:
     * - page: página atual (default: 1)
     * - per_page: itens por página (default: 10)
     * - dateFrom|date_from: filtra data inicial (created_at >=)
     * - dateTo|date_to: filtra data final (created_at <=)
     * - category: filtra pela categoria do produto (Category.name)
     * - status, user_id, product_id: filtros existentes mantidos
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            // Verificar permissão de visualização
            if (!$this->permissionService->isHasPermission('view')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $perPage = $request->input('per_page', 10);
            $page = (int) $request->input('page', 1);
            $orderBy = $request->input('order_by', 'created_at');
            $order = $request->input('order', 'desc');
            // Normalizar datas e categoria (suporta snake_case e camelCase)
            $dateFrom = $request->input('dateFrom', $request->input('date_from'));
            $dateTo = $request->input('dateTo', $request->input('date_to'));
            $categoryName = $request->input('category');

            // Query base com relacionamentos
            $query = Redemption::with(['product', 'user'])
                ->ativos()
                ->orderBy($orderBy, $order)
                ->where('excluido', 'n');

            //se a permission_id dele form maior ou igual a 5, então é um parceiro e pode ver todos os resgates
            if((int)$user->permission_id >= 5){
                $query->where('autor', $user->id);
                //verificar a string sql completa para debug sem ? na string
                // dd([
                //     'sql' => vsprintf(str_replace('?', "'%s'", $query->toSql()), $query->getBindings()),
                //     'bindings' => $query->getBindings(),
                //     'query' => $query->get(),
                //     'count' => $query->count(),
                // ]);
                // dd($query->get());
            }
            // Filtros opcionais
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }

            if ($request->filled('product_id')) {
                $query->where('product_id', $request->input('product_id'));
            }

            // Filtro por período
            if (!empty($dateFrom)) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }
            if (!empty($dateTo)) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            // Filtro por categoria do produto (Category.name) via guid
            if (!empty($categoryName)) {
                try {
                    $categoryIds = \App\Models\Category::where('name', $categoryName)->pluck('id');
                    if ($categoryIds && $categoryIds->count() > 0) {
                        $query->whereHas('product', function ($q) use ($categoryIds) {
                            $q->whereIn('guid', $categoryIds);
                        });
                    } else {
                        // Nenhuma categoria encontrada => força retorno vazio
                        $query->whereRaw('1 = 0');
                    }
                } catch (\Exception $e) {
                    // Em caso de erro ao buscar categoria, não aplicar filtro
                }
            }

            // Buscar com paginação
            $redemptions = $query->paginate($perPage, ['*'], 'page', $page);

            // Mapear dados para o formato solicitado
            $mappedRedemptions = $redemptions->getCollection()->map(function ($redemption) {
                return $this->mapRedemptionData($redemption);
            });

            // Preparar resposta com paginação
            $response = [
                'data' => [
                    'data' => $mappedRedemptions,
                    'current_page' => $redemptions->currentPage(),
                    'last_page' => $redemptions->lastPage(),
                    'per_page' => $redemptions->perPage(),
                    'total' => $redemptions->total(),
                    'filters' => [
                        'page' => $page,
                        'per_page' => $perPage,
                        'dateFrom' => $dateFrom,
                        'dateTo' => $dateTo,
                        'category' => $categoryName,
                        'status' => $request->input('status'),
                        'user_id' => $request->input('user_id'),
                        'product_id' => $request->input('product_id'),
                    ],
                ],
                'message' => 'Resgates listados com sucesso',
                'success' => true
            ];

            return response()->json($response, 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar resgates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mapeia os dados do resgate para o formato da API
     */
    private function mapRedemptionData($redemption)
    {
        $product = $redemption->product;
        $user = $redemption->user;
        $categoryData = null;
        $productImage = null;
        $productImage2 = null;
        // Determinar telefone do usuário com múltiplos fallbacks (prioriza config.celular)
        $userPhone = null;
        if ($user) {
            try {
                $configArr = is_array($user->config)
                    ? $user->config
                    : (is_string($user->config) ? (json_decode($user->config, true) ?? []) : []);
                // Prioridade: config['celular'] > user.phone > user.telefone > config['phone'] > config['telefone']
                $userPhone = $configArr['celular']
                    ?? $user->phone
                    ?? $user->telefone
                    ?? ($configArr['phone'] ?? $configArr['telefone'] ?? null);
            } catch (\Exception $e) {
                $userPhone = $user->phone ?? $user->telefone ?? null;
            }
        }
        if ($product) {
            // Obter dados da categoria apenas se guid não for null
            if (!empty($product->guid)) {
                try {
                    $categoryData = Qlib::get_category_by_id($product->guid);
                } catch (\Exception $e) {
                    $categoryData = null;
                }
            }

            // Obter imagem do produto
            $image = $product->config['image'] ?? null;
            if ($image) {
                $productImage = str_replace('{image}', $image, Qlib::qoption('link_files'));
            }
            // Obter segunda imagem do produto
            $image2 = $product->config['image2'] ?? null;
            if ($image2) {
                $productImage2 = str_replace('{image}', $image2, Qlib::qoption('link_files'));
            }
        }

        // Gerar código de rastreamento baseado no ID
        $trackingCode =  isset($redemption->config['tracking_code']) ? $redemption->config['tracking_code'] : '';//'BR' . str_pad($redemption->id, 9, '0', STR_PAD_LEFT);

        // Calcular data estimada de entrega (7 dias após o resgate)
        $estimatedDelivery = $redemption->created_at->addDays(7);

        // Determinar prioridade baseada nos pontos usados
        $priority = $this->calculatePriority($redemption->points_used);

        // Mapear histórico de status se disponível
        $statusHistory = [];
        if ($redemption->relationLoaded('statusHistory')) {
            $statusHistory = $redemption->statusHistory->map(function ($history) {
                return $history->toApiFormat();
            })->toArray();
        }

        // Processar endereço de entrega do JSON delivery_address
        $shippingAddress = null;
        if ($redemption->delivery_address) {
            $deliveryData = is_string($redemption->delivery_address)
                ? json_decode($redemption->delivery_address, true)
                : $redemption->delivery_address;

            if ($deliveryData) {
                $shippingAddress = [
                    'street' => $deliveryData['street'] ?? $deliveryData['endereco'] ?? '',
                    'neighborhood' => $deliveryData['neighborhood'] ?? $deliveryData['bairro'] ?? '',
                    'city' => $deliveryData['city'] ?? $deliveryData['cidade'] ?? '',
                    'state' => $deliveryData['state'] ?? $deliveryData['estado'] ?? '',
                    'zipCode' => $deliveryData['zipCode'] ?? $deliveryData['cep'] ?? '',
                    'complement' => $deliveryData['complement'] ?? $deliveryData['complemento'] ?? ''
                ];
            }
        }

        return [
            'id' => Qlib::redeem_id($redemption->id),
            'userId' => $user ? 'U' . str_pad($user->id, 3, '0', STR_PAD_LEFT) : null,
            'userName' => $user ? $user->name : 'Usuário não encontrado',
            'userEmail' => $user ? $user->email : null,
            'userPhone' => $userPhone,
            'clientPhone' => $userPhone, // alias para compatibilidade
            'phone' => $userPhone, // alias simples
            'productId' => $product ? 'P' . str_pad($product->ID, 3, '0', STR_PAD_LEFT) : null,
            'productName' => $product ? $product->post_title : 'Produto não encontrado',
            'productImage' => $productImage ?: '/placeholder.svg',
            'productImage2' => $productImage2 ?: '',
            'productCategory' => $categoryData['name'] ?? 'Categoria não definida',
            'pointsUsed' => (int)$redemption->points_used,
            'status' => $this->mapRedemptionStatus($redemption->status),
            'shippingAddress' => $shippingAddress,
            'trackingCode' => $trackingCode,
            'estimatedDelivery' => $redemption->estimated_delivery_date ? \Carbon\Carbon::parse($redemption->estimated_delivery_date)->toISOString() : null,
            'notes' => $redemption->notes ?? null,
            'createdAt' => $redemption->created_at->toISOString(),
            'updatedAt' => $redemption->updated_at->toISOString(),
            // Campos mantidos para compatibilidade
            'redemptionDate' => $redemption->created_at->toISOString(),
            'priority' => $priority,
            'actualDelivery' => $redemption->actual_delivery_date ? \Carbon\Carbon::parse($redemption->actual_delivery_date)->toISOString() : null,
            'adminNotes' => $redemption->admin_notes ?? null,
            'statusHistory' => $statusHistory
        ];
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

    /**
     * Exibe um resgate específico
     * GET /point-store/redemptions/{id}
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            // Verificar permissão de visualização
            if (!$this->permissionService->isHasPermission('view')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            // Converter ID se necessário
            $id = Qlib::redeem_id($id);

            // Buscar o resgate com relacionamentos e histórico
            $redemption = Redemption::with(['product', 'user', 'statusHistory'])
                ->where('id', $id)
                ->where('excluido', 'n')
                ->where('deletado', 'n')
                ->first();

            if (!$redemption) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resgate não encontrado'
                ], 404);
            }

            // Mapear dados do resgate
            $mappedData = $this->mapRedemptionData($redemption);

            return response()->json([
                'success' => true,
                'message' => 'Resgate encontrado com sucesso',
                'data' => $mappedData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar resgate: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza o status de um resgate específico
     * PATCH /admin/redemptions/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            // Verificar permissão de edição
            if (!$this->permissionService->isHasPermission('edit')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            // Validar dados de entrada
             $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                 'status' => 'required|string|in:pending,processing,confirmed,shipped,delivered,cancelled',
                 'comment' => 'nullable|string|max:500',
                 'notes' => 'nullable|string|max:500',
                 'trackingCode' => 'nullable|string|max:255',
             ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }
            $id = Qlib::redeem_id($id);
            // Buscar o resgate
            $redemption = Redemption::where('id', $id)
                ->where('excluido', 'n')
                ->where('deletado', 'n')
                ->first();

            if (!$redemption) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resgate não encontrado'
                ], 404);
            }
            // dd($request->all());
            $oldStatus = $redemption->status;
            $newStatus = $request->input('status');
            $comment = $request->input('notes') ? $request->input('notes') : $request->input('comment', 'Status atualizado');
            $config = [
                'tracking_code' => $request->input('trackingCode'),
            ];
            // Verificar se houve mudança de status
            if ($oldStatus !== $newStatus) {
                // Registrar histórico de status
                RedemptionStatusHistory::createHistory(
                    $redemption->id,
                    $oldStatus,
                    $newStatus,
                    $comment,
                    'U' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                    $user->name
                );

                // Atualizar o status
                $redemption->status = $newStatus;

                // Se o status for 'delivered', definir a data de entrega
                if ($newStatus === 'delivered' && $oldStatus !== 'delivered') {
                    $redemption->actual_delivery_date = now()->setTimezone(config('app.timezone'))->format('Y-m-d');
                }
                if($request->get('trackingCode')){
                    $redemption->config = $config;
                }

                // Adicionar nota administrativa sobre a mudança
                $adminNote = "Status alterado de '{$this->translateStatus($oldStatus)}' para '{$this->translateStatus($newStatus)}' por {$user->name} em " . now()->format('d/m/Y H:i:s');
                $adminNote .= "\nComentário: {$comment}";

                if ($redemption->admin_notes) {
                    $redemption->admin_notes .= "\n" . $adminNote;
                } else {
                    $redemption->admin_notes = $adminNote;
                }

                $redemption->updated_at = now();
                $redemption->save();
            }else{
                if($request->get('trackingCode')){
                    $redemption->config = $config;
                    $redemption->save();
                }
            }

            // Enviar notificação de atualização de status através da fila
            if ($oldStatus !== $newStatus) {
                SendRedemptionStatusUpdateNotification::dispatch(
                    $redemption,
                    $oldStatus,
                    $newStatus,
                    $user
                );

                Log::info('Notificação de atualização de status enviada para a fila', [
                    'redemption_id' => $redemption->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'user_email' => $redemption->user->email ?? 'N/A'
                ]);
            }

            // Preparar resposta com dados atualizados
            $redemption->load(['product', 'user']);
            $mappedData = $this->mapRedemptionData($redemption);

            return response()->json([
                'success' => true,
                'message' => 'Status do resgate atualizado com sucesso',
                'data' => $mappedData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar status do resgate: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Metodo para traduzir o status do ingles para portugues
     *
     */
    private function translateStatus($status)
    {
        $statusMap = [
            'pending' => 'pendente',
            'processing' => 'processando',
            'confirmed' => 'confirmado',
            'shipped' => 'enviado',
            'delivered' => 'entregue',
            'cancelled' => 'cancelado',
        ];

        return $statusMap[$status] ?? $status;
    }
    /**
     * Calcula a prioridade baseada nos pontos usados
     */
    private function calculatePriority($pointsUsed)
    {
        if ($pointsUsed >= 20000) {
            return 'high';
        } elseif ($pointsUsed >= 10000) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    /**
     * Extorno de resgate: credita pontos e notifica admin e cliente
     * POST /admin/redemptions/{id}/refund
     */
    public function refund(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            // Verificar permissão de edição
            if (!$this->permissionService->isHasPermission('edit')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            // Validar dados de entrada
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'reason' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }
            $id = Qlib::redeem_id($id);
            // dd($id);

            // Buscar o resgate com relacionamentos
            $redemption = Redemption::with(['product', 'user'])
                ->where('id', $id)
                ->where('excluido', 'n')
                ->where('deletado', 'n')
                ->first();

            if (!$redemption) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resgate não encontrado'
                ], 404);
            }

            // Se já estiver cancelado, não repetir extorno
            if ($redemption->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Resgate já está cancelado'
                ], 422);
            }

            $reason = $request->input('reason');
            $pointsToCredit = (int) $redemption->points_used;
            $oldStatus = $redemption->status;
            $newStatus = 'cancelled';
            // dd($redemption);
            DB::transaction(function () use ($redemption, $user, $pointsToCredit, $reason, $oldStatus, $newStatus) {
                // Lançar crédito de pontos no extrato
                $d_estorno = [
                    'client_id' => $redemption->user_id,
                    'valor' => $pointsToCredit,
                    'tipo' => 'credito',
                    'origem' => 'refund',
                    'pedido_id' => $redemption->id,
                    'description' => 'Estorno do resgate #' . Qlib::redeem_id($redemption->id),
                    'data_expiracao' => now()->addYear(),
                    'usuario_id' => $user->id,
                    'autor' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'data' => now()->format('Y-m-d'),
                ];
                // dd($d_estorno);
                Point::create($d_estorno);

                // Registrar histórico de status
                RedemptionStatusHistory::createHistory(
                    $redemption->id,
                    $oldStatus,
                    $newStatus,
                    $reason ? ('Estorno: ' . $reason) : 'Estorno realizado',
                    'U' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                    $user->name
                );

                // Atualizar o status e notas administrativas
                $redemption->status = $newStatus;
                $adminNote = "Resgate estornado e cancelado por {$user->name} em " . now()->setTimezone(config('app.timezone'))->format('d/m/Y H:i:s');
                if ($reason) {
                    $adminNote .= "\nMotivo: {$reason}";
                }
                if ($redemption->admin_notes) {
                    $redemption->admin_notes .= "\n" . $adminNote;
                } else {
                    $redemption->admin_notes = $adminNote;
                }
                $redemption->updated_at = now();
                $redemption->save();
            });

            // Notificar cliente sobre o extorno e cancelamento
            if ($redemption->user && $redemption->user->email) {
                $redemption->user->notify(new UserRedemptionRefundNotification($redemption, $pointsToCredit, $reason, $user));
            }

            // Notificar administradores sobre o extorno
            $admins = User::whereIn('permission_id', [1,2])
                ->whereNotNull('email')
                ->get();
            if ($admins->count() > 0) {
                Notification::send($admins, new AdminRedemptionRefundNotification($redemption, $pointsToCredit, $reason, $user));
            }

            // Enviar notificação de atualização de status através da fila (mantém padrão existente)
            SendRedemptionStatusUpdateNotification::dispatch(
                $redemption,
                $oldStatus,
                $newStatus,
                $user
            );

            // Preparar resposta com dados atualizados
            $redemption->load(['product', 'user']);
            $mappedData = $this->mapRedemptionData($redemption);

            return response()->json([
                'success' => true,
                'message' => 'Extorno realizado: pontos creditados e notificações enviadas',
                'data' => $mappedData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao realizar extorno: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Excluir (cancelar) um resgate específico
     * DELETE /admin/redemptions/{id}
     *
     * Regras:
     * - Verifica usuário autenticado e permissão de "delete".
     * - Se não for admin (permission_id não em [1,2]), só pode excluir resgates cujo autor seja o próprio usuário.
     * - Normaliza o ID com Qlib::redeem_id.
     * - Marca exclusão lógica (deletado = 's') e registra histórico de status para 'cancelled'.
     * - Não realiza crédito de pontos (para crédito use o endpoint de refund).
     *
     * @param Request $request
     * @param string|int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            // Verificar permissão de exclusão
            if (!$this->permissionService->isHasPermission('delete')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            // Normalizar ID de resgate
            $id = Qlib::redeem_id($id);

            // Buscar resgate ativo (carrega relação usuário apenas por conveniência)
            $redemption = Redemption::with(['user'])
                ->where('id', $id)
                ->where('excluido', 'n')
                ->where('deletado', 'n')
                ->first();

            if (!$redemption) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resgate não encontrado'
                ], 404);
            }

            $isAdmin = in_array($user->permission_id, [1, 2]);
            // dd($redemption);
            // Se não for admin, garantir que o autor do resgate é o próprio usuário
            if (!$isAdmin) {
                if ((string)$redemption->autor !== (string)$user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Você não pode excluir pedidos de outros proprietários'
                    ], 403);
                }
            }
            //excluir do extrato de pontos
            // $this->pointService->deleteFromPointsHistory($redemption->id);

            $oldStatus = $redemption->status;

            // Registrar histórico de cancelamento
            RedemptionStatusHistory::createHistory(
                $redemption->id,
                $oldStatus,
                'cancelled',
                'Resgate cancelado/excluído por ' . $user->name,
                'U' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                $user->name
            );
            //excluir do extrato de pontos
            $this->pointController->deleteFromPointsHistory($redemption->id);

            // Marcar exclusão lógica
            $redemption->status = 'cancelled';
            $redemption->deletado = 's';
            $redemption->updated_at = now();
            $redemption->save();

            return response()->json([
                'success' => true,
                'message' => 'Resgate cancelado e excluído com sucesso'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir resgate: ' . $e->getMessage()
            ], 500);
        }
    }
}
