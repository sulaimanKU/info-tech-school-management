<?php

namespace App\Providers;

use App\Contracts\PushProvider;
use App\Contracts\SmsProvider;
use App\Contracts\WhatsAppProvider;
use App\Services\Push\DisabledPushProvider;
use App\Services\Push\DisabledWhatsAppProvider;
use App\Services\Push\FcmPushProvider;
use App\Services\Sms\DisabledSmsProvider;
use App\Services\Sms\LocalSmsProvider;
use App\Services\Sms\SpextSmsProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PushProvider::class, fn () => config('school.push_driver') === 'fcm' ? new FcmPushProvider : new DisabledPushProvider);
        $this->app->bind(WhatsAppProvider::class, DisabledWhatsAppProvider::class);
        $this->app->bind(SmsProvider::class, function () {
            if (config('school.sms_driver') === 'spext') {
                return new SpextSmsProvider;
            }
            if (config('school.sms_driver') === 'local' && app()->environment(['local', 'testing'])) {
                return new LocalSmsProvider;
            }

            return new DisabledSmsProvider;
        });
    }

    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(10)->by('ip:'.$r->ip()),
            Limit::perMinute(5)->by('account:'.hash('sha256', strtolower((string) $r->input('email'))).'|'.$r->ip()),
        ]);
        RateLimiter::for('parent-request', fn (Request $r) => [Limit::perMinute(3)->by('otp-ip:'.$r->ip()), Limit::perHour(10)->by('otp-day:'.$r->ip())]);
        RateLimiter::for('parent-verify', fn (Request $r) => Limit::perMinute(10)->by('verify:'.$r->ip()));
        Gate::define('settings.read', fn ($user) => $user->hasPermission('settings.read'));
    }
}
