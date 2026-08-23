@extends('emails.layout')

@section('titulo', 'Seu código de acesso ao Resolve+')

{{-- Código na prévia: quem só quer entrar copia da lista de e-mails, sem abrir. --}}
@section('preheader', $codigo . ' é o seu código. Expira em ' . $expiraEmMinutos . ' minutos.')

@section('conteudo')
    <h1 style="margin:0 0 10px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; font-size:22px; line-height:29px; font-weight:700; letter-spacing:-0.4px; color:#0C1615;" class="rp-titulo">Seu código de acesso</h1>

    <p style="margin:0 0 26px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; font-size:15px; line-height:23px; color:#5B6B69;" class="rp-texto">
        Olá, {{ $primeiroNome }}. Digite o código abaixo na tela de acesso para entrar no Resolve+ — sem senha para criar nem lembrar.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" style="background-color:#F7FAFA; border:1px solid #DCE4E3; border-radius:12px; padding:24px 16px 20px;" class="rp-caixa">
                {{-- Monoespaçada e espaçada porque o código é para ser lido em
                     voz alta, conferido dígito a dígito e digitado em outra
                     tela. O text-indent compensa o espaço que o letter-spacing
                     acrescenta depois do último dígito e devolve o centro. --}}
                <div style="font-family:SFMono-Regular,ui-monospace,Menlo,Consolas,'Liberation Mono',monospace; font-size:36px; line-height:44px; font-weight:700; letter-spacing:10px; text-indent:10px; color:#0F766E;" class="rp-codigo">{{ $codigo }}</div>
                <div style="padding-top:10px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; font-size:12px; line-height:18px; letter-spacing:0.3px; color:#8B9A98;" class="rp-faint">expira em {{ $expiraEmMinutos }} minutos &middot; uso único</div>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td style="padding:26px 0 20px;">
                <div style="border-top:1px solid #E3EAE9; font-size:0; line-height:0;" class="rp-divisor">&nbsp;</div>
            </td>
        </tr>
    </table>

    <p style="margin:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; font-size:13px; line-height:20px; color:#8B9A98;" class="rp-faint">
        Não foi você que pediu? Pode ignorar este e-mail. O código sozinho não abre nada e ninguém entra na sua conta sem ele.
    </p>
@endsection

@section('rodape')
    <strong style="color:#5B6B69;" class="rp-texto">Resolve+</strong> &middot; serviços para a sua casa, com profissionais verificados.<br />
    Precisa de ajuda? É só responder este e-mail.
@endsection
