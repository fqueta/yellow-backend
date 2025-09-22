<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use App\Services\Qlib;

/**
 * Modelo Partner - representa parceiros/fornecedores no sistema
 * Herda de User e utiliza permission_id = 5 para identificar parceiros
 */
class Partner extends User
{
    protected $table = 'users';

    /**
     * Configurações do modelo ao ser inicializado
     * Define o permission_id como 5 para parceiros e aplica scope global
     */
    protected static function booted()
    {
        $partner_permission_id = Qlib::qoption('permission_partner_id') ?? 5;
        
        // Força permission_id = 5 ao criar um novo parceiro
        static::creating(function ($partner) use ($partner_permission_id) {
            $partner->permission_id = $partner_permission_id;
        });

        // Aplica scope global para trazer apenas usuários com permission_id = 5
        static::addGlobalScope('partner', function (Builder $builder) use ($partner_permission_id) {
            $builder->where('permission_id', $partner_permission_id);
        });
    }

    /**
     * Campos que podem ser preenchidos em massa
     */
    protected $fillable = [
        'tipo_pessoa',
        'name',
        'razao',
        'cpf',
        'cnpj',
        'email',
        'password',
        'status',
        'genero',
        'verificado',
        'permission_id',
        'config',
        'preferencias',
        'foto_perfil',
        'ativo',
        'autor',
        'token',
        'excluido',
        'reg_excluido',
        'deletado',
        'reg_deletado',
    ];

    /**
     * Campos ocultos na serialização
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];
}