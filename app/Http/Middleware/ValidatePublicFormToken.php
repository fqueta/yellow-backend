<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class ValidatePublicFormToken
{
    /**
     * Handle an incoming request.
     * Valida token de formulário público para prevenir spam e ataques automatizados
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // Buscar token no header ou no corpo da requisição
        $token = $request->header('X-Form-Token') ?? $request->input('_token');
        
        if (!$token) {
            return response()->json([
                'error' => 'Token de formulário obrigatório',
                'message' => 'É necessário fornecer um token válido para acessar este recurso'
            ], 422);
        }
        
        $cacheKey = 'form_token:' . $token;
        
        try {
            // Usar cache store 'file' que não é afetado pelo tenancy e persiste entre requisições
            $cache = Cache::store('file');
            
            // Verificar se o token existe no cache
            if (!$cache->has($cacheKey)) {
                return response()->json([
                    'error' => 'Token inválido ou expirado',
                    'message' => 'O token fornecido não é válido ou já expirou. Solicite um novo token.'
                ], 422);
            }
            
            // Obter dados do token para validações adicionais
            $tokenData = $cache->get($cacheKey);
            
            // Validar IP se configurado (opcional - pode ser removido se causar problemas)
            if (isset($tokenData['ip']) && $tokenData['ip'] !== $request->ip()) {
                // Log da tentativa suspeita
                Log::warning('Tentativa de uso de token com IP diferente', [
                    'token_ip' => $tokenData['ip'],
                    'request_ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
            }
            
            // Token pode ser reutilizado durante o período de validade
            // Não remove o token para permitir múltiplos usos
            // O token expirará automaticamente após o tempo configurado
            
        } catch (\Exception $e) {
            Log::error('Erro ao validar token no cache', [
                'error' => $e->getMessage(),
                'token_hash' => hash('sha256', $token)
            ]);
            
            return response()->json([
                'error' => 'Erro interno do servidor',
                'message' => 'Não foi possível validar o token. Tente novamente.'
            ], 500);
        }
        
        return $next($request);
    }
}