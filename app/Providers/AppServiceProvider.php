<?php

namespace App\Providers;

use App\Services\Assessments\PythonAssessmentClient;
use App\Services\Auth\AuthService;
use App\Services\Auth\OtpService;
use App\Services\Sms\AfricaTalkingSmsSender;
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
    }
}
