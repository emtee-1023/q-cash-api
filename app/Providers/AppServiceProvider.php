<?php

namespace App\Providers;

use App\Services\Assessments\PythonAssessmentClient;
use App\Services\Auth\AuthService;
use App\Services\Auth\OtpService;
use App\Services\Daraja\DarajaClient;
use App\Services\Sms\AfricaTalkingSmsSender;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Markt\LaravelAuth\Contracts\SmsSender;
use Markt\LaravelAuth\Services\AuthService as BaseAuthService;
use Markt\LaravelAuth\Services\OtpService as BaseOtpService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PythonAssessmentClient::class, function ($app) {
            return new PythonAssessmentClient(
                config('services.ml_api.url'),
                config('services.ml_api.key'),
            );
        });

        $this->app->bind(BaseAuthService::class, AuthService::class);
        $this->app->bind(BaseOtpService::class, OtpService::class);

        $this->app->bind(DarajaClient::class, function (): DarajaClient {
            return new DarajaClient(
                config('services.mpesa.base_url'),
                config('services.mpesa.consumer_key'),
                config('services.mpesa.consumer_secret'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Binding AT sms sender with auth package sms sender
        $this->app->bind(
            SmsSender::class,
            AfricaTalkingSmsSender::class
        );

        // Allow docs when using cloudflared tunnel
        if (str_contains(request()->getHost(), 'trycloudflare.com')) {
            URL::forceScheme('https');
        }
    }
}
