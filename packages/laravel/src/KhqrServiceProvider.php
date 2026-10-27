<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Laravel;

use Illuminate\Support\ServiceProvider;
use TonSamith\Khqr\Core\Contracts\PaymentStoreInterface;
use TonSamith\Khqr\Core\Khqr as CoreKhqr;
use TonSamith\Khqr\Laravel\Storage\LaravelPaymentStore;

final class KhqrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/khqr.php', 'khqr');
        $this->app->singleton(CoreKhqr::class, static fn (): CoreKhqr => new CoreKhqr());

        // No bank transport is ever automatically registered.
        $this->app->bind(
            PaymentStoreInterface::class,
            static fn ($app): LaravelPaymentStore => new LaravelPaymentStore($app['db']->connection()),
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/khqr.php' => config_path('khqr.php'),
        ], 'khqr-config');

        // Explicit opt-in: never run database migrations automatically.
        $this->publishes([
            __DIR__ . '/../database/migrations/create_khqr_payment_requests_table.php'
                => database_path('migrations/2026_10_28_000001_create_khqr_payment_requests_table.php'),
        ], 'khqr-migrations');
    }
}
