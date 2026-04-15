<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminMaintenanceAccess
{
    /**
     * Bloqueia usuários com permission_id > 1 quando o modo manutenção estiver ativo.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $permissionService = app(PermissionService::class);

        if (!$permissionService->shouldBlockForMaintenance((int) $user->permission_id)) {
            return $next($request);
        }

        $currentToken = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        if ($currentToken) {
            $currentToken->delete();
        } elseif (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        return $permissionService->maintenanceModeResponse();
    }
}
