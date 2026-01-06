<?php

namespace Ejoi8\MalaysiaPaymentGateway;

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
        // 1. Load Package Resources
        // We load the views providing the checkout and status pages.
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'payment-gateway');

        // 2. Register Publishing Commands (Console Only)
        // If the application is running in the console (e.g., artisan commands),
        // we register the commands to publish config, views, and migrations.
        if ($this->app->runningInConsole()) {
            // Load package migrations
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

            // Publish configuration file
            $this->publishes([
                __DIR__.'/../config/payment-gateway.php' => config_path('payment-gateway.php'),
            ], 'payment-gateway-config');

            // Publish views
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/payment-gateway'),
            ], 'payment-gateway-views');

            // Publish migrations
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'payment-gateway-migrations');
        }

        // 3. Register Routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // 4. Register Event Listeners
        // We listen for payment events to trigger notifications and status updates.

        // Listeners for Notifications (e.g., Email, SMS)
        $this->app['events']->listen(
            \Ejoi8\MalaysiaPaymentGateway\Events\PaymentInitiated::class,
            \Ejoi8\MalaysiaPaymentGateway\Listeners\SendPaymentNotification::class
        );
        $this->app['events']->listen(
            \Ejoi8\MalaysiaPaymentGateway\Events\PaymentSucceeded::class,
            \Ejoi8\MalaysiaPaymentGateway\Listeners\SendPaymentNotification::class
        );
        $this->app['events']->listen(
            \Ejoi8\MalaysiaPaymentGateway\Events\PaymentFailed::class,
            \Ejoi8\MalaysiaPaymentGateway\Listeners\SendPaymentNotification::class
        );

        // Listeners for Status Updates (e.g., updating database records)
        $this->app['events']->listen(
            \Ejoi8\MalaysiaPaymentGateway\Events\PaymentSucceeded::class,
            \Ejoi8\MalaysiaPaymentGateway\Listeners\UpdatePaymentStatus::class
        );
        $this->app['events']->listen(
            \Ejoi8\MalaysiaPaymentGateway\Events\PaymentFailed::class,
            \Ejoi8\MalaysiaPaymentGateway\Listeners\UpdatePaymentStatus::class
        );
    }

    /**
     * Register the core gateway drivers.
     */
    protected function registerCoreGateways(GatewayManager $manager): void
    {
        // Iterate through the gateways defined in the configuration file.
        $gateways = config('payment-gateway.gateways', []);

        foreach ($gateways as $name => $config) {
            // Skip gateways that don't have a driver class defined.
            if (empty($config['driver_class'])) {
                continue;
            }

            // Register the driver with the GatewayManager.
            // We use a closure to defer instantiation until the driver is actually needed.
            $manager->extend($name, function ($app) use ($name) {
                $customConfig = config("payment-gateway.gateways.{$name}");
                $class = $customConfig['driver_class'] ?? null;

                if (! $class) {
                    throw new \InvalidArgumentException("Driver class not defined for gateway [{$name}].");
                }

                // If the driver class has a static 'make' method (Factory Pattern), use it.
                if (method_exists($class, 'make')) {
                    return $class::make($customConfig);
                }

                // Otherwise, simple instantiation.
                return new $class;
            });
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
