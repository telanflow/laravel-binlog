<?php

namespace Telanflow\Binlog\Configure;

use Telanflow\Binlog\Contracts\EventInterface;

class Configure
{
    protected static $host = '';
    protected static $port = '';
    protected static $username = '';
    protected static $password = '';
    protected static $slaveId = '';
    protected static $heartbeat = 5;
    protected static $daemon = false;
    protected static $processName = 'binlog';
    protected static $pidFile = '';
    protected static $posFile = '';
    protected static $logFile = '';
    protected static $binlogPosition = 0;
    protected static $binlogFileName = '';
    protected static $listen;
    protected static $listenEvent;

    /**
     * @param array $config
     */
    public static function parse(array $config)
    {
        // conn
        self::$host = trim($config['connection']['host']);
        self::$port = trim($config['connection']['port']);
        self::$username = strval($config['connection']['username']);
        self::$password = strval($config['connection']['password']);
        self::$slaveId = trim($config['connection']['slave_id']);
        self::$heartbeat = intval($config['connection']['heartbeat']);

        self::$listen = !empty($config['listen']) ? $config['listen'] : null;
        self::$listenEvent = !empty($config['listen_event']) ? $config['listen_event'] : null;

        // options
        self::$daemon = boolval($config['options']['daemon']);
        self::$processName = trim($config['options']['process_name']);
        self::$pidFile = trim($config['options']['pid_file']);
        self::$posFile = trim($config['options']['pos_file']);
        self::$logFile = trim($config['options']['log_file']);

        // resolve pos file
        if (self::$posFile && is_readable(self::$posFile))
        {
            $arr = json_decode(file_get_contents(self::$posFile), true);
            if ($arr && is_array($arr)) {
                self::$binlogPosition = $arr['binlogPosition'] ?? 0;
                self::$binlogFileName = $arr['binlogFileName'] ?? '';
            }
        }
    }

    /**
     * @return string
     */
    public static function getHost(): string
    {
        return self::$host;
    }

    /**
     * @param string $host
     */
    public static function setHost(string $host): void
    {
        self::$host = $host;
    }

    /**
     * @return string
     */
    public static function getPort(): string
    {
        return self::$port;
    }

    /**
     * @param string|int $port
     */
    public static function setPort($port): void
    {
        self::$port = strval($port);
    }

    /**
     * @return string
     */
    public static function getUsername(): string
    {
        return self::$username;
    }

    /**
     * @param string $username
     */
    public static function setUsername(string $username): void
    {
        self::$username = $username;
    }

    /**
     * @return string
     */
    public static function getPassword(): string
    {
        return self::$password;
    }

    /**
     * @param string $password
     */
    public static function setPassword(string $password): void
    {
        self::$password = $password;
    }

    /**
     * @return string
     */
    public static function getSlaveId(): string
    {
        return self::$slaveId;
    }

    /**
     * @param string $slaveId
     */
    public static function setSlaveId(string $slaveId): void
    {
        self::$slaveId = $slaveId;
    }

    /**
     * @return int
     */
    public static function getHeartbeat(): int
    {
        return self::$heartbeat;
    }

    /**
     * @param int $heartbeat
     */
    public static function setHeartbeat(int $heartbeat): void
    {
        self::$heartbeat = $heartbeat;
    }

    /**
     * @return int
     */
    public static function getBinlogPosition(): int
    {
        return self::$binlogPosition;
    }

    /**
     * @param int $binlogPosition
     */
    public static function setBinlogPosition($binlogPosition): void
    {
        self::$binlogPosition = $binlogPosition;
    }

    /**
     * @return string|null
     */
    public static function getBinlogFileName(): ?string
    {
        return self::$binlogFileName;
    }

    /**
     * @param string $binlogFileName
     */
    public static function setBinlogFileName(string $binlogFileName): void
    {
        self::$binlogFileName = $binlogFileName;
    }

    /**
     * @return bool
     */
    public static function isDaemon(): bool
    {
        return self::$daemon;
    }

    /**
     * @param bool $daemon
     */
    public static function setDaemon(bool $daemon): void
    {
        self::$daemon = $daemon;
    }

    /**
     * @return string
     */
    public static function getProcessName(): string
    {
        return self::$processName;
    }

    /**
     * @param string $processName
     */
    public static function setProcessName(string $processName): void
    {
        self::$processName = $processName;
    }

    /**
     * @return string
     */
    public static function getPidFile(): string
    {
        return self::$pidFile;
    }

    /**
     * @param string $pidFile
     */
    public static function setPidFile(string $pidFile): void
    {
        self::$pidFile = $pidFile;
    }

    /**
     * @return string
     */
    public static function getPosFile(): string
    {
        return self::$posFile;
    }

    /**
     * @param string $posFile
     */
    public static function setPosFile(string $posFile): void
    {
        self::$posFile = $posFile;
    }

    /**
     * @return string
     */
    public static function getLogFile(): string
    {
        return self::$logFile;
    }

    /**
     * @param string $logFile
     */
    public static function setLogFile(string $logFile): void
    {
        self::$logFile = $logFile;
    }

    /**
     * @param EventInterface $event
     * @return bool
     */
    public static function checkEvent(EventInterface $event)
    {
        if (empty(self::$listenEvent)) {
            return false;
        }

        if (!in_array($event->getRecordType(), self::$listenEvent)) {
            return false;
        }

        if (empty(self::$listen)) {
            return true;
        }

        $ret = false;
        $database = $event->getDatabase();
        $table = $event->getTableName();
        foreach(self::$listen as $k => $v)
        {
            if (is_string($v) && $v === $database) {
                $ret = true;
                break;
            }

            if (is_array($v))
            {
                if ($k === $database && in_array($table, $v))
                {
                    $ret = true;
                    break;
                }
            }
        }

        return $ret;
    }

}
