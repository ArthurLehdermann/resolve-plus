<?php

namespace App\Providers;

use App\Auth\Enums\TipoUsuario;
use App\Categories\Models\Categoria;
use App\Categories\Policies\CategoriaPolicy;
use App\Payments\Gateway\AsaasPaymentGateway;
use App\Payments\Gateway\FakePaymentGateway;
use App\Payments\Gateway\PaymentGateway;
use App\Payments\Listeners\CapturePaymentOnApproval;
use App\PropertyHistory\Listeners\RecordInterventionOnApproval;
use App\Ratings\Events\AvaliacaoRegistrada;
use App\Ratings\Listeners\RecalcularPerfilOnAvaliacao;
use App\Requests\Events\SolicitacaoCriada;
use App\Requests\Listeners\NotifyEligibleProfessionals;
use App\Services\Events\ServiceApproved;
use App\Warranty\Listeners\IssueWarrantyOnApproval;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FakePaymentGateway::class);

        $this->app->bind(PaymentGateway::class, function ($app): PaymentGateway {
            if ($app->environment('testing') || config('payments.gateway') === 'fake') {
                return $app->make(FakePaymentGateway::class);
            }

            return $app->make(AsaasPaymentGateway::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Categoria::class, CategoriaPolicy::class);

        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $email = method_exists($notifiable, 'getEmailForPasswordReset')
                ? $notifiable->getEmailForPasswordReset()
                : $notifiable->email;

            return config('app.url').'/api/v1/auth/reset-password?token='.$token.'&email='.urlencode($email);
        });

        Gate::define('admin', static function (?object $user): bool {
            if ($user === null) {
                return false;
            }

            return $user->tipo === TipoUsuario::Admin;
        });

        RateLimiter::for('auth-register', function (Request $request) {
            return Limit::perHour(10)->by($request->ip());
        });

        RateLimiter::for('auth-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('upload', function (Request $request) {
            return Limit::perHour(30)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('chat', function (Request $request) {
            return Limit::perMinute(60)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        Event::listen(ServiceApproved::class, CapturePaymentOnApproval::class);
        Event::listen(ServiceApproved::class, IssueWarrantyOnApproval::class);
        Event::listen(ServiceApproved::class, RecordInterventionOnApproval::class);
        Event::listen(SolicitacaoCriada::class, NotifyEligibleProfessionals::class);
        Event::listen(AvaliacaoRegistrada::class, RecalcularPerfilOnAvaliacao::class);
    }
}
