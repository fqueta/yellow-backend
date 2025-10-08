<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;

class PublicFormTokenController extends Controller
{
    /**
     * Gera um token para formulários públicos
     * Este token deve ser usado em formulários públicos para validação anti-spam
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateToken(Request $request): JsonResponse
    {
        try {
            // Gerar token único
            $token = Str::random(64);
            
            // Dados do token para validação
            $tokenData = [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now()->format('Y-m-d H:i:s'),
                'expires_at' => now()->addMinutes(120)->format('Y-m-d H:i:s')
            ];
            
            $cacheKey = 'form_token:' . $token;
            
            // Armazenar token no cache por 2 horas usando store 'file'
            try {
                Cache::store('file')->put($cacheKey, $tokenData, 7200); // 2 horas em segundos
            } catch (\Exception $cacheException) {
                // Se o cache falhar, continua sem ele por enquanto
                Log::warning('Falha ao armazenar token no cache', ['error' => $cacheException->getMessage()]);
            }
            
            // Log da geração do token para auditoria
            try {
                Log::info('Token de formulário público gerado', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'token_hash' => hash('sha256', $token)
                ]);
            } catch (\Exception $logException) {
                // Se o log falhar, continua sem ele
            }
            
            return response()->json([
                'success' => true,
                'token' => $token,
                'expires_in' => 7200, // 2 horas em segundos
                'message' => 'Token gerado com sucesso'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao gerar token de formulário público', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Erro interno do servidor',
                'message' => 'Não foi possível gerar o token. Tente novamente.',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    
    /**
     * Valida se um token ainda é válido (sem consumi-lo)
     * Útil para verificações do frontend
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validateToken(Request $request): JsonResponse
    {
        $token = $request->input('token');
        
        if (!$token) {
            return response()->json([
                'valid' => false,
                'message' => 'Token não fornecido'
            ], 422);
        }
        
        $cacheKey = 'form_token:' . $token;
        
        // Usar cache store 'file' que não é afetado pelo tenancy
        $cache = Cache::store('file');
        
        if ($cache->has($cacheKey)) {
            $tokenData = $cache->get($cacheKey);
            
            return response()->json([
                'valid' => true,
                'expires_at' => $tokenData['expires_at'],
                'message' => 'Token válido'
            ]);
        }
        
        return response()->json([
            'valid' => false,
            'message' => 'Token inválido ou expirado'
        ]);
    }
}