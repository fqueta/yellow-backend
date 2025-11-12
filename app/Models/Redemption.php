<?php

namespace App\Models;

use App\Services\Qlib;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * Model para gerenciar resgates de pontos
 * Sistema de resgate de produtos por pontos
 */
class Redemption extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nome da tabela
     */
    protected $table = 'redemptions';

    /**
     * Campos que podem ser preenchidos em massa
     */
    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'pontos', // Campo legacy para compatibilidade
        'points_used',
        'unit_points',
        'status',
        'delivery_address',
        'estimated_delivery_date',
        'actual_delivery_date',
        'config',
        'notes',
        'admin_notes',
        'product_snapshot',
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
        'points_used' => 'decimal:2',
        'unit_points' => 'decimal:2',
        'delivery_address' => 'array',
        'product_snapshot' => 'array',
        'estimated_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'config' => 'array',
    ];

    /**
     * Valores padrão para os atributos
     */
    protected $attributes = [
        'status' => 'pending',
        'quantity' => 1,
        'ativo' => 's',
        'excluido' => 'n',
        'deletado' => 'n',
    ];

    /**
     * Boot do modelo
     */
    protected static function boot()
    {
        parent::boot();

        // Definir autor automaticamente ao criar um resgate deve ser o primiero usuario com id de permissão 5
        static::creating(function ($model) {
            if (Auth::check() && !$model->autor) {
                $permissionId = Qlib::qoption('permission_partner_id') ?? 5;
                $model->autor = User::where('permission_id', $permissionId)->orderBy('created_at', 'asc')->first()->id;
            }
        });
    }

    /**
     * Relacionamento com o produto
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'product_id', 'ID');
    }

    /**
     * Relacionamento com o usuário
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Relacionamento com o histórico de status
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(RedemptionStatusHistory::class)->latest();
    }

    /**
     * Scope para registros ativos
     */
    public function scopeAtivos($query)
    {
        return $query->where('ativo', 's')
                    ->where('excluido', 'n')
                    ->where('deletado', 'n');
    }

    /**
     * Scope para resgates pendentes
     */
    public function scopePendentes($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope para resgates confirmados
     */
    public function scopeConfirmados($query)
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Scope para resgates entregues
     */
    public function scopeEntregues($query)
    {
        return $query->where('status', 'delivered');
    }

    /**
     * Scope para resgates cancelados
     */
    public function scopeCancelados($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope para resgates de um usuário específico
     */
    public function scopeDoUsuario($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para resgates de um produto específico
     */
    public function scopeDoProduto($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Calcular o total de pontos utilizados
     */
    public function getTotalPointsAttribute()
    {
        return $this->points_used;
    }

    /**
     * Verificar se o resgate pode ser cancelado
     */
    public function canBeCancelled()
    {
        return in_array($this->status, ['pending', 'approved']);
    }

    /**
     * Verificar se o resgate está em processamento
     */
    public function isProcessing()
    {
        return in_array($this->status, ['processing', 'shipped']);
    }

    /**
     * Verificar se o resgate foi finalizado
     */
    public function isCompleted()
    {
        return in_array($this->status, ['delivered', 'cancelled']);
    }

    /**
     * Obter o status formatado para exibição
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'Pendente',
            'processing' => 'Processando',
            'confirmed' => 'Confirmado',
            'shipped' => 'Enviado',
            'delivered' => 'Entregue',
            'cancelled' => 'Cancelado',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Criar snapshot do produto no momento do resgate
     */
    public function createProductSnapshot()
    {
        if ($this->product) {
            $this->product_snapshot = [
                'name' => $this->product->post_title,
                'description' => $this->product->post_content,
                'points' => $this->product->config['points'] ?? null,
                'stock' => $this->product->config['stock'] ?? null,
                'image' => $this->product->config['image'] ?? null,
                'terms' => $this->product->config['terms'] ?? null,
                'delivery_time' => $this->product->config['delivery_time'] ?? null,
                'snapshot_date' => now()->toDateTimeString(),
            ];
            $this->save();
        }
    }
}
