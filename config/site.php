<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Endereço do app
    |--------------------------------------------------------------------------
    |
    | Para onde o site manda quem clica em "Abrir o app". O padrão deriva do
    | APP_URL trocando o host pelo subdomínio `app.` — que é como os ambientes
    | estão montados hoje (`resolveplus.…` para a API e o site, `app.…` para o
    | Flutter). Ambiente que fugir dessa convenção define SITE_APP_URL.
    |
    */

    'app_url' => env('SITE_APP_URL', preg_replace(
        '#^(https?://)#',
        '$1app.',
        rtrim((string) env('APP_URL', 'http://localhost'), '/')
    )),

    /*
    |--------------------------------------------------------------------------
    | Contato
    |--------------------------------------------------------------------------
    */

    'email_contato' => env('SITE_EMAIL_CONTATO', 'resolveplus@bigworks.com.br'),

    /*
    |--------------------------------------------------------------------------
    | Proteção de dados
    |--------------------------------------------------------------------------
    |
    | Quem responde pelos pedidos de titular (LGPD, art. 18) e o nome do
    | controlador que aparece na Política de Privacidade. O padrão do
    | encarregado é a mesma caixa de contato — trocar por um endereço
    | dedicado é só definir LGPD_DPO_EMAIL.
    |
    */

    'email_encarregado' => env('LGPD_DPO_EMAIL', env('SITE_EMAIL_CONTATO', 'resolveplus@bigworks.com.br')),

    'controlador' => env('LGPD_CONTROLADOR', 'BigWorks'),

];
