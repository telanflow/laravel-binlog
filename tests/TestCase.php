<?php

namespace Telanflow\Binlog\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Telanflow\Binlog\Configure\Configure;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            'Telanflow\Binlog\LaravelServiceProvider',
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('binlog', include('config/binlog.php'));
        $app['config']->set('binlog.connection', [
            'host' => '127.0.0.1',
            'port' => '3306',
            'username' => 'forge',
            'password' => '',
            'charset' => 'utf8',

            // Binlog slave_id
            'slave_id' => '1',
            // Binlog heartbeat
            'heartbeat' => 5,
        ]);
        Configure::parse($app['config']->get('binlog'));
    }

}