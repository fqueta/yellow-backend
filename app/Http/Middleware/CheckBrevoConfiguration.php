<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\BrevoEmailService;
use Symfony\Component\HttpFoundation\Response;

class CheckBrevoConfiguration
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar se a chave API do Brevo está configurada
        $brevoApiKey = config('services.brevo.api_key');
        
        if (empty($brevoApiKey)) {
            Log::warning('Chave API do Brevo não configurada', [
                'route' => $request->route()->getName(),
                'url' => $request->url()
            ]);
            
            // Em ambiente de desenvolvimento, mostrar aviso
            if (config('app.debug')) {
                return response()->json([
                    'error' => 'Configuração do Brevo incompleta',
                    'message' => 'A chave API do Brevo não está configurada. Verifique a variável BREVO_API_KEY no arquivo .env',
                    'instructions' => [
                        '1. Obtenha sua chave API em: https://app.brevo.com/settings/keys/api',
                        '2. Adicione BREVO_API_KEY=sua_chave_aqui no arquivo .env',
                        '3. Execute: php artisan config:clear',
                        '4. Teste com: php artisan brevo:test-email seu@email.com --check-api'
                    ]
                ], 500);
            }
        }
        
        return $next($request);
    }
    
    /**
     * Verificar se a API do Brevo está acessível
     *
     * @return bool
     */
    public static function isBrevoApiAccessible(): bool
    {
        try {
            $brevoService = new BrevoEmailService();
            $status = $brevoService->checkApiStatus();
            return $status['success'];
        } catch (\Exception $e) {
            Log::error('Erro ao verificar API Brevo', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Obter informações de status da configuração Brevo
     *
     * @return array
     */
    public static function getBrevoConfigurationStatus(): array
    {
        $apiKey = config('services.brevo.api_key');
        $apiUrl = config('services.brevo.api_url');
        
        $status = [
            'api_key_configured' => !empty($apiKey),
            'api_url_configured' => !empty($apiUrl),
            'api_accessible' => false,
            'configuration_complete' => false
        ];
        
        if ($status['api_key_configured']) {
            $status['api_accessible'] = self::isBrevoApiAccessible();
        }
        
        $status['configuration_complete'] = $status['api_key_configured'] && 
                                          $status['api_url_configured'] && 
                                          $status['api_accessible'];
        
        return $status;
    }
}