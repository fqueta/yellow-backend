<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class SystemLog extends Model
{
    use HasFactory;

    // Se estiver usando o pacote tenancy for laravel, descomente a linha abaixo 
    // se desejar que essa tabela seja global mas escopada opcionalmente. 
    // Como a migration tem tenant_id, podemos gerenciar manualmente ou usar a trait.
    // use BelongsToTenant;

    protected $fillable = [
        'event_type',
        'status',
        'description',
        'metadata',
        'ativo',
        'excluido',
        'deletado'
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
