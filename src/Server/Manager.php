<?php

namespace Telanflow\Binlog\Server;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Telanflow\Binlog\Configure\Configure;
use Telanflow\Binlog\Constants\EventTypeConst;
use Telanflow\Binlog\Event\EventBinaryData;
use Telanflow\Binlog\Event\EventBuilder;
use Telanflow\Binlog\Event\EventInfo;
use Telanflow\Binlog\Exceptions\ConnectionException;
use Telanflow\Binlog\Exceptions\EventBinaryDataException;
use Telanflow\Binlog\Helpers\OS;
use Telanflow\Binlog\Exceptions\PacketCheckException;

class Manager
{
    const EOF_HEADER_VALUE = 254;

    /**
     * Container.
     *
     * @var Container
     */
    protected $container;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Repository
     */
    protected $cache;

    /**
     * @var bool
     */
    protected $exit = false;

    public function __construct(Container $container, Client $client)
    {
        $this->container = $container;
        $this->client = $client;
        $this->cache = Cache::store('array');
    }

    public function run()
    {
        $this->setProcessName('master process');
        $this->savePidFile();

        // register signal
        pcntl_signal(SIGINT, [$this, 'signalHandler'], false);
        pcntl_signal(SIGUSR1, [$this, 'signalHandler'], false);
        pcntl_signal(SIGUSR2, [$this, 'signalHandler'], false);
        pcntl_signal(SIGTERM, [$this, 'signalHandler'], false);
        pcntl_signal(SIGPIPE, SIG_IGN, false);

        try {

            // conn
            if(!$this->client->connect(Configure::getHost(), Configure::getPort(), 10)) {
                throw new ConnectionException($this->client->reuse, $this->client->errCode);
            }
            // auth
            $this->client->authenticate();
            // registerSlave
            $this->client->getBinlogStream();

            while (!$this->exit)
            {
                pcntl_signal_dispatch();

                try {
                    $this->consume();
                } catch (EventBinaryDataException $e) {}
            }

        } catch (\Exception $e) {
            print_r("Connect error: " . $e->getMessage() . ' : ' . $e->getCode());
            Log::error($e->getMessage());
        }
    }

    /**
     * Handler signal
     */
    public function signalHandler($signal)
    {
        /** @var PidManager $pidManager */
        $pidManager = $this->container->make(PidManager::class);
        $pid = $pidManager->read();

        $binlogCurrent = $this->client->getBinlogCurrent();
        $pidFileContent = json_encode($binlogCurrent);

        switch ($signal)
        {
            case SIGINT:
            case SIGUSR1:
            case SIGUSR2:
            case SIGTERM:
                $this->exit = true;
                if ($this->client->isConnected()) {
                    $this->client->close(true);
                }
                file_put_contents(Configure::getPosFile(), $pidFileContent);
                exit(0);
        }
    }

    /**
     * @throws PacketCheckException
     */
    protected function consume(): void
    {
        $recvData = $this->client->read();
        if (empty($recvData)) {
            return;
        }

        $binaryDataReader = new EventBinaryData($recvData);

        // check EOF_Packet -> https://dev.mysql.com/doc/internals/en/packet-EOF_Packet.html
        if (self::EOF_HEADER_VALUE === $binaryDataReader->readUInt8()) {
            return;
        }

        // decode all events data
        $eventInfo = $this->getEventInfo($binaryDataReader);
        $eventBuilder = new EventBuilder($binaryDataReader, $eventInfo, $this->cache);

        switch ($eventInfo->getType())
        {
            case EventTypeConst::TABLE_MAP_EVENT:
                $event = $eventBuilder->makeTableMap();
                break;
            case EventTypeConst::ROTATE_EVENT:
                $event = $eventBuilder->makeRotate();
                break;
            case EventTypeConst::GTID_LOG_EVENT:
                $event = $eventBuilder->makeGTIDLog();
                break;
            case EventTypeConst::HEARTBEAT_LOG_EVENT:
                $event = $eventBuilder->makeHeartbeat();
                break;
            case EventTypeConst::UPDATE_ROWS_EVENT_V1:
            case EventTypeConst::UPDATE_ROWS_EVENT_V2:
                $event = $eventBuilder->makeUpdateRecord();
                break;
            case EventTypeConst::WRITE_ROWS_EVENT_V1:
            case EventTypeConst::WRITE_ROWS_EVENT_V2:
                $event = $eventBuilder->makeInsertRecord();
                break;
            case EventTypeConst::DELETE_ROWS_EVENT_V1:
            case EventTypeConst::DELETE_ROWS_EVENT_V2:
                $event = $eventBuilder->makeDeleteRecord();
                break;
            case EventTypeConst::XID_EVENT:
                $event = $eventBuilder->makeXidRecord();
                break;
            case EventTypeConst::QUERY_EVENT:
                $event = $eventBuilder->makeQueryRecord();
                break;
            case EventTypeConst::FORMAT_DESCRIPTION_EVENT:
                $event = $eventBuilder->makeFormatDescriptionRecord();
                break;
        }

        if (isset($event))
        {
            // check for ignore and permitted events
            if (!Configure::checkEvent($event)) {
                return;
            }

            // event dispatch
            Event::dispatch($event);
        }
    }

    protected function getEventInfo(EventBinaryData $binaryDataReader): EventInfo
    {
        return new EventInfo(
            $binaryDataReader->readInt32(),
            $binaryDataReader->readUInt8(),
            $binaryDataReader->readInt32(),
            $binaryDataReader->readInt32(),
            $binaryDataReader->readInt32(),
            $binaryDataReader->readUInt16(),
            $this->client->isCheckSum,
            $this->client->getBinlogCurrent()
        );
    }

    /**
     * 获取当前进程id
     *
     * @return int
     */
    protected function getCurrentProcessID()
    {
        if (function_exists('getmypid')) {
            return getmypid();
        }
        if (function_exists('posix_getpid')) {
            return posix_getpid();
        }
        return 0;
    }

    /**
     * Save pid file.
     */
    protected function savePidFile()
    {
        /** @var PidManager $pidManager */
        $pidManager = $this->container->make(PidManager::class);
        $pidManager->write(self::getCurrentProcessID());
    }

    /**
     * Set process name.
     *
     * @param string $subProcessName
     *
     * @codeCoverageIgnore
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function setProcessName($subProcessName)
    {
        // MacOS doesn't support modifying process name.
        if (OS::is(OS::MAC_OS) || $this->isInTesting()) {
            return;
        }

        /** @var Config $config */
        $config = $this->container->make('config');
        $appName = $config->get('app.name', 'Laravel');
        $name = sprintf('%s: %s for %s', Configure::getProcessName(), $subProcessName, $appName);

        if (function_exists('cli_set_process_title')) {
            cli_set_process_title($name);
        } elseif (function_exists('swoole_set_process_name')) {
            swoole_set_process_name($name);
        } elseif (function_exists('setproctitle')) {
            setproctitle($name);
        }
    }

    /**
     * Indicates if it's in phpunit environment.
     *
     * @return bool
     */
    protected function isInTesting()
    {
        return defined('IN_PHPUNIT') && IN_PHPUNIT;
    }

}
