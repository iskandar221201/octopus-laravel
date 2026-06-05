<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel;

use Illuminate\Support\ServiceProvider;
use OctopusLLM\Laravel\Contracts\StorageInterface;
use OctopusLLM\Laravel\Contracts\HttpClientInterface;
use OctopusLLM\Laravel\Http\LaravelHttpClient;
use OctopusLLM\Laravel\Storage\LaravelCacheStorage;
use OctopusLLM\Laravel\Commands\OctopusValidate;
use OctopusLLM\Laravel\Commands\OctopusStatus;
use OctopusLLM\Laravel\Commands\OctopusBenchmark;
use OctopusLLM\Laravel\Commands\OctopusTest;
use OctopusLLM\Laravel\Commands\OctopusRecover;

class OctopusLLMServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge default config — user config override default, bukan sebaliknya
        $this->mergeConfigFrom(__DIR__ . '/../config/octopus.php', 'octopus');

        // Bind HttpClientInterface ke LaravelHttpClient
        $this->app->bind(HttpClientInterface::class, function ($app) {
            return new LaravelHttpClient($app->make('Illuminate\Http\Client\Factory'));
        });

        // Bind StorageInterface berdasarkan config 'storage'
        $this->app->bind(StorageInterface::class, function ($app) {
            $config = $app['config']['octopus'];

            return match ($config['storage']) {
                'custom' => $app->make($config['storage_class']),
                default  => new LaravelCacheStorage(
                    $app['cache.store'],
                    $config['cache_key_prefix'],
                    (int) $config['cache_ttl'],
                ),
            };
        });

        // Singleton OctopusGateway — satu instance per request lifecycle
        $this->app->singleton(OctopusGateway::class, function ($app) {
            return new OctopusGateway(
                config: $app['config']['octopus'],
                storage: $app->make(StorageInterface::class),
                http: $app->make(HttpClientInterface::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish config file
        $this->publishes([
            __DIR__ . '/../config/octopus.php' => config_path('octopus.php'),
        ], 'octopus-config');

        // Register Artisan commands hanya saat running in console
        if ($this->app->runningInConsole()) {
            $this->commands([
                OctopusValidate::class,
                OctopusStatus::class,
                OctopusBenchmark::class,
                OctopusTest::class,
                OctopusRecover::class,
            ]);
        }
    }
}
