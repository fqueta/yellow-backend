<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BrevoEmailService;
use App\Http\Middleware\CheckBrevoConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class BrevoController extends Controller
{
    protected $brevoService;

    /**
     * Construtor do controller
     */
    public function __construct()
    {
        $this->brevoService = new BrevoEmailService();
    }

    /**
     * Verificar status da configuração Brevo
     *
     * @return JsonResponse
     */
    public function checkConfiguration(): JsonResponse
    {
        try {
            $configStatus = CheckBrevoConfiguration::getBrevoConfigurationStatus();
            
            return response()->json([
                'success' => true,
                'data' => $configStatus,
                'message' => $configStatus['configuration_complete'] 
                    ? 'Configuração Brevo está completa e funcionando'
                    : 'Configuração Brevo incompleta ou com problemas'
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao verificar configuração Brevo', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erro ao verificar configuração',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar status da API Brevo
     *
     * @return JsonResponse
     */
    public function checkApiStatus(): JsonResponse
    {
        try {
            $apiStatus = $this->brevoService->checkApiStatus();
            
            return response()->json([
                'success' => $apiStatus['success'],
                'data' => $apiStatus,
                'message' => $apiStatus['success'] 
                    ? 'API Brevo está acessível e funcionando'
                    : 'Problema ao acessar API Brevo'
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao verificar API Brevo', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erro ao verificar API',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar email de teste
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendTestEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'name' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $email = $request->input('email');
            $name = $request->input('name', 'Usuário Teste');
            $subject = $request->input('subject', 'Teste de Integração - API Brevo');

            $to = [[
                'email' => $email,
                'name' => $name
            ]];

            $htmlContent = $this->buildTestEmailHtml($name);

            $result = $this->brevoService->sendTransactionalEmail(
                $to,
                $subject,
                $htmlContent
            );

            if ($result['success']) {
                Log::info('Email de teste enviado via API', [
                    'email' => $email,
                    'message_id' => $result['message_id']
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Email de teste enviado com sucesso',
                    'data' => [
                        'message_id' => $result['message_id'],
                        'email' => $email,
                        'sent_at' => now()->toISOString()
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Falha ao enviar email de teste'
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Erro ao enviar email de teste via API', [
                'error' => $e->getMessage(),
                'email' => $request->input('email')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao enviar email de teste',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simular envio de notificação de resgate
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function simulateRedemptionNotification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_email' => 'required|email',
            'user_name' => 'nullable|string|max:255',
            'product_name' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'points_used' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Simular objetos de usuário, produto e resgate
            $mockUser = (object) [
                'id' => 999,
                'email' => $request->input('user_email'),
                'name' => $request->input('user_name', 'Usuário Teste')
            ];

            $mockProduct = (object) [
                'ID' => 888,
                'post_title' => $request->input('product_name'),
                'name' => $request->input('product_name')
            ];

            $mockRedemption = (object) [
                'id' => 777,
                'status' => 'pending'
            ];

            $quantity = $request->input('quantity');
            $pointsUsed = $request->input('points_used');

            // Enviar notificação para o usuário
            $userResult = $this->brevoService->sendRedemptionSuccessNotification(
                $mockUser,
                $mockProduct,
                $mockRedemption,
                $quantity,
                $pointsUsed
            );

            $response = [
                'success' => true,
                'message' => 'Simulação de notificação de resgate executada',
                'data' => [
                    'user_notification' => [
                        'sent' => $userResult['success'],
                        'message_id' => $userResult['message_id'] ?? null,
                        'email' => $mockUser->email
                    ],
                    'simulation_details' => [
                        'user' => $mockUser->name,
                        'product' => $mockProduct->post_title,
                        'quantity' => $quantity,
                        'points_used' => $pointsUsed,
                        'timestamp' => now()->toISOString()
                    ]
                ]
            ];

            Log::info('Simulação de notificação de resgate executada', $response['data']);

            return response()->json($response);

        } catch (Exception $e) {
            Log::error('Erro na simulação de notificação de resgate', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro na simulação de notificação',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Construir HTML para email de teste
     *
     * @param string $name
     * @return string
     */
    private function buildTestEmailHtml(string $name): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Teste de Integração Brevo</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .footer { padding: 20px; text-align: center; color: #666; }
                .success { background: #d4edda; padding: 10px; border-left: 4px solid #28a745; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🚀 Teste de Integração Brevo</h1>
                </div>
                <div class='content'>
                    <p>Olá <strong>{$name}</strong>,</p>
                    
                    <div class='success'>
                        <h3>✅ Integração Funcionando!</h3>
                        <p>Este email confirma que a integração com a API do Brevo está funcionando corretamente via interface web.</p>
                    </div>
                    
                    <h3>Detalhes do Teste</h3>
                    <ul>
                        <li><strong>Data/Hora:</strong> " . now()->format('d/m/Y H:i:s') . "</li>
                        <li><strong>Serviço:</strong> Brevo API v3</li>
                        <li><strong>Origem:</strong> Controller API</li>
                        <li><strong>Status:</strong> Enviado com Sucesso</li>
                    </ul>
                    
                    <p>A integração está funcionando perfeitamente! 🎉</p>
                </div>
                <div class='footer'>
                    <p>Este é um email de teste automático.</p>
                    <p>Sistema Yellow - Integração Brevo</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}