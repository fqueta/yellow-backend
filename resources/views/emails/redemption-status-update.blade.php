@component('mail::message')
# Atualização do Status do seu Resgate

Olá, **{{ $user->name }}**!

O status do seu resgate foi atualizado.

## Informações da Atualização

@component('mail::panel')
**Status anterior:** {{ $oldStatusLabel }}<br>
**Novo status:** {{ $newStatusLabel }}<br>
**Data da atualização:** {{ now()->format('d/m/Y H:i') }}<br>
@if($updatedBy)
**Atualizado por:** {{ $updatedBy->name }}
@endif
@endcomponent

## Detalhes do Resgate

@component('mail::table')
| Campo | Valor |
|:------|:------|
| **Produto** | {{ $redemption->product->post_title ?? 'N/A' }} |
| **Quantidade** | {{ $redemption->quantity }} |
| **Pontos utilizados** | {{ number_format($redemption->points_used, 0, ',', '.') }} |
| **Data do resgate** | {{ $redemption->created_at->format('d/m/Y H:i') }} |
| **Código do resgate** | #{{ App\Services\Qlib::redeem_code($redemption->id) }} |
@endcomponent

@if($newStatus === 'processing')
@component('mail::panel')
**Seu resgate está sendo processado!** Nossa equipe está preparando seu pedido.
@endcomponent
@elseif($newStatus === 'confirmed')
@component('mail::panel')
**Seu resgate foi confirmado!** Em breve você receberá informações sobre o envio.
@endcomponent
@elseif($newStatus === 'shipped')
@component('mail::panel')
**Seu resgate foi enviado!** Você receberá o código de rastreamento em breve.
@endcomponent
@elseif($newStatus === 'delivered')
@component('mail::panel')
**Seu resgate foi entregue!** Esperamos que você aproveite seu produto.
@endcomponent
@elseif($newStatus === 'cancelled')
@component('mail::panel')
**Seu resgate foi cancelado.** Os pontos foram devolvidos à sua conta. Se você tem dúvidas, entre em contato conosco.
@endcomponent
@endif

@component('mail::button', ['url' => url('/loja/meus-resgates')])
Ver Meus Resgates
@endcomponent

Obrigado por usar nosso sistema de pontos!

@component('mail::subcopy')
Este é um email automático. Por favor, não responda a este email.
@endcomponent
@endcomponent
