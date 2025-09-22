<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Financial - representa monitorações financeiras no sistema
 * Gerencia contas a receber e contas a pagar
 */
class Financial extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * A tabela associada ao modelo
     */
    protected $table = 'financial';

    /**
     * Campos que podem ser preenchidos em massa
     */
    protected $fillable = [
        'titulo',
        'descricao',
        'tipo',
        'categoria',
        'valor',
        'valor_pago',
        'desconto',
        'juros',
        'multa',
        'data_vencimento',
        'data_pagamento',
        'data_competencia',
        'status',
        'parcela_atual',
        'total_parcelas',
        'cliente_id',
        'fornecedor_id',
        'usuario_id',
        'documento',
        'forma_pagamento',
        'conta_bancaria',
        'config',
        'autor',
        'token',
        'ativo',
        'excluido',
        'reg_excluido',
        'deletado',
        'reg_deletado',
    ];

    /**
     * Campos que devem ser convertidos para tipos nativos
     */
    protected $casts = [
        'config' => 'array',
        'valor' => 'decimal:2',
        'valor_pago' => 'decimal:2',
        'desconto' => 'decimal:2',
        'juros' => 'decimal:2',
        'multa' => 'decimal:2',
        'data_vencimento' => 'date',
        'data_pagamento' => 'date',
        'data_competencia' => 'date',
        'parcela_atual' => 'integer',
        'total_parcelas' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relacionamento com Cliente (contas a receber)
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'cliente_id', 'id');
    }

    /**
     * Relacionamento com Fornecedor/Partner (contas a pagar)
     */
    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'fornecedor_id', 'id');
    }

    /**
     * Relacionamento com Usuário responsável
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'id');
    }

    /**
     * Scope para filtrar contas a receber
     */
    public function scopeContasReceber($query)
    {
        return $query->where('tipo', 'receber');
    }

    /**
     * Scope para filtrar contas a pagar
     */
    public function scopeContasPagar($query)
    {
        return $query->where('tipo', 'pagar');
    }

    /**
     * Scope para filtrar por status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para contas vencidas
     */
    public function scopeVencidas($query)
    {
        return $query->where('data_vencimento', '<', now())
                    ->whereIn('status', ['pendente', 'parcial']);
    }

    /**
     * Scope para contas do mês atual
     */
    public function scopeMesAtual($query)
    {
        return $query->whereMonth('data_vencimento', now()->month)
                    ->whereYear('data_vencimento', now()->year);
    }

    /**
     * Accessor para calcular valor em aberto
     */
    public function getValorAbertoAttribute()
    {
        return $this->valor - $this->valor_pago;
    }

    /**
     * Accessor para verificar se está vencida
     */
    public function getVencidaAttribute()
    {
        return $this->data_vencimento < now() && in_array($this->status, ['pendente', 'parcial']);
    }

    /**
     * Mutator para garantir que o valor seja sempre positivo
     */
    public function setValorAttribute($value)
    {
        $this->attributes['valor'] = abs($value);
    }

    /**
     * Mutator para garantir que o valor pago não seja maior que o valor total
     */
    public function setValorPagoAttribute($value)
    {
        $this->attributes['valor_pago'] = min(abs($value), $this->valor ?? 0);
    }
}