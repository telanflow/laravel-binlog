<?php

namespace Telanflow\Binlog;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Telanflow\Binlog\Commands\BinlogCommand;
use Telanflow\Binlog\Server\Facades\Client;
use Telanflow\Binlog\Server\Manager;
use Telanflow\Binlog\Server\PidManager;

class LaravelServiceProvider extends ServiceProvider
{
    /**
     * 在容器中注册绑定。
     *
     * @return void
     */
    public function register()
    {
        $this->registerCommands();
        $this->registerManager();
        $this->registerPidManager();
        $this->registerClient();
    }

    /**
     * 在注册后启动服务。
     *
     * @return void
     */
    public function boot()
    {
        $this->publishFiles();
        $this->mergeConfigs();
    }

    /**
     * Publish files of this package.
     */
    protected function publishFiles()
    {
        $this->publishes([
            __DIR__ . '/../config/binlog.php' => base_path('config/binlog.php'),
        ], 'laravel-binlog');
    }

    /**
     * Merge configurations.
     */
    protected function mergeConfigs()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/binlog.php', 'binlog');
    }

    /**
     * Register commands.
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BinlogCommand::class,
            ]);
        }
    }

    protected function registerClient()
    {
        $this->app->singleton(Client::class, function () {
            return new \Telanflow\Binlog\Server\Client(SWOOLE_SOCK_TCP | SWOOLE_KEEP);
        });
        $this->app->alias(Client::class, 'binlog.client');
    }

    /**
     * Register manager.
     *
     * @return void
     */
    protected function registerManager()
    {
        $this->app->singleton(Manager::class, function ($app) {
            return new Manager($app, $app->make(Client::class));
        });

        $this->app->alias(Manager::class, 'binlog.manager');
    }

    /**
     * Register pid manager.
     *
     * @return void
     */
    protected function registerPidManager()
    {
        $this->app->singleton(PidManager::class, function (Container $app) {
            /** @var Config $config */
            $config = $app->make('config');
            $pidFile = $config->get('binlog.options.pid_file');
            return new PidManager($pidFile);
        });

        $this->app->alias(PidManager::class, 'binlog.pidManager');
    }

}
