<?php

namespace App\Models;

use App\Services\Qlib;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * Model para gerenciar pontos dos clientes
 * Sistema de pontuação/recompensas
 */
class Point extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nome da tabela
     */
    protected $table = 'points';

    /**
     * Campos que podem ser preenchidos em massa
     */
    protected $fillable = [
        'client_id',
        'valor',
        'data',
        'description',
        'tipo',
        'origem',
        'valor_referencia',
        'data_expiracao',
        'status',
        'usuario_id',
        'pedido_id',
        'config',
        'autor',
        'ativo',
        'excluido',
        'deletado',
    ];

    /**
     * Campos que devem ser ocultados na serialização
     */
    protected $hidden = [
        'autor',
        'excluido',
        'deletado',
    ];

    /**
     * Casting de tipos
     */
    protected $casts = [
        'valor' => 'decimal:2',
        'valor_referencia' => 'decimal:2',
        'data' => 'date',
        'data_expiracao' => 'date',
        'config' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Valores padrão para os atributos
     */
    protected $attributes = [
        'tipo' => 'credito',
        'status' => 'ativo',
        'ativo' => 's',
        'excluido' => 'n',
        'deletado' => 'n',
    ];

    /**
     * Relacionamento com Cliente
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id', 'id');
    }

    /**
     * Relacionamento com Usuário que registrou
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'id');
    }

    /**
     * Scope para pontos ativos
     */
    public function scopeAtivos($query)
    {
        return $query->where('status', 'ativo')
                    ->where('ativo', 's')
                    ->where('excluido', 'n')
                    ->where('deletado', 'n');
    }

    /**
     * Scope para pontos de crédito
     */
    public function scopeCreditos($query)
    {
        return $query->where('tipo', 'credito');
    }

    /**
     * Scope para pontos de débito
     */
    public function scopeDebitos($query)
    {
        return $query->where('tipo', 'debito');
    }

    /**
     * Scope para pontos expirados
     */
    public function scopeExpirados($query)
    {
        return $query->where('data_expiracao', '<', now())
                    ->where('status', '!=', 'expirado');
    }

    /**
     * Scope para pontos que vão expirar em X dias
     */
    public function scopeVencendoEm($query, $dias = 30)
    {
        return $query->where('data_expiracao', '<=', now()->addDays($dias))
                    ->where('data_expiracao', '>', now())
                    ->where('status', 'ativo');
    }

    /**
     * Scope para filtrar por cliente
     */
    public function scopePorCliente($query, $clienteId)
    {
        return $query->where('client_id', $clienteId);
    }

    /**
     * Scope para filtrar por período
     */
    public function scopePorPeriodo($query, $dataInicio, $dataFim)
    {
        return $query->whereBetween('data', [$dataInicio, $dataFim]);
    }

    /**
     * Accessor para verificar se os pontos estão expirados
     */
    public function getExpiradoAttribute(): bool
    {
        if (!$this->data_expiracao) {
            return false;
        }
        return $this->data_expiracao->isPast() && $this->status !== 'expirado';
    }

    /**
     * Accessor para verificar se os pontos estão próximos do vencimento
     */
    public function getProximoVencimentoAttribute(): bool
    {
        if (!$this->data_expiracao) {
            return false;
        }
        return $this->data_expiracao->diffInDays(now()) <= 30 && $this->data_expiracao->isFuture();
    }

    /**
     * Accessor para valor formatado
     */
    public function getValorFormatadoAttribute(): string
    {
        $valor = $this->valor;
        $sinal = $valor >= 0 ? '+' : '';
        return $sinal . number_format($valor, 0, ',', '.') . ' pts';
    }

    /**
     * Accessor para valor de referência formatado
     */
    public function getValorReferenciaFormatadoAttribute(): string
    {
        if (!$this->valor_referencia) {
            return '';
        }
        return 'R$ ' . number_format($this->valor_referencia, 2, ',', '.');
    }

    /**
     * Mutator para converter valor para negativo quando for débito
     */
    public function setValorAttribute($value)
    {
        $valor = floatval($value);

        // Se o tipo for débito, garantir que o valor seja negativo
        if (isset($this->attributes['tipo']) && $this->attributes['tipo'] === 'debito') {
            $this->attributes['valor'] = -abs($valor);
        } else {
            $this->attributes['valor'] = $valor;
        }
    }

    /**
     * Mutator para garantir que valor_referencia seja positivo
     */
    public function setValorReferenciaAttribute($value)
    {
        if ($value !== null) {
            $this->attributes['valor_referencia'] = abs(floatval($value));
        }
    }

    /**
     * Mutator para formatar a data
     */
    public function setDataAttribute($value)
    {
        if ($value) {
            $this->attributes['data'] = Carbon::parse($value)->format('Y-m-d');
        }
    }

    /**
     * Mutator para formatar a data de expiração
     */
    public function setDataExpiracaoAttribute($value)
    {
        if ($value) {
            $this->attributes['data_expiracao'] = Carbon::parse($value)->format('Y-m-d');
        }
    }

    /**
     * Boot do model para eventos
     */
    protected static function boot()
    {
        parent::boot();

        // Ao criar, definir autor se não informado como o primeiro usuário com permissão de parceiro
        static::creating(function ($point) {
            if (!$point->autor && Auth::check()) {
                $permissionId = Qlib::qoption('permission_partner_id') ?? 5;
                $point->autor = User::where('permission_id', $permissionId)->orderBy('created_at', 'asc')->first()->id;
            }
            if (!$point->usuario_id && Auth::check()) {
                $point->usuario_id = Auth::id();
            }
        });

        // Verificar expiração ao recuperar
        static::retrieved(function ($point) {
            if ($point->expirado && $point->status === 'ativo') {
                $point->update(['status' => 'expirado']);
            }
        });
    }

    /**
     * Método estático para calcular saldo de pontos de um cliente
     */
    public static function saldoCliente($clienteId): float
    {
        $creditos = self::where('client_id', $clienteId)
                       ->where('tipo', 'credito')
                       ->ativos()
                       ->sum('valor');

        $debitos = self::where('client_id', $clienteId)
                      ->where('tipo', 'debito')
                      ->ativos()
                      ->sum('valor');

        return $creditos - $debitos;
    }

    /**
     * Método estático para pontos que expiram em breve
     */
    public static function pontosVencendoCliente($clienteId, $dias = 30): float
    {
        return self::where('client_id', $clienteId)
                  ->vencendoEm($dias)
                  ->where('tipo', 'credito')
                  ->sum('valor');
    }
}
