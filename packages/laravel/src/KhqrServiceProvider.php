<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Laravel;

use Illuminate\Support\ServiceProvider;
use TonSamith\Khqr\Core\Khqr as CoreKhqr;

final class KhqrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/khqr.php', 'khqr');

        $this->app->singleton(CoreKhqr::class, static fn (): CoreKhqr => new CoreKhqr());
    }

    public function boot(): void
    {
        $this->publishes(
            [__DIR__ . '/../config/khqr.php' => config_path('khqr.php')],
            'khqr-config',
        );
    }
}

