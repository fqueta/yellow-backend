<?
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasUuids;
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

    protected $casts = [
        'email_verified_at' => 'datetime',
        'config' => 'array',
        'preferencias' => 'array',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'token',
    ];
    public $incrementing = false;   // 👈 precisa porque o id não é int
    protected $keyType = 'string';  // 👈 precisa porque UUID é string
    // RELACIONAMENTOS
    // Relacionamentos com permission removidos pois a coluna permission_id não existe na tabela users
    
    // public function permission()
    // {
    //     return $this->belongsTo(Permission::class);
    // }

    // public function menus()
    // {
    //     // Relacionamento através da permission_id do usuário
    //     return $this->hasManyThrough(
    //         Menu::class,
    //         'App\Models\MenuPermission',
    //         'permission_id', // chave estrangeira na tabela menu_permission
    //         'id', // chave estrangeira na tabela menus
    //         'permission_id', // chave local na tabela users
    //         'menu_id' // chave local na tabela menu_permission
    //     );
    // }


    // MÉTODO PARA RETORNAR MENUS FORMATADOS
    // public function menusPermitidosFiltrados()
    // {
    //     return $this->menus()
    //         ->with('submenus') // Caso queira carregar itens de menus
    //         ->orderBy('title')
    //         ->get()
    //         ->map(function ($menu) {
    //             return [
    //                 'title' => $menu->title,
    //                 'url'   => $menu->url,
    //                 'icon'  => $menu->icon,
    //                 'items' => $menu->items ? json_decode($menu->items, true) : null,
    //             ];
    //         });
    // }
}
