@component('mail::message')
# Resgate de Pontos Realizado com Sucesso!

Olá, **{{ $user->name }}**!

Seu resgate de pontos foi processado com sucesso.

## Detalhes do Resgate

@component('mail::panel')
**Produto:** {{ $product->post_title }}<br>
**Quantidade:** {{ $quantity }}<br>
**Pontos utilizados:** {{ number_format($pointsUsed, 0, ',', '.') }}<br>
**Data do resgate:** {{ $redemption->created_at->format('d/m/Y H:i') }}<br>
**Código do resgate:** #{{ App\Services\Qlib::redeem_code($redemption->id) }}
@endcomponent

Seu resgate será processado em breve. Você receberá mais informações sobre a entrega em seu email.

@component('mail::button', ['url' => url('/loja/meus-resgates')])
Ver Meus Resgates
@endcomponent

Obrigado por usar nosso sistema de pontos!

@component('mail::subcopy')
Este é um email automático. Por favor, não responda a este email.
@endcomponent
@endcomponent
