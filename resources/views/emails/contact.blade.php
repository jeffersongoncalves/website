@php
    $kind = $payload['kind'] ?? 'outro';
    $kindLabel = config("site.contact_kinds.$kind.pt", $kind);
@endphp

<h2>Nova mensagem · {{ $kindLabel }}</h2>

<table cellpadding="6" cellspacing="0" style="font-family:Arial,sans-serif;font-size:14px;border-collapse:collapse;">
    <tr><td><strong>Tipo:</strong></td><td>{{ $kindLabel }}</td></tr>
    <tr><td><strong>Nome:</strong></td><td>{{ $payload['name'] }}</td></tr>
    @if(!empty($payload['company']))
        <tr><td><strong>Empresa:</strong></td><td>{{ $payload['company'] }}</td></tr>
    @endif
    <tr><td><strong>E-mail:</strong></td><td>{{ $payload['email'] }}</td></tr>
    @if(!empty($payload['budget']))
        <tr><td><strong>Orçamento:</strong></td><td>{{ $payload['budget'] }}</td></tr>
    @endif
</table>

<h3>Mensagem</h3>
<p style="white-space:pre-wrap;font-family:Arial,sans-serif;font-size:14px;line-height:1.6;">{{ $payload['message'] }}</p>

<hr>
<p style="font-family:Arial,sans-serif;font-size:11px;color:#666;">
    Enviado via formulário em {{ now()->toDateTimeString() }} · Locale: {{ $payload['locale'] ?? 'pt' }}
</p>
