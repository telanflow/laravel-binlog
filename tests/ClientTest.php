<?php

namespace Telanflow\Binlog\Tests;

use Telanflow\Binlog\Server\Client;

class ClientTest extends TestCase
{
    /**
     * @var Client
     */
    protected $client;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->client = new Client(SWOOLE_SOCK_TCP | SWOOLE_KEEP);
    }

    /**
     * @test
     */
    public function testConnection()
    {
        try
        {
            $ret = $this->client->connect(config('binlog.connection.host'), config('binlog.connection.port'), 10);
            $this->assertTrue($ret);

            // Auth
            $this->client->authenticate();

            // RegisterSlave
            $this->client->getBinlogStream();

            while (true)
            {
                $resp = $this->client->read();
                break;
            }
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->client->close();
        $this->assertTrue(true);
    }

}