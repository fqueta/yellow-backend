<?php

namespace App\Models;

use App\Services\Qlib;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
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
        'valor_usado',
        'usuario_id',
        'pedido_id',
        'config',
        'status',
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
     * Atributos que devem ser anexados ao JSON
     */
    protected $appends = [
        'saldo_restante',
        'is_totalmente_usado',
        'valor_resgatado',
        'valor_expirado',
        'proximo_vencimento',
        'expirado',
        'valor_formatado',
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
     * Accessor para saldo restante do crédito
     */
    public function getSaldoRestanteAttribute(): float
    {
        if ($this->tipo !== 'credito') return 0;
        return max(0, (float) $this->valor - (float) $this->valor_usado);
    }

    /**
     * Retorna o valor que foi efetivamente resgatado (gasto) pelo cliente.
     */
    public function getValorResgatadoAttribute()
    {
        if ($this->tipo !== 'credito') return 0;
        
        $totalUsado = (float) ($this->attributes['valor_usado'] ?? 0);
        $totalExpirado = (float) $this->valor_expirado;
        
        // O valor resgatado é o total de uso menos o que foi pro ralo na expiração
        return (float)max(0, $totalUsado - $totalExpirado);
    }

    /**
     * Retorna o valor que foi expirado pelo sistema para este crédito.
     */
    public function getValorExpiradoAttribute()
    {
        if ($this->tipo !== 'credito') return 0;
        
        // Busca débitos com origem expiração que citam este ID de crédito
        return (float)self::where('client_id', $this->client_id)
            ->whereIn('tipo', ['expired', 'debito', 'expiracao'])
            ->where('origem', 'expiracao')
            ->where('description', 'like', "%#{$this->id}%")
            ->sum(DB::raw('ABS(valor)'));
    }

    /**
     * Verifica se o crédito já foi totalmente consumido
     */
    public function getIsTotalmenteUsadoAttribute(): bool
    {
        if ($this->tipo !== 'credito') return true;
        return $this->valor_usado >= $this->valor;
    }

    /**
     * Mutator para converter valor para negativo quando for débito
     */
    public function setValorAttribute($value)
    {
        $valor = floatval($value);

        // Se o tipo for débito ou expiração, garantir que o valor seja negativo
        if (isset($this->attributes['tipo']) && in_array($this->attributes['tipo'], ['debito', 'expired'])) {
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
                $point->autor = Auth::id();
            }
            if (!$point->usuario_id && Auth::check()) {
                $point->usuario_id = Auth::id();
            }

            // Auto-definir data_expiracao para créditos quando não informada
            if (!$point->data_expiracao && $point->tipo === 'credito') {
                $expiracaoAtiva = Qlib::qoption('pontos_expiracao_ativa') ?? 'n';
                if ($expiracaoAtiva === 's') {
                    $diasExpiracao = Qlib::qoption('pontos_dias_expiracao');
                    if ($diasExpiracao && (int) $diasExpiracao > 0) {
                        $dataBase = $point->data ? Carbon::parse($point->data) : Carbon::now();
                        $point->data_expiracao = $dataBase->addDays((int) $diasExpiracao)->format('Y-m-d');
                    }
                }
            }
        });

        // Ao criar um DÉBITO normal, consumir os créditos automaticamente (Lógica PEPS)
        // Ignorar débitos de expiração, pois a própria rotina de expiração já atualiza o valor_usado do crédito matriz.
        static::created(function ($point) {
            if ($point->tipo === 'debito' && $point->origem !== 'expiracao') {
                $consumedIds = self::consumePoints($point->client_id, abs($point->valor));
                
                // Se consumiu créditos, atualizar a descrição para incluir as referências
                if (!empty($consumedIds)) {
                    $idsStr = implode(', #', $consumedIds);
                    $newDescription = $point->description . " (Créditos: #$idsStr)";
                    
                    // Usar DB::table para evitar disparar eventos do Eloquent novamente (o que causaria loop infinito)
                    DB::table('points')->where('id', $point->id)->update([
                        'description' => $newDescription,
                        'config' => json_encode(array_merge(is_array($point->config) ? $point->config : [], [
                            'consumed_credit_ids' => $consumedIds
                        ]))
                    ]);
                }
            }
        });
    }

    /**
     * Método estático para calcular saldo de pontos de um cliente
     * Exclui pontos expirados em tempo real (por data_expiracao e status)
     */
    public static function saldoCliente($clienteId): float
    {
        // 1. Calcula a soma matemática efetiva validada do histórico de toda a vida do cliente
        $mathSum = (float) self::where('client_id', $clienteId)
            ->where('excluido', 'n')
            ->where('deletado', 'n')
            ->where('status', '!=', 'cancelado')
            ->sum(DB::raw("CASE WHEN tipo = 'credito' THEN valor ELSE -ABS(valor) END"));

        // 2. Desconta os créditos ativos dinamicamente vencidos caso a rotina Cron ainda não os tenha processado (expiracao < hoje)
        $dynamicExpiredUnprocessed = (float) self::where('client_id', $clienteId)
            ->where('tipo', 'credito')
            ->where('status', 'ativo')
            ->where('ativo', 's')
            ->where('excluido', 'n')
            ->where('deletado', 'n')
            ->whereNotNull('data_expiracao')
            ->where('data_expiracao', '<=', now()->toDateString())
            ->sum(DB::raw('valor - valor_usado'));

        // Saldo real é a vida menos as expirações dinâmicas ocorridas antes do cronJob.
        return $mathSum - $dynamicExpiredUnprocessed;
    }

    /**
     * Consome os pontos de crédito mais antigos do cliente.
     * Implementa a lógica PEPS (Primeiro que Entra, Primeiro que Sai).
     * 
     * @param int $clienteId
     * @param float $amount Valor total a ser debitado (será tratado como positivo)
     * @return array IDs dos créditos que foram consumidos
     */
    public static function consumePoints($clienteId, $amount): array
    {
        $remainingToConsume = abs((float) $amount);
        $consumedIds = [];
        if ($remainingToConsume <= 0) return [];

        // Buscar créditos ativos que ainda possuem saldo disponível
        // Ordenação: 1. Data de expiração mais próxima, 2. Data de lançamento, 3. ID (garante ordem determinística)
        $creditos = self::where('client_id', $clienteId)
            ->where('tipo', 'credito')
            ->ativos()
            ->where(function ($q) {
                // Apenas créditos que NÃO expiraram ainda
                $q->whereNull('data_expiracao')
                  ->orWhere('data_expiracao', '>', now());
            })
            ->whereRaw('valor > valor_usado')
            ->orderByRaw('data_expiracao IS NULL, data_expiracao ASC')
            ->orderBy('data', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($creditos as $credito) {
            /** @var Point $credito */
            if ($remainingToConsume <= 0.001) break; // Float safety

            $availableInThisCredit = (float) $credito->valor - (float) $credito->valor_usado;
            $consumption = min($availableInThisCredit, $remainingToConsume);

            $credito->valor_usado = (float) $credito->valor_usado + $consumption;
            $credito->save();

            $consumedIds[] = $credito->id;
            $remainingToConsume -= $consumption;
        }

        return $consumedIds;
    }

    /**
     * Versão especial do consumePoints para recálculo histórico.
     * NÃO filtra por data de expiração, pois precisamos reconstruir
     * o estado passado, quando os créditos ainda eram válidos.
     *
     * @param int $clienteId
     * @param float $amount Valor a ser debitado
     * @return void
     */
    public static function consumePointsForRecalculation($clienteId, $amount): void
    {
        $remainingToConsume = abs((float) $amount);
        if ($remainingToConsume <= 0) return;

        // Busca TODOS os créditos com saldo disponível, incluindo os já expirados
        // pois no passado eles podiam ser usados
        $creditos = self::where('client_id', $clienteId)
            ->where('tipo', 'credito')
            ->where('ativo', 's')
            ->where('excluido', 'n')
            ->where('deletado', 'n')
            ->where('status', '!=', 'cancelado')
            ->whereRaw('valor > valor_usado')
            ->orderByRaw('data_expiracao IS NULL, data_expiracao ASC')
            ->orderBy('data', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($creditos as $credito) {
            if ($remainingToConsume <= 0.001) break;

            $availableInThisCredit = (float) $credito->valor - (float) $credito->valor_usado;
            $consumption = min($availableInThisCredit, $remainingToConsume);

            // Usar DB::table para evitar disparar eventos do Eloquent (performance e segurança)
            \Illuminate\Support\Facades\DB::table('points')
                ->where('id', $credito->id)
                ->update(['valor_usado' => (float) $credito->valor_usado + $consumption]);

            $remainingToConsume -= $consumption;
        }
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
