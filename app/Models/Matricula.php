<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Matricula extends Model
{
    use HasFactory;
    const CREATED_AT = 'data';
    const UPDATED_AT = 'atualizado';
    protected $casts = [
        'orc' => 'array',
    ];
    protected $fillable = [
        'id',
        'token',
        'orc',
        'memo',
        'route',
        'icon',
        'actived',
    ];
}
