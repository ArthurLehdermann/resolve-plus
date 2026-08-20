<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver do gateway
    |--------------------------------------------------------------------------
    |
    | `asaas` é o gateway do MVP (ADR-005). `fake` é o adapter de testes e
    | desenvolvimento local sem credenciais.
    |
    */

    'gateway' => env('PAYMENT_GATEWAY', 'fake'),

    'default_commission_percent' => (float) env('PAYMENT_COMMISSION_PERCENT', 10),

    'authorization_days' => (int) env('PAYMENT_AUTHORIZATION_DAYS', 3),

    'reauthorize_before_hours' => (int) env('PAYMENT_REAUTHORIZE_BEFORE_HOURS', 12),

    'asaas' => [
        'api_key' => env('ASAAS_API_KEY'),
        'base_url' => env('ASAAS_BASE_URL', 'https://api-sandbox.asaas.com'),
        'fallback_remote_ip' => env('ASAAS_FALLBACK_REMOTE_IP', '127.0.0.1'),
        // Token configurado manualmente no painel do Asaas (Integrações >
        // Webhooks), devolvido em todo POST no header asaas-access-token.
        // Sem isso configurado, o endpoint de webhook recusa tudo (falha
        // fechada) - não existe um valor default aceito.
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
    ],

];
