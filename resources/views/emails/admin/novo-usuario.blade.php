@extends('emails.layout')

@section('titulo', 'Novo cadastro no Resolve+')

@section('preheader', $usuario->nome . ' entrou como ' . $tipo . '.')

@section('conteudo')
    <h1 style="margin:0 0 16px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; font-size:22px; line-height:29px; font-weight:700; color:#0C1615;" class="rp-titulo">Entrou uma conta nova</h1>

    <p style="margin:0 0 8px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; font-size:15px; line-height:23px; color:#5B6B69;" class="rp-texto"><strong>Nome:</strong> {{ $usuario->nome }}</p>
    <p style="margin:0 0 8px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; font-size:15px; line-height:23px; color:#5B6B69;" class="rp-texto"><strong>E-mail:</strong> {{ $usuario->email }}</p>
    <p style="margin:0 0 8px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; font-size:15px; line-height:23px; color:#5B6B69;" class="rp-texto"><strong>Tipo:</strong> {{ $tipo }} &middot; cadastro por {{ $origem }}</p>
    <p style="margin:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; font-size:15px; line-height:23px; color:#5B6B69;" class="rp-texto"><strong>Quando:</strong> {{ $cadastradoEm }} (horário de Brasília)</p>
@endsection
