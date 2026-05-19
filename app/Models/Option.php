<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Schema;

class Option extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'token',
        'name',
        'url',
        'value',
        'ativo',
        'obs',
        'excluido',
        'reg_excluido',
        'deletado',
        'reg_deletado',
    ];

    // protected $casts = [
    //     'value' => 'array',
    // ];

    public $incrementing = false;   // 👈 precisa porque o id não é int
    protected $keyType = 'string';  // 👈 precisa porque UUID é string

    protected static function booted()
    {
        static::addGlobalScope('active', function ($query) {
            try {
                $conn = $query->getModel()->getConnectionName();
                $schema = Schema::connection($conn);
                if ($schema->hasColumn('options', 'deletado') && $schema->hasColumn('options', 'excluido')) {
                    $query->where(function ($q) {
                        $q->whereNull('deletado')->orWhere('deletado', '!=', 's');
                    })->where(function ($q) {
                        $q->whereNull('excluido')->orWhere('excluido', '!=', 's');
                    });
                }
            } catch (\Throwable $e) {
            }
        });

        static::saved(function ($option) {
            if ($option->url) {
                \App\Services\Qlib::clear_option_cache($option->url);
            }
        });

        static::deleted(function ($option) {
            if ($option->url) {
                \App\Services\Qlib::clear_option_cache($option->url);
            }
        });
    }
}
