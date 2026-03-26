<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use Illuminate\Http\Request;

class SystemLogController extends Controller
{
    /**
     * Retorna a lista de logs do sistema (paginada).
     */
    public function index(Request $request)
    {
        // Validação básica de acesso - apenas permissão 1 (admin)
        $user = $request->user();
        if (!$user || $user->permission_id > 1) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $perPage = $request->input('per_page', 50);
        $query = SystemLog::query()
            ->where('ativo', 's')
            ->where('excluido', 'n')
            ->where('deletado', 'n')
            ->orderBy('created_at', 'desc');

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $logs = $query->paginate($perPage);

        return response()->json(['data' => $logs]);
    }
}
