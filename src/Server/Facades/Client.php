<?php

namespace Telanflow\Binlog\Server\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Client
 *
 * @package Telanflow\Binlog\Server\Facades
 */
class Client extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'binlog.client';
    }
}
