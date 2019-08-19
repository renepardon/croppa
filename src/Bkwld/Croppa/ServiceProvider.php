<?php

namespace Bkwld\Croppa;

use Bkwld\Croppa\Commands\Purge;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

/**
 * Class ServiceProvider
 *
 * @package Bkwld\Croppa
 */
class ServiceProvider extends IlluminateServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Version specific registering
        if (abs($this->version()) == 5) {
            $this->registerLaravel5Lumen();
        }

        // Bind the Croppa URL generator and parser
        $this->app->singleton(URL::class, function ($app) {
            return new URL($this->getConfig());
        });

        // Handle the request for an image, this cooridnates the main logic
        $this->app->singleton(Handler::class, function ($app) {
            return new Handler($app[URL::class],
                $app[Storage::class],
                $app['request'],
                $this->getConfig());
        });

        // Interact with the disk
        $this->app->singleton(Storage::class, function ($app) {
            return new Storage($app, $this->getConfig());
        });

        // API for use in apps
        $this->app->singleton(Helpers::class, function ($app) {
            return new Helpers($app[URL::class], $app[Storage::class], $app[Handler::class]);
        });

        // Register command to delte all crops
        $this->app->singleton(Purge::class, function ($app) {
            return new Commands\Purge($app[Storage::class]);
        });

        // Register all commadns
        $this->commands(Purge::class);
    }

    /**
     * Get the major Laravel version number
     *
     * @return integer
     */
    public function version()
    {
        $app = $this->app;

        if (defined(get_class($app) . '::VERSION')) {
            return intval($app::VERSION);
        }

        if (is_callable([$app, 'version'])) {
            preg_match('/(\((\d+\.\d+\.\d+)\))/', $app->version(), $v);
            if (isset($v[2])) {
                return -intval($v[2]);
            }
        }

        return null;
    }

    /**
     * Register specific logic for Laravel/Lumen 5. Merges package config with user config
     *
     * @return void
     */
    public function registerLaravel5Lumen()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/config.php', 'croppa');
    }

    /**
     * Get the configuration, which is keyed differently in L5 vs l4
     *
     * @return array
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function getConfig()
    {
        $key = abs($this->version()) == 5 ? 'croppa' : 'croppa::config';

        $config = $this->app->make('config')->get($key);

        // Use Laravel's encryption key if instructed to
        if (isset($config['signing_key']) && $config['signing_key'] == 'app.key') {
            $config['signing_key'] = $this->app->make('config')->get('app.key');
        }

        return $config;
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            URL::class,
            Handler::class,
            Storage::class,
            Helpers::class,
            Purge::class,
        ];
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     * @throws Exception
     */
    public function boot()
    {
        // Version specific booting
        switch ($this->version()) {
            case 4:
                $this->bootLaravel4();
                break;
            case 5:
                $this->bootLaravel5();
                break;
            case -5:
                $this->bootLumen();
                break;
            default:
                throw new Exception('Unsupported Laravel version');
        }

        // Listen for Croppa style URLs, these are how Croppa gets triggered
        if ($this->version() > 0) { // Laravel
            $this->app['router']
                ->get('{path}', 'Bkwld\Croppa\Handler@handle')
                ->where('path', $this->app[URL::class]->routePattern());
        } else { // Lumen
            $this->app->get('{path:' . $this->app[URL::class]->routePattern() . '}', [
                'uses' => 'Bkwld\Croppa\Handler@handle',
            ]);
        }
    }

    /**
     * Boot specific logic for Laravel 4. Tells Laravel about the package for auto
     * namespacing of config files
     *
     * @return void
     */
    public function bootLaravel4()
    {
        $this->package('bkwld/croppa');
    }

    /**
     * Boot specific logic for Laravel 5. Registers the config file for publishing
     * to app directory
     *
     * @return void
     */
    public function bootLaravel5()
    {
        $this->publishes([
            __DIR__ . '/../../config/config.php' => config_path('croppa.php'),
        ], 'croppa');
    }

    /**
     * Boot specific logic for Lumen. Load custom croppa config file
     *
     * @return void
     */
    public function bootLumen()
    {
        $this->app->configure('croppa');
    }
}
