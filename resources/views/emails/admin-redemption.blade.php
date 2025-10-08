@component('mail::message')
# Novo Resgate de Pontos Realizado

Olá, **Administrador**!

Um novo resgate de pontos foi realizado no sistema e requer sua atenção.

## Informações do Cliente

@component('mail::panel')
**Nome:** {{ $user->name }}<br>
**Email:** {{ $user->email }}<br>
**ID do Cliente:** #{{ $user->id }}
@endcomponent

## Detalhes do Resgate

@component('mail::table')
| Campo | Valor |
|:------|:------|
| **Produto** | {{ $product->post_title }} |
| **Quantidade** | {{ $quantity }} |
| **Pontos utilizados** | {{ number_format($pointsUsed, 0, ',', '.') }} |
| **Data do resgate** | {{ $redemption->created_at->format('d/m/Y H:i') }} |
| **Código do resgate** | #{{ App\Services\Qlib::redeem_code($redemption->id) }} |
| **Status** | {{ ucfirst($redemption->status) }} |
@endcomponent

@component('mail::panel')
**Ação Necessária:** Por favor, processe este resgate o mais breve possível e entre em contato com o cliente se necessário.
@endcomponent

@component('mail::button', ['url' => url('/admin/redemptions')])
Gerenciar Resgates
@endcomponent

@component('mail::subcopy')
Este é um email automático do sistema de pontos. Para mais informações, acesse o painel administrativo.
@endcomponent
@endcomponent
