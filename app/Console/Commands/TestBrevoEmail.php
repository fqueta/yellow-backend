<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BrevoEmailService;
use Illuminate\Support\Facades\Log;
use Exception;

class TestBrevoEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'brevo:test-email {email} {--name=} {--check-api}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testar envio de email via API Brevo';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $name = $this->option('name') ?? 'Usuário Teste';
        $checkApi = $this->option('check-api');

        $brevoService = new BrevoEmailService();

        // Verificar status da API se solicitado
        if ($checkApi) {
            $this->info('Verificando status da API Brevo...');
            $apiStatus = $brevoService->checkApiStatus();
            
            if ($apiStatus['success']) {
                $this->info('✅ API Brevo está funcionando corretamente!');
                $this->line('Dados da conta: ' . json_encode($apiStatus['data'], JSON_PRETTY_PRINT));
            } else {
                $this->error('❌ Erro na API Brevo: ' . ($apiStatus['error'] ?? 'Erro desconhecido'));
                return 1;
            }
        }

        // Testar envio de email
        $this->info("Enviando email de teste para: {$email}");
        
        try {
            $to = [[
                'email' => $email,
                'name' => $name
            ]];

            $subject = 'Teste de Integração - API Brevo';
            $htmlContent = $this->buildTestEmailHtml($name);

            $result = $brevoService->sendTransactionalEmail(
                $to,
                $subject,
                $htmlContent
            );

            if ($result['success']) {
                $this->info('✅ Email enviado com sucesso!');
                $this->line('Message ID: ' . ($result['message_id'] ?? 'N/A'));
                $this->line('Dados da resposta: ' . json_encode($result['data'], JSON_PRETTY_PRINT));
            } else {
                $this->error('❌ Falha ao enviar email');
                return 1;
            }

        } catch (Exception $e) {
            $this->error('❌ Erro ao enviar email: ' . $e->getMessage());
            Log::error('Erro no comando de teste Brevo', [
                'error' => $e->getMessage(),
                'email' => $email
            ]);
            return 1;
        }

        $this->info('🎉 Teste concluído com sucesso!');
        return 0;
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
                        <p>Este email confirma que a integração com a API do Brevo está funcionando corretamente.</p>
                    </div>
                    
                    <h3>Detalhes do Teste</h3>
                    <ul>
                        <li><strong>Data/Hora:</strong> " . now()->format('d/m/Y H:i:s') . "</li>
                        <li><strong>Serviço:</strong> Brevo API v3</li>
                        <li><strong>Tipo:</strong> Email Transacional</li>
                        <li><strong>Status:</strong> Enviado com Sucesso</li>
                    </ul>
                    
                    <p>A partir de agora, todas as notificações do sistema serão enviadas através da API do Brevo, contornando os problemas de SSL/SMTP.</p>
                    
                    <p><strong>Benefícios da integração:</strong></p>
                    <ul>
                        <li>✅ Maior confiabilidade de entrega</li>
                        <li>✅ Sem problemas de certificados SSL</li>
                        <li>✅ Melhor rastreamento de emails</li>
                        <li>✅ APIs modernas e estáveis</li>
                    </ul>
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