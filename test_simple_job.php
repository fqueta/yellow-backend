<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// Job simples para teste
class TestSimpleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    public function handle()
    {
        Log::info('Job simples executado com sucesso: ' . $this->message);
        echo "Job executado: {$this->message}\n";
    }
}

try {
    echo "Testando job simples...\n";
    
    // Despachar job simples
    $job = new TestSimpleJob('Teste de fila funcionando!');
    dispatch($job);
    
    echo "Job simples despachado com sucesso!\n";
    echo "Verifique o worker de fila e os logs.\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}