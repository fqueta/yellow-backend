<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class BrevoEmailService
{
    protected $apiKey;
    protected $baseUrl;
    protected $defaultSender;

    /**
     * Construtor do serviço Brevo
     */
    public function __construct()
    {
        $this->apiKey = config('services.brevo.api_key');
        $this->baseUrl = 'https://api.brevo.com/v3';
        $this->defaultSender = [
            'name' => config('mail.from.name', 'Sistema'),
            'email' => config('mail.from.address')
        ];
    }

    /**
     * Enviar email transacional via API Brevo
     *
     * @param array $to Array de destinatários [['email' => 'email@example.com', 'name' => 'Nome']]
     * @param string $subject Assunto do email
     * @param string $htmlContent Conteúdo HTML do email
     * @param string|null $textContent Conteúdo texto do email (opcional)
     * @param array|null $sender Remetente personalizado (opcional)
     * @param array $templateParams Parâmetros para template (opcional)
     * @return array
     * @throws Exception
     */
    public function sendTransactionalEmail(
        array $to,
        string $subject,
        string $htmlContent,
        ?string $textContent = null,
        ?array $sender = null,
        array $templateParams = []
    ): array {
        try {
            $payload = [
                'sender' => $sender ?? $this->defaultSender,
                'to' => $to,
                'subject' => $subject,
                'htmlContent' => $htmlContent
            ];

            if ($textContent) {
                $payload['textContent'] = $textContent;
            }

            if (!empty($templateParams)) {
                $payload['params'] = $templateParams;
            }

            Log::info('Enviando email via Brevo API', [
                'to' => $to,
                'subject' => $subject
            ]);

            $response = Http::withHeaders([
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($this->baseUrl . '/smtp/email', $payload);

            if ($response->successful()) {
                $result = $response->json();
                Log::info('Email enviado com sucesso via Brevo', [
                    'message_id' => $result['messageId'] ?? null,
                    'to' => $to
                ]);
                return [
                    'success' => true,
                    'message_id' => $result['messageId'] ?? null,
                    'data' => $result
                ];
            } else {
                $error = $response->json();
                Log::error('Erro ao enviar email via Brevo', [
                    'status' => $response->status(),
                    'error' => $error,
                    'to' => $to
                ]);
                throw new Exception('Erro na API Brevo: ' . ($error['message'] ?? 'Erro desconhecido'));
            }
        } catch (Exception $e) {
            Log::error('Exceção ao enviar email via Brevo', [
                'error' => $e->getMessage(),
                'to' => $to
            ]);
            throw $e;
        }
    }

    /**
     * Enviar notificação de resgate bem-sucedido
     *
     * @param object $user
     * @param object $product
     * @param object $redemption
     * @param int $quantity
     * @param int $pointsUsed
     * @return array
     */
    public function sendRedemptionSuccessNotification(
        $user,
        $product,
        $redemption,
        int $quantity,
        int $pointsUsed
    ): array {
        $to = [[
            'email' => $user->email,
            'name' => $user->name ?? $user->email
        ]];

        $subject = 'Resgate de Pontos Realizado com Sucesso!';

        $htmlContent = $this->buildRedemptionSuccessHtml(
            $user,
            $product,
            $redemption,
            $quantity,
            $pointsUsed
        );

        return $this->sendTransactionalEmail($to, $subject, $htmlContent);
    }

    /**
     * Enviar notificação para administradores sobre resgate
     *
     * @param object $user
     * @param object $product
     * @param object $redemption
     * @param int $quantity
     * @param int $pointsUsed
     * @param array $admins
     * @return array
     */
    public function sendAdminRedemptionNotification(
        $user,
        $product,
        $redemption,
        int $quantity,
        int $pointsUsed,
        $admins
    ): array {
        $to = [];
        foreach ($admins as $admin) {
            $to[] = [
                'email' => $admin->email,
                'name' => $admin->name ?? $admin->email
            ];
        }

        $subject = 'Novo Resgate de Pontos Realizado - Ação Necessária';

        $htmlContent = $this->buildAdminRedemptionHtml(
            $user,
            $product,
            $redemption,
            $quantity,
            $pointsUsed
        );

        return $this->sendTransactionalEmail($to, $subject, $htmlContent);
    }

    /**
     * Construir HTML para notificação de resgate bem-sucedido
     */
    private function buildRedemptionSuccessHtml(
        $user,
        $product,
        $redemption,
        int $quantity,
        int $pointsUsed
    ): string {
        $productName = $product->post_title ?? $product->name ?? 'Produto';
        $userName = $user->name ?? $user->email;

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Resgate Realizado com Sucesso</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 20px; text-align: center; color: #666; }
                .highlight { background: #e8f5e8; padding: 10px; border-left: 4px solid #4CAF50; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Resgate Realizado com Sucesso!</h1>
                </div>
                <div class='content'>
                    <p>Olá <strong>{$userName}</strong>,</p>
                    <p>Seu resgate foi processado com sucesso! Aqui estão os detalhes:</p>

                    <div class='highlight'>
                        <h3>Detalhes do Resgate</h3>
                        <ul>
                            <li><strong>Produto:</strong> {$productName}</li>
                            <li><strong>Quantidade:</strong> {$quantity}</li>
                            <li><strong>Pontos Utilizados:</strong> {$pointsUsed}</li>
                            <li><strong>CPF:</strong> {$user->cpf}</li>
                            <li><strong>ID do Resgate:</strong> #{$redemption->id}</li>
                            <li><strong>Data:</strong> " . now()->format('d/m/Y H:i') . "</li>
                        </ul>
                        " . (!empty($redemption->config) ? $this->formatRedemptionConfig($redemption->config, 'client') : "") . "
                    </div>

                    <p>Em breve você receberá mais informações sobre a entrega do seu produto.</p>
                    <p>Obrigado por usar nosso sistema de pontos!</p>
                </div>
                <div class='footer'>
                    <p>Este é um email automático, não responda.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Construir HTML para notificação de administradores
     */
    private function buildAdminRedemptionHtml(
        $user,
        $product,
        $redemption,
        int $quantity,
        int $pointsUsed
    ): string {
        $productName = $product->post_title ?? $product->name ?? 'Produto';
        $userName = $user->name ?? $user->email;

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Novo Resgate - Ação Necessária</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #FF9800; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 20px; text-align: center; color: #666; }
                .alert { background: #fff3cd; padding: 10px; border-left: 4px solid #FF9800; }
                .user-info { background: #e3f2fd; padding: 10px; border-left: 4px solid #2196F3; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔔 Novo Resgate Realizado</h1>
                </div>
                <div class='content'>
                    <div class='alert'>
                        <h3>⚠️ Ação Necessária</h3>
                        <p>Um novo resgate foi realizado e requer sua atenção.</p>
                    </div>

                    <div class='user-info'>
                        <h3>Informações do Cliente</h3>
                        <ul>
                            <li><strong>Nome:</strong> {$userName}</li>
                            <li><strong>Email:</strong> {$user->email}</li>
                            <li><strong>CPF:</strong> {$user->cpf}</li>
                            <li><strong>ID do Cliente:</strong> #{$user->id}</li>
                        </ul>
                    </div>

                    <h3>Detalhes do Resgate</h3>
                    <ul>
                        <li><strong>Produto:</strong> {$productName}</li>
                        <li><strong>Quantidade:</strong> {$quantity}</li>
                        <li><strong>Pontos Utilizados:</strong> {$pointsUsed}</li>
                        <li><strong>ID do Resgate:</strong> #{$redemption->id}</li>
                        <li><strong>Status:</strong> {$redemption->status}</li>
                        <li><strong>Data:</strong> " . now()->format('d/m/Y H:i') . "</li>
                    </ul>
                    " . (!empty($redemption->config) ? $this->formatRedemptionConfig($redemption->config, 'admin') : "") . "

                    <p><strong>Próximos passos:</strong></p>
                    <ol>
                        <li>Verificar disponibilidade do produto</li>
                        <li>Processar a entrega</li>
                        <li>Atualizar o status do resgate</li>
                        <li>Notificar o cliente sobre o andamento</li>
                    </ol>
                </div>
                <div class='footer'>
                    <p>Este é um email automático, não responda.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Formatar informações de configuração do resgate
     *
     * @param string|array $config JSON string ou array com configurações
     * @param string $type Tipo de formatação ('client' ou 'admin')
     * @return string HTML formatado
     */
    private function formatRedemptionConfig($config, string $type = 'client'): string
    {
        try {
            // Se já é um array (devido ao cast do modelo), usar diretamente
            // Se é string, decodificar o JSON
            $data = is_array($config) ? $config : json_decode($config, true);

            if (!$data || !is_array($data)) {
                return "";
            }

            $html = "";
            $containerStyle = $type === 'admin'
                ? "style='background: #f0f8ff; padding: 10px; border-left: 4px solid #007bff; margin: 10px 0;'"
                : "";

            // Verificar se são dados de endereço
            if (isset($data['cep']) || isset($data['logradouro'])) {
                $html = "<div {$containerStyle}>";
                $html .= "<h4>📍 Informações de Entrega:</h4>";
                $html .= "<ul style='margin: 5px 0; padding-left: 20px;'>";

                if (isset($data['logradouro'])) {
                    $endereco = $data['logradouro'];
                    if (isset($data['numero']) && $data['numero'] !== 'sem numero') {
                        $endereco .= ", " . $data['numero'];
                    }
                    if($data['numero']=='sem numero'){
                        $endereco .= ", S/N";
                    }
                    if (isset($data['complemento']) && !empty($data['complemento'])) {
                        $endereco .= " - {$data['complemento']}";
                    }
                    if (isset($data['ponto_referencia']) && !empty($data['ponto_referencia'])) {
                        $endereco .= " - {$data['ponto_referencia']}";
                    }
                    $html .= "<li><strong>Endereço:</strong> {$endereco}</li>";
                }

                if (isset($data['bairro'])) {
                    $html .= "<li><strong>Bairro:</strong> {$data['bairro']}</li>";
                }

                if (isset($data['cidade']) && isset($data['uf'])) {
                    $html .= "<li><strong>Cidade:</strong> {$data['cidade']} - {$data['uf']}</li>";
                }

                if (isset($data['cep'])) {
                    $html .= "<li><strong>CEP:</strong> {$data['cep']}</li>";
                }

                // if (isset($data['ponto_referencia']) && !empty($data['ponto_referencia'])) {
                //     $html .= "<li><strong>Ponto de Referência:</strong> {$data['ponto_referencia']}</li>";
                // }

                $html .= "</ul></div>";
            }
            // Verificar se são dados de PIX
            elseif (isset($data['chave_pix']) || isset($data['confira_pix'])) {
                $html = "<div {$containerStyle}>";
                $html .= "<h4>💳 Informações de PIX:</h4>";
                $html .= "<ul style='margin: 5px 0; padding-left: 20px;'>";

                if (isset($data['chave_pix'])) {
                    $html .= "<li><strong>Chave PIX:</strong> {$data['chave_pix']}</li>";
                }

                if (isset($data['confira_pix'])) {
                    $html .= "<li><strong>Confirmação PIX:</strong> {$data['confira_pix']}</li>";
                }

                $html .= "</ul></div>";
            }
            // Outros tipos de dados
            else {
                $html = "<div {$containerStyle}>";
                $html .= "<h4>ℹ️ Informações Adicionais:</h4>";
                $html .= "<ul style='margin: 5px 0; padding-left: 20px;'>";

                foreach ($data as $key => $value) {
                    if (!empty($value)) {
                        $label = ucfirst(str_replace('_', ' ', $key));
                        $html .= "<li><strong>{$label}:</strong> {$value}</li>";
                    }
                }

                $html .= "</ul></div>";
            }

            return $html;

        } catch (Exception $e) {
            // Em caso de erro, retornar o JSON original
            $containerStyle = $type === 'admin'
                ? "style='background: #f0f8ff; padding: 10px; border-left: 4px solid #007bff; margin: 10px 0;'"
                : "";
            return "<div {$containerStyle}><h4>Informações Adicionais:</h4><p>" . htmlspecialchars($config) . "</p></div>";
        }
    }

    /**
     * Verificar status da API Brevo
     *
     * @return array
     */
    public function checkApiStatus(): array
    {
        try {
            $response = Http::withHeaders([
                'api-key' => $this->apiKey,
                'Accept' => 'application/json'
            ])->get($this->baseUrl . '/account');

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->json()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
