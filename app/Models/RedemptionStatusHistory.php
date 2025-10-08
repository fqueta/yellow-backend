<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model para gerenciar o histórico de status dos resgates
 * Registra todas as mudanças de status com informações detalhadas
 */
class RedemptionStatusHistory extends Model
{
    use HasFactory;

    /**
     * Nome da tabela
     */
    protected $table = 'redemption_status_history';

    /**
     * Campos que podem ser preenchidos em massa
     */
    protected $fillable = [
        'redemption_id',
        'old_status',
        'new_status',
        'comment',
        'created_by',
        'created_by_name',
    ];

    /**
     * Casting de tipos
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relacionamento com o resgate
     */
    public function redemption(): BelongsTo
    {
        return $this->belongsTo(Redemption::class);
    }

    /**
     * Scope para ordenar por data de criação (mais recente primeiro)
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope para ordenar por data de criação (mais antigo primeiro)
     */
    public function scopeOldest($query)
    {
        return $query->orderBy('created_at', 'asc');
    }

    /**
     * Scope para filtrar por resgate
     */
    public function scopeForRedemption($query, $redemptionId)
    {
        return $query->where('redemption_id', $redemptionId);
    }

    /**
     * Scope para filtrar por status
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('new_status', $status);
    }

    /**
     * Obter o label amigável do status antigo
     */
    public function getOldStatusLabelAttribute()
    {
        return $this->getStatusLabel($this->old_status);
    }

    /**
     * Obter o label amigável do novo status
     */
    public function getNewStatusLabelAttribute()
    {
        return $this->getStatusLabel($this->new_status);
    }

    /**
     * Mapear status para labels amigáveis
     */
    private function getStatusLabel($status)
    {
        $labels = [
            'pending' => 'Pendente',
            'processing' => 'Processando',
            'confirmed' => 'Confirmado',
            'shipped' => 'Enviado',
            'delivered' => 'Entregue',
            'cancelled' => 'Cancelado',
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * Criar um registro de histórico de status
     */
    public static function createHistory(
        $redemptionId,
        $oldStatus,
        $newStatus,
        $comment = null,
        $createdBy = 'SYSTEM',
        $createdByName = 'Sistema'
    ) {
        return self::create([
            'redemption_id' => $redemptionId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'comment' => $comment,
            'created_by' => $createdBy,
            'created_by_name' => $createdByName,
        ]);
    }

    /**
     * Formatar dados para API
     */
    public function toApiFormat()
    {
        return [
            'id' => 'SH' . str_pad($this->id, 3, '0', STR_PAD_LEFT),
            'status' => $this->new_status,
            'comment' => $this->comment ?? 'Status atualizado',
            'createdAt' => $this->created_at->toISOString(),
            'createdBy' => $this->created_by,
            'redeemId' => 'R' . str_pad($this->redemption_id, 3, '0', STR_PAD_LEFT),
            'createdByName' => $this->created_by_name,
        ];
    }
}