<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\Qlib;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PHPUnit\Architecture\Services\ServiceContainer;

class ServiceOrderController extends Controller
{
    protected $permissionService;

    public function __construct()
    {
        $this->permissionService = new PermissionService;
    }

    /**
     * Display a listing of service orders.
     */
    public function index(Request $request): JsonResponse
    {
        // Check permissions
        $user = request()->user();
        if (!$user) {
            return response()->json(['message' => 'Acesso negado'], 403);
        }

        if (!$this->permissionService->isHasPermission('view')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $query = ServiceOrder::with([
            'items.product',
            'items.service',
            'aircraft',
            'client',
            'assignedUser'
        ]);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->has('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->has('aircraft_id')) {
            $query->where('object_id', $request->aircraft_id)
                  ->where('object_type', 'aircraft');
        }

        if ($request->has('object_id')) {
            $query->where('object_id', $request->object_id);
        }

        if ($request->has('object_type')) {
            $query->where('object_type', $request->object_type);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $serviceOrders = $query->paginate($perPage);

        // Transform data
        $responseData = $serviceOrders->toArray();
        $responseData['data'] = array_map([$this, 'transformServiceOrder'], $responseData['data']);
// dd($responseData);
        return response()->json([
            'success' => true,
            'data' => $responseData['data'],
        ]);
    }

    /**
     * Store a newly created service order.
     */
    public function store(Request $request): JsonResponse
    {
        // Check permissions
        $user = request()->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('create')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $validator = $this->validateServiceOrder($request);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'body' => $validator->errors(),
                'status' => 422,
            ], 422);
        }

        try {
            DB::beginTransaction();
            // Gerar token único
            $request->merge(['token' => Qlib::token()]);
            // Create service order
            $serviceOrder = ServiceOrder::create($request->only([
                'doc_type',
                'title',
                'token',
                'description',
                'object_id',
                'object_type',
                'assigned_to',
                'client_id',
                'status',
                'priority',
                'estimated_start_date',
                'estimated_end_date',
                'actual_start_date',
                'actual_end_date',
                'notes',
                'internal_notes'
            ]));
            // Add products
            if ($request->has('products') && is_array($request->products)) {
                $this->addItemsToOrder($serviceOrder, $request->products, 'product');
            }
            // Add services
            if ($request->has('services') && is_array($request->services)) {
                $this->addItemsToOrder($serviceOrder, $request->services, 'service');
            }

            // Calculate total amount
            $serviceOrder->calculateTotalAmount();

            DB::commit();

            // Load relationships for response
            $serviceOrder->load([
                'items.product',
                'items.service',
                'aircraft',
                'client',
                'assignedUser'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ordem de serviço criada com sucesso',
                'data' => $this->transformServiceOrder($serviceOrder)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar ordem de serviço: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified service order.
     */
    public function show(Request $request, $id): JsonResponse
    {
        // Check permissions
        if (!$this->permissionService->isHasPermission('view')) {
            return response()->json(['message' => 'Acesso negado'], 403);
        }
        $serviceOrder = ServiceOrder::find($id);
        if (!$serviceOrder) {
            return response()->json(['message' => 'Ordem de serviço não encontrada'], 404);
        }
        // $serviceOrder->load([
        //     'items.product',
        //     'items.service',
        //     'aircraft',
        //     'client',
        //     'assignedUser'
        // ]);

        return response()->json([
            'success' => true,
            'data' => $this->transformServiceOrder($serviceOrder)
        ]);
    }

    /**
     * Update the specified service order.
     */
    public function update(Request $request, $id): JsonResponse
    {
        // Check permissions
        if (!$this->permissionService->isHasPermission('edit')) {
            return response()->json(['message' => 'Acesso negado'], 403);
        }

        $validator = $this->validateServiceOrder($request);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Update service order
            // dd($request->only([
            //     'title',
            //     'description',
            //     'object_id',
            //     'object_type',
            //     'assigned_to',
            //     'client_id',
            //     'status',
            //     'priority',
            //     'estimated_start_date',
            //     'estimated_end_date',
            //     'actual_start_date',
            //     'actual_end_date',
            //     'notes',
            //     'internal_notes'
            // ]),$request->all());
            // dd($request->all());
            $serviceOrder = ServiceOrder::find($id)->update($request->only([
                'doc_type',
                'title',
                'description',
                'object_id',
                'object_type',
                'assigned_to',
                'client_id',
                'status',
                'priority',
                'estimated_start_date',
                'estimated_end_date',
                'actual_start_date',
                'actual_end_date',
                'notes',
                'internal_notes'
            ]));

            // dd($serviceOrder);
            // Recarregar o modelo do banco de dados
            $serviceOrder = ServiceOrder::find($id);
            if (!$serviceOrder) {
                throw new \Exception('Service Order not found after update');
            }

            // Update items if provided
            if ($request->has('products') || $request->has('services')) {
                // Remove existing items
                // dd($serviceOrder->items());
                $serviceOrder->items()->delete();

                // Add new products
                if ($request->has('products') && is_array($request->products)) {
                    $this->addItemsToOrder($serviceOrder, $request->products, 'product');
                }

                // Add new services
                if ($request->has('services') && is_array($request->services)) {
                    $this->addItemsToOrder($serviceOrder, $request->services, 'service');
                }

                // Recalculate total amount
                $serviceOrder->calculateTotalAmount();
            }

            DB::commit();

            // Load relationships for response
            $serviceOrder->load([
                'items.product',
                'items.service',
                'aircraft',
                'client',
                'assignedUser'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ordem de serviço atualizada com sucesso',
                'data' => $this->transformServiceOrder($serviceOrder)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar ordem de serviço: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified service order (soft delete).
     */
    public function destroy(Request $request, ServiceOrder $serviceOrder): JsonResponse
    {
        // Check permissions
        $user = request()->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->permissionService->isHasPermission('delete')) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        try {
            $serviceOrder->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ordem de serviço movida para lixeira com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao mover ordem de serviço para lixeira: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display trashed service orders.
     */
    public function trash(Request $request): JsonResponse
    {
        // Check permissions
        if (!$this->permissionService->isHasPermission($request)) {
            return response()->json(['message' => 'Acesso negado'], 403);
        }

        $query = ServiceOrder::onlyTrashed()->with([
            'items.product',
            'items.service',
            'aircraft',
            'client',
            'assignedUser'
        ]);

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'deleted_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $serviceOrders = $query->paginate($perPage);

        // Transform data
        $responseData = $serviceOrders->toArray();
        $responseData['data'] = array_map([$this, 'transformServiceOrder'], $responseData['data']);

        return response()->json([
            'success' => true,
            'data' => $responseData,
        ]);
    }
    /**
     * Para atualizar o status da ordem de serviço
     */
    public function updateStatus(Request $request, $id)
    {
        // Check permissions
        if (!$this->permissionService->isHasPermission('edit')) {
            return response()->json(['message' => 'Acesso negado'], 403);
        }

        // $validator = $this->validateServiceOrder($request, [
        //         'status' => 'required|in:draft,pending,in_progress,completed,cancelled,on_hold,approved'
        //     ],
        //     [
        //         'status.required' => 'O status é obrigatório',
        //         'status.in' => 'O status selecionado é inválido',
        //     ]);
        // if ($validator->fails()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Dados inválidos',
        //         'errors' => $validator->errors()
        //     ], 422);
        // }

        // Check if status is valid
        $validStatuses = ['draft', 'pending', 'in_progress', 'completed', 'cancelled', 'on_hold', 'approved'];
        if (!in_array($request->status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Status inválido'
            ], 422);
        }

        try {
            $serviceOrder = ServiceOrder::findOrFail($id);
            $serviceOrder->update($request->only([
                'status'
            ]));
            return response()->json([
                'success' => true,
                'message' => 'Status atualizado com sucesso',
                'data' => $this->transformServiceOrder($serviceOrder)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar ordem de serviço: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a trashed service order.
     */
    public function restore(Request $request, $id): JsonResponse
    {
        // Check permissions
        if (!$this->permissionService->isHasPermission($request)) {
            return response()->json(['message' => 'Acesso negado'], 403);
        }

        try {
            $serviceOrder = ServiceOrder::onlyTrashed()->findOrFail($id);
            $serviceOrder->restore();

            return response()->json([
                'success' => true,
                'message' => 'Ordem de serviço restaurada com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao restaurar ordem de serviço: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Permanently delete a service order.
     */
    public function forceDelete(Request $request, $id): JsonResponse
    {
        // Check permissions
        if (!$this->permissionService->isHasPermission($request)) {
            return response()->json(['message' => 'Acesso negado'], 403);
        }

        try {
            $serviceOrder = ServiceOrder::onlyTrashed()->findOrFail($id);
            $serviceOrder->forceDelete();

            return response()->json([
                'success' => true,
                'message' => 'Ordem de serviço excluída permanentemente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir ordem de serviço permanentemente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate service order data.
     */
    private function validateServiceOrder(Request $request, $rules = [],$messages=[])
    {
        // Mapear aircraft_id para object_id se fornecido para compatibilidade
        // dd($request->all());
        if ($request->has('aircraft_id') && !$request->has('object_id')) {
            $request->merge([
                'object_id' => $request->aircraft_id,
                'object_type' => 'aircraft'
            ]);
        }
        // $cliente_permission_id = Qlib::qoption('cliente_permission_id')??5;
        if(count($rules) != 0){
            $rules = [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'object_id' => 'required|integer|min:1',
                'object_type' => 'required|in:aircraft,equipment,vehicle,facility',
                'aircraft_id' => 'nullable|integer|min:1', // Para compatibilidade
                'assigned_to' => 'required|exists:users,id',
                'client_id' => 'required',
                // 'client_id' => 'required|exists:users,id,permission_id,'.$cliente_permission_id,
                'status' => 'required|in:draft,pending,in_progress,completed,cancelled,on_hold,approved',
                'priority' => 'required|in:low,medium,high,urgent',
                'estimated_start_date' => 'nullable|date',
                'estimated_end_date' => 'nullable|date|after_or_equal:estimated_start_date',
                'actual_start_date' => 'nullable|date',
                'actual_end_date' => 'nullable|date|after_or_equal:actual_start_date',
                'notes' => 'nullable|string',
                'internal_notes' => 'nullable|string',
                'products' => 'nullable|array',
                'products.*.product_id' => 'required_with:products|exists:posts,ID',
                'products.*.quantity' => 'required_with:products|integer|min:1',
                'products.*.unit_price' => 'required_with:products|numeric|min:0',
                'products.*.total_price' => 'required_with:products|numeric|min:0',
                'products.*.notes' => 'nullable|string',
                'services' => 'nullable|array',
                'services.*.service_id' => 'required_with:services|exists:posts,ID',
                'services.*.quantity' => 'required_with:services|integer|min:1',
                'services.*.unit_price' => 'required_with:services|numeric|min:0',
                'services.*.total_price' => 'required_with:services|numeric|min:0',
                'services.*.notes' => 'nullable|string',
            ];
            $messages = [
                'title.required' => 'O título é obrigatório',
                'description.required' => 'A descrição é obrigatória',
                'object_id.required' => 'O objeto é obrigatório',
                'object_type.required' => 'O tipo de objeto é obrigatório',
                'assigned_to.required' => 'O responsável é obrigatório',
                'assigned_to.exists' => 'O responsável selecionado é inválido',
                'client_id.required' => 'O cliente é obrigatório',
                // 'client_id.exists' => 'O cliente selecionado é inválido ou não é um cliente válido',
                'status.required' => 'O status é obrigatório',
                'priority.required' => 'A prioridade é obrigatória',
            ];
        }
        return Validator::make($request->all(), $rules, $messages);
    }

    /**
     * Add items (products or services) to service order.
     */
    private function addItemsToOrder(ServiceOrder $serviceOrder, array $items, string $itemType)
    {
        // Verificar se o service_order_id é válido
        if (!$serviceOrder->id) {
            throw new \Exception('Service Order ID is null. Cannot add items.');
        }

        foreach ($items as $item) {
            $itemId = $itemType === 'product' ? $item['product_id'] : $item['service_id'];

            ServiceOrderItem::create([
                'service_order_id' => $serviceOrder->id,
                'item_type' => $itemType,
                'item_id' => $itemId,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['total_price'],
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }

    /**
     * Transform service order data for API response.
     */
    private function transformServiceOrder($serviceOrder)
    {
        $assigned_user = User::find($serviceOrder['assigned_to']);
        $data = [
            'id' => $serviceOrder['id'],
            'doc_type' => $serviceOrder['doc_type'],
            'title' => $serviceOrder['title'],
            'description' => $serviceOrder['description'],
            'object_id' => $serviceOrder['object_id'],
            'object_type' => $serviceOrder['object_type'],
            'aircraft_id' => $serviceOrder['object_type'] === 'aircraft' ? $serviceOrder['object_id'] : null, // Para compatibilidade
            'assigned_to' => $serviceOrder['assigned_to'],
            'assigned_user' => $assigned_user,
            'client_id' => $serviceOrder['client_id'],
            'status' => $serviceOrder['status'],
            'priority' => $serviceOrder['priority'],
            'estimated_start_date' => $serviceOrder['estimated_start_date'],
            'estimated_end_date' => $serviceOrder['estimated_end_date'],
            'actual_start_date' => $serviceOrder['actual_start_date'],
            'actual_end_date' => $serviceOrder['actual_end_date'],
            'notes' => $serviceOrder['notes'],
            'internal_notes' => $serviceOrder['internal_notes'],
            'total_amount' => $serviceOrder['total_amount'],
            'created_at' => $serviceOrder['created_at'],
            'updated_at' => $serviceOrder['updated_at'],
            'deleted_at' => $serviceOrder['deleted_at'] ?? null,
        ];
        // Add relationships
        if (isset($serviceOrder['aircraft'])) {
            // dd($serviceOrder['aircraft']);
            $data['aircraft'] = (new AircraftController())->map_aircraft($serviceOrder['aircraft']);
            // dd($data->toArray());
        }
        if (isset($serviceOrder['client'])) {
            $data['client'] = $serviceOrder['client'];
        }

        if (isset($serviceOrder['assigned_user'])) {
            $data['assigned_user'] = $serviceOrder['assigned_user'];
        }

        // Transform items
        $data['products'] = [];
        $data['services'] = [];
        $map_service = new ServiceController();
        $map_product = new ProductController();
        if (isset($serviceOrder['items'])) {
            foreach ($serviceOrder['items'] as $item) {
                $itemData = [
                    'id' => $item['id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'notes' => $item['notes'],
                ];
                if ($item['item_type'] === 'product') {
                    $itemData['product_id'] = $item['item_id'];
                    if (isset($item['product'])) {
                        $itemData['product'] = $map_product->map_product($item['product']);
                    }
                    $data['products'][] = $itemData;
                } elseif ($item['item_type'] === 'service') {
                    $itemData['service_id'] = $item['item_id'];
                    if (isset($item['service'])) {
                        $itemData['service'] = $map_service->map_service($item['service']);
                    }
                    $data['services'][] = $itemData;
                }
            }
        }

        return $data;
    }
}
