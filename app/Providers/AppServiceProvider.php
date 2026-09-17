<?php

namespace App\Providers;

use App\Services\Sms\AfricaTalkingSmsSender;
use Illuminate\Support\ServiceProvider;
use Markt\LaravelAuth\Contracts\SmsSender;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;

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
        // Binding AT sms sender with auth package sms sender
        $this->app->bind(
            SmsSender::class,
            AfricaTalkingSmsSender::class
        );

        //security input for scramble docs
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );
            });
    }
}
