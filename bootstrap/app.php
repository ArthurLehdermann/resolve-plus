<?php

use App\Services\Console\AutoApproveServicesCommand;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        AutoApproveServicesCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => null);

        /*
         * O container só é alcançável pelo proxy (Traefik), nunca direto, então
         * o X-Forwarded-* que chega é sempre dele. Sem confiar nesses
         * cabeçalhos o Laravel enxerga a requisição como http e monta URL
         * absoluta em http — o que fazia o navegador bloquear o CSS do site
         * por conteúdo misto numa página https.
         */
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A API responde JSON mesmo quando o cliente esquece o Accept (o
        // webhook do gateway, por exemplo). O site é HTML e precisa ficar de
        // fora, senão um erro na página pública devolve um objeto JSON no
        // navegador.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
