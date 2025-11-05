<?php

namespace App\Notifications\Channels;

use App\Services\BrevoEmailService;

/**
 * Canal de notificação personalizado para envio de e-mails via Brevo.
 *
 * Este canal lê os dados da notificação através do método toBrevo()
 * e utiliza o BrevoEmailService para enviar o e-mail transacional.
 */
class BrevoChannel
{
    /** @var BrevoEmailService */
    protected BrevoEmailService $service;

    /**
     * Construtor do canal Brevo.
     */
    public function __construct()
    {
        $this->service = new BrevoEmailService();
    }

    /**
     * Envia a notificação via Brevo.
     *
     * @param object $notifiable O destinatário da notificação
     * @param object $notification A instância da notificação
     * @return void
     */
    public function send($notifiable, $notification): void
    {
        if (!method_exists($notification, 'toBrevo')) {
            return;
        }

        $payload = $notification->toBrevo($notifiable);

        $to = $payload['to'] ?? [[
            'email' => $notifiable->email,
            'name' => $notifiable->name ?? $notifiable->email,
        ]];

        $subject = $payload['subject'] ?? 'Notificação';
        $html = $payload['htmlContent'] ?? '';
        $text = $payload['textContent'] ?? null;
        $sender = $payload['sender'] ?? null;
        $params = $payload['params'] ?? [];

        // Enviar email via Brevo
        $this->service->sendTransactionalEmail($to, $subject, $html, $text, $sender, $params);
    }
}