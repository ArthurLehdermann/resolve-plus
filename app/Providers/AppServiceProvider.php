<?php

namespace App\Providers;

use App\Categories\Models\Categoria;
use App\Categories\Policies\CategoriaPolicy;
use App\Payments\Listeners\CapturePaymentOnApproval;
use App\PropertyHistory\Listeners\RecordInterventionOnApproval;
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
        //
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
    }
}
