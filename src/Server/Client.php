<?php

namespace Telanflow\Binlog\Server;

use Swoole\Client as BaseClient;
use Telanflow\Binlog\Configure\Configure;
use Telanflow\Binlog\Constants\CapabilityFlagConst;
use Telanflow\Binlog\Constants\CharsetConst;
use Telanflow\Binlog\Constants\CommandTypeConst;
use Telanflow\Binlog\Exceptions\PacketCheckException;
use Telanflow\Binlog\Event\BinlogCurrent;

class Client extends BaseClient
{
    /**
     * http://dev.mysql.com/doc/internals/en/auth-phase-fast-path.html 00 FE
     */
    const DATA_MAX_LENGTH = 16777215;

    /**
     * @var array
     */
    public static $packageOkHeader = [0, 254];

    /**
     * @var BinlogCurrent
     */
    public $binlogCurrent;

    /**
     * @var bool
     */
    public $isCheckSum;

    public function __construct($type, $async = null)
    {
        parent::__construct($type, $async);
        $this->binlogCurrent = new BinlogCurrent();
    }

    /**
     * 发送包
     *
     * @param string $data
     * @param int $flag
     * @return bool
     */
    public function write($data, $flag = null)
    {
        return $this->send($data, $flag);
    }

    /**
     * 读取Mysql包内容
     *
     * @param bool $checkPacket
     * @return string
     * @throws PacketCheckException
     */
    public function read($checkPacket = true)
    {
        $header = $this->recv(4, self::MSG_WAITALL);
        if ('' === $header) {
            return '';
        }

        $dataLen = unpack('L', $header[0].$header[1].$header[2].chr(0))[1];
        $isMaxDataLength = $dataLen === self::DATA_MAX_LENGTH;

        $data = $this->recv($dataLen, self::MSG_WAITALL);
        if ($checkPacket) {
            self::check($data);
        }

        // https://dev.mysql.com/doc/internals/en/sending-more-than-16mbyte.html
        while ($isMaxDataLength)
        {
            $header = $this->recv(4, self::MSG_WAITALL);
            if ('' === $header) {
                return $data;
            }

            $dataLen = unpack('L', $header[0] . $header[1] . $header[2] . chr(0))[1];
            $isMaxDataLength = $dataLen === self::DATA_MAX_LENGTH;
            $nextData = $this->recv($dataLen, self::MSG_WAITALL);
            $data .= $nextData;
        }

        return $data;
    }

    /**
     * @return BinlogCurrent
     */
    public function getBinlogCurrent()
    {
        return $this->binlogCurrent;
    }

    /**
     * Mysql 认证流程
     * 1、Socket连接服务器
     * 2、读取服务器返回的信息，关键是获取到加盐信息，用于后面生成加密的password
     * 3、生成auth协议包
     * 4、发送协议包，认证完成
     *
     * @throws PacketCheckException
     */
    public function authenticate(): void
    {
        // 服务器信息
        $serverInfo = new ServerInfo(self::read(false));
        $salt = $serverInfo->salt;

        $data  = pack('L', self::getCapabilities());    // 4byte权能信息
        $data .= pack('L', self::DATA_MAX_LENGTH);      // 4byte最大长度
        $data .= chr(CharsetConst::UTF8_GENERAL_CI);    // 1byte字符编码

        for ($i = 0; $i < 23; ++$i) {
            $data .= chr(0);
        }

        $data .= Configure::getUsername() . chr(0);     // 用户名 0x00 以NULL结束
        $pwd = sha1(Configure::getPassword(), true) ^ sha1($salt . sha1(sha1(Configure::getPassword(), true), true), true); // 加密
        $data .= chr(strlen($pwd)) . $pwd;              // 密码信息 Length Coded Binary
        // Configure::getDatabase() && ($data .= Configure::getDatabase().chr(0));// 数据库名称 0x00 以NULL结束

        $str  = pack('L', strlen($data));
        $data = $str[0].$str[1].$str[2] . chr(1) . $data;

        $this->write($data);
        $this->read();
    }

    /**
     * @throws PacketCheckException
     */
    public function getBinlogStream(): void
    {
        $this->isCheckSum = boolval(Database::getGlobalCheckSum()); // CRC32
        if ($this->isCheckSum) {
            $this->execute('SET @master_binlog_checksum = @@global.binlog_checksum');
        }

        // 主从复制心跳
        // 设置复制心跳的周期，取值范围为 0 到 4294967秒
        // 精确度可以达到毫秒，最小的非0值是0.001秒
        $heartbeat = Configure::getHeartbeat();
        if (0 !== $heartbeat) {
            // master_heartbeat_period is in nanoseconds
            $this->execute('SET @master_heartbeat_period = ' . $heartbeat * 1000000000);
        }

        // COM_REGISTER_SLAVE
        $this->registerSlave();

        $this->setBinlogDump();
    }

    /**
     * @see https://dev.mysql.com/doc/internals/en/com-register-slave.html
     * @throws PacketCheckException
     */
    private function registerSlave(): void
    {
        $host = gethostname();
        $hostLength = strlen($host);
        $userLength = strlen(Configure::getUsername());
        $passLength = strlen(Configure::getPassword());

        $data = pack('l', 18 + $hostLength + $userLength + $passLength);
        $data .= chr(CommandTypeConst::COM_REGISTER_SLAVE);
        $data .= pack('V', Configure::getSlaveId());
        $data .= pack('C', $hostLength);
        $data .= $host;
        $data .= pack('C', $userLength);
        $data .= Configure::getUsername();
        $data .= pack('C', $passLength);
        $data .= Configure::getPassword();
        $data .= pack('v', Configure::getPort());
        $data .= pack('V', 0);
        $data .= pack('V', 0);

        $this->write($data);
        $this->read();
    }

    /**
     * @see https://dev.mysql.com/doc/internals/en/com-binlog-dump.html
     * @throws PacketCheckException
     */
    private function setBinlogDump(): void
    {
        $binFilePos = Configure::getBinlogPosition();
        $binFileName = Configure::getBinlogFileName();
        if (0 === $binFilePos && '' === $binFileName) {
            $arr = Database::getBinlogInfo();
            $binFileName = $arr['File'];
            $binFilePos = $arr['Position'];
        }

        $data = pack('i', strlen($binFileName) + 11) . chr(CommandTypeConst::COM_BINLOG_DUMP);
        $data .= pack('I', $binFilePos);
        $data .= pack('v', 0);
        $data .= pack('I', Configure::getSlaveId());
        $data .= $binFileName;

        $this->write($data);
        $this->read();

        $this->binlogCurrent->setBinlogPosition($binFilePos);
        $this->binlogCurrent->setBinlogFileName($binFileName);
    }

    /**
     * @see http://dev.mysql.com/doc/internals/en/capability-flags.html#packet-protocol::capabilityflags
     * @see https://github.com/siddontang/mixer/blob/master/doc/protocol.txt
     * @return int
     */
    private static function getCapabilities(): int
    {
        $flags = (
            CapabilityFlagConst::CLIENT_LONG_PASSWORD |
            CapabilityFlagConst::CLIENT_LONG_FLAG |
            CapabilityFlagConst::CLIENT_TRANSACTIONS |
            CapabilityFlagConst::CLIENT_SECURE_CONNECTION |
            CapabilityFlagConst::CLIENT_MULTI_RESULTS | /* needed for mysql_multi_query() */
            CapabilityFlagConst::CLIENT_PROTOCOL_41
        );

        // if (Configure::getDatabase()) {
        //     $flags |= CapabilityFlag::CLIENT_CONNECT_WITH_DB;
        // }

        return $flags;
    }

    /**
     * 数据包校验
     *
     * @param string $data
     * @throws PacketCheckException
     */
    public static function check(string $data)
    {
        $head = ord($data[0]);

        if (!in_array($head, self::$packageOkHeader, true))
        {
            $errorMessage = '';
            $errorCode = unpack('v', $data[1] . $data[2])[1];
            $packetLength = strlen($data);
            for ($i = 9; $i < $packetLength; ++$i) {
                $errorMessage .= $data[$i];
            }

            throw new PacketCheckException($errorMessage, $errorCode);
        }
    }

    /**
     * COM_QUERY封包，此命令最常用，常用于增删改查
     */
    public function execute(string $sql): string
    {
        $pack = pack('LC', strlen($sql) + 1, 3) . $sql;
        $this->write($pack);
        return $this->read();
    }

}
