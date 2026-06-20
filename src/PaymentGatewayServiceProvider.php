<?php

namespace Ejoi8\MalaysiaPaymentGateway;

use Ejoi8\MalaysiaPaymentGateway\Events\PaymentFailed;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentInitiated;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentRefunded;
use Ejoi8\MalaysiaPaymentGateway\Console\Commands\ReconcilePaymentsCommand;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentSucceeded;
use Ejoi8\MalaysiaPaymentGateway\Listeners\DispatchPaymentWebhook;
use Ejoi8\MalaysiaPaymentGateway\Listeners\PersistInitiationId;
use Ejoi8\MalaysiaPaymentGateway\Listeners\SendPaymentNotification;
use Ejoi8\MalaysiaPaymentGateway\Listeners\UpdatePaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\Support\GatewayFactory;
use Illuminate\Support\ServiceProvider;

class PaymentGatewayServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 1. Merge the package configuration file
        // This allows users to override specific values in their own config file
        // while keeping the package defaults for anything they don't define.
        $this->mergeConfigFrom(
            __DIR__.'/../config/payment-gateway.php',
            'payment-gateway'
        );

        // 2. Register the GatewayManager singleton
        // We use a singleton so that the same instance of the manager is used
        // throughout the request lifecycle. This manager is responsible for
        // resolving and managing different payment gateway drivers.
        $this->app->singleton(GatewayManager::class, function ($app) {
            $manager = new GatewayManager;
            $this->registerCoreGateways($manager);

            return $manager;
        });

        // 3. Register an alias for the specific service
        // This allows developers to use app('payment-gateway') to resolve the manager.
        $this->app->alias(GatewayManager::class, 'payment-gateway');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'payment-gateway');

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
            $this->registerPublishes();
            $this->commands([ReconcilePaymentsCommand::class]);
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->registerEventListeners();
    }

    /**
     * Register the core gateway drivers.
     */
    protected function registerCoreGateways(GatewayManager $manager): void
    {
        // Iterate through the gateways defined in the configuration file.
        $gateways = config('payment-gateway.gateways', []);

        foreach ($gateways as $name => $config) {
            if (($config['enabled'] ?? true) === false || empty($config['driver_class'])) {
                continue;
            }

            $manager->extend($name, function ($app) use ($name) {
                $customConfig = config("payment-gateway.gateways.{$name}");

                return GatewayFactory::make($customConfig);
            });
        }
    }

    protected function registerPublishes(): void
    {
        $this->publishes([
            __DIR__.'/../config/payment-gateway.php' => config_path('payment-gateway.php'),
        ], 'payment-gateway-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/payment-gateway'),
        ], 'payment-gateway-views');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'payment-gateway-migrations');
    }

    protected function registerEventListeners(): void
    {
        foreach ([PaymentInitiated::class, PaymentSucceeded::class, PaymentFailed::class] as $event) {
            $this->app['events']->listen($event, SendPaymentNotification::class);
        }

        $this->app['events']->listen(PaymentInitiated::class, PersistInitiationId::class);

        foreach ([PaymentSucceeded::class, PaymentFailed::class, PaymentRefunded::class] as $event) {
            $this->app['events']->listen($event, UpdatePaymentStatus::class);
        }

        foreach ([PaymentSucceeded::class, PaymentFailed::class, PaymentRefunded::class] as $event) {
            $this->app['events']->listen($event, DispatchPaymentWebhook::class);
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        // Return the service container bindings registered by this provider.
        // This is used if the provider is deferred (not the case here, but good practice).
        return [
            GatewayManager::class,
            'payment-gateway',
        ];
    }
}
