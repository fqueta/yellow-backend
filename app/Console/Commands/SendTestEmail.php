<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmail extends Command
{
    protected $signature = 'email:send-test {email} {--subject=Teste}';
    protected $description = 'Enviar e-mail de teste';

    public function handle()
    {
        $email = $this->argument('email');
        $subject = $this->option('subject');

        Mail::raw('Este é um e-mail de teste enviado via Artisan.', function ($message) use ($email, $subject) {
            $message->to($email)->subject($subject);
        });

        $this->info("E-mail enviado para: {$email}");
    }
}
