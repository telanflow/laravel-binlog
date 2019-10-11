<?php

namespace Telanflow\Binlog\Server;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Telanflow\Binlog\Configure\Configure;
use Telanflow\Binlog\Constants\EventTypeConst;
use Telanflow\Binlog\Event\EventBinaryData;
use Telanflow\Binlog\Event\EventBuilder;
use Telanflow\Binlog\Event\EventInfo;
use Telanflow\Binlog\Exceptions\ConnectionException;
use Telanflow\Binlog\Helpers\OS;
use Telanflow\Binlog\Server\Facades\Client;

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
     * @var string
     */
    protected $framework;

    /**
     * @var Client
     */
    protected $client;

    public function __construct(Container $container, $framework)
    {
        $this->container = $container;
        $this->framework = $framework;
        $this->client = $this->container->make(Client::class);
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

        // Conn
        if(!$this->client->connect(Configure::getHost(), Configure::getPort(), 10)) {
            throw new ConnectionException($this->client->reuse, $this->client->errCode);
        }
        // Auth
        $this->client->authenticate();
        // RegisterSlave
        $this->client->getBinlogStream();

        while (1)
        {
            pcntl_signal_dispatch();

            $status = 0;
            $pid = pcntl_wait($status, WNOHANG);

            $this->consume();
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
                $this->client->close();
                file_put_contents(Configure::getPosFile(), $pidFileContent);
                exit(0);
        }
    }

    protected function consume(): void
    {
        $binaryDataReader = new EventBinaryData($this->client->read());

        // check EOF_Packet -> https://dev.mysql.com/doc/internals/en/packet-EOF_Packet.html
        if (self::EOF_HEADER_VALUE === $binaryDataReader->readUInt8()) {
            return;
        }

        // decode all events data
        $eventInfo = $this->getEventInfo($binaryDataReader);
        $eventBuilder = new EventBuilder($binaryDataReader, $eventInfo, Cache::store('array'));

        switch($eventInfo->getType())
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
        }

        // check for ignore and permitted events
        if (!Configure::checkEvent($event)) {
            return;
        }

        switch($eventInfo->getType())
        {
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

        // event dispatch
        if (isset($event)) {
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
     * @codeCoverageIgnore
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
