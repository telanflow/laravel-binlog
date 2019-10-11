<?php

namespace Telanflow\Binlog\Server;

/**
 * @document https://mariadb.com/kb/en/library/1-connecting-connecting/#initial-handshake-packet
 *
 * int<1> protocol version
 * string<NUL> server version (MariaDB server version is by default prefixed by "5.5.5-")
 * int<4> connection id
 * string<8> scramble 1st part (authentication seed)
 * string<1> reserved byte
 * int<2> server capabilities (1st part)
 * int<1> server default collation
 * int<2> status flags
 * int<2> server capabilities (2nd part)
 * int<1> length of scramble's 2nd part
 * if (server_capabilities & PLUGIN_AUTH)
 * int<1> plugin data length
 * else
 * int<1> 0x00
 * string<6> filler
 * if (server_capabilities & CLIENT_MYSQL)
 * string<4> filler
 * else
 * int<4> server capabilities 3rd part . MariaDB specific flags /-- MariaDB 10.2 or later
 * if (server_capabilities & CLIENT_SECURE_CONNECTION)
 * string<n> scramble 2nd part . Length = max(12, plugin data length - 9)
 * string<1> reserved byte
 * if (server_capabilities & PLUGIN_AUTH)
 * string<NUL> authentication plugin name
 */
class ServerInfo
{
    const CLIENT_PLUGIN_AUTH =  (1 << 19);

    /**
     * 服务协议版本号：该值由 PROTOCOL_VERSION
     * 宏定义决定（参考MySQL源代码/include/mysql_version.h头文件定义）
     * mysql-server/config.h.cmake 399
     *
     * @var int|string
     */
    public $protocolVersion = '';

    /**
     * mysql-server/include/mysql_version.h.in 12行
     * mysql-server/cmake/mysql_version.cmake 59行
     *
     * @var string $server_info
     */
    public $serverInfo = '';

    /**
     * 字符编码 1byte
     *
     * @var int|string
     */
    public $characterSet = '';

    /**
     * 加盐信息 用于握手认证  8 byte
     *
     * @var string
     */
    public $salt = '';

    /**
     * 线程id  4 bytes
     *
     * @var int
     */
    public $threadId = 0;

    /**
     * mysql-server/sql/auth/sql_authentication.cc 567行
     *
     * @var bool|string
     */
    public $authPluginName = '';

    /**
     * mysql-server/include/mysql_com.h 204 238
     * mysql初始化的权能信息为 CapabilityFlag::CLIENT_BASIC_FLAGS
     *
     * @var string
     */
    public $capabilityFlag;

    /**
     * 服务器状态  2 byte
     *
     * @var int
     */
    public $serverStatus;

    public function __construct($pack)
    {
        $offset = 0;
        $length = strlen($pack);

        // 协议版本号 1 byte
        // int<1> protocol version
        $this->protocolVersion = ord($pack[$offset]);

        // 服务器版本信息 以null(0x00)结束
        // string<NUL> server version (MariaDB server version is by default prefixed by "5.5.5-")
        while ($pack[$offset++] !== chr(0x00)) {
            $this->serverInfo .= $pack[$offset];
        }

        // 线程id  4 byte
        // int<4> connection id
        $this->threadId = unpack('V', substr($pack, $offset, 4))[1];
        $offset += 4;

        // 加盐信息 用于握手认证  8 byte
        // string<8> scramble 1st part (authentication seed)
        $this->salt .= substr($pack, $offset, 8);
        $offset = $offset + 8;

        // 填充值 -- 0x00  1byte
        // string<1> reserved byte 1byte保留值
        $offset++;

        // 低位服务器权能信息  2byte
        // int<2> server capabilities (1st part)
        $this->capabilityFlag = $pack[$offset]. $pack[$offset+1];
        $offset = $offset + 2;

        // 字符编码 1byte
        // int<1> server default collation
        $this->characterSet = ord($pack[$offset]);
        $offset++;

        // 服务器状态  2 byte
        // int<2> status flags
        // SERVER_STATUS_AUTOCOMMIT == 2
        $this->serverStatus = unpack('v', $pack[$offset].$pack[$offset+1])[1];
        $offset += 2;

        // 服务器权能标志 高16位
        // int<2> server capabilities (2nd part)
        $this->capabilityFlag = unpack('V', $this->capabilityFlag.$pack[$offset].$pack[$offset+1])[1];
        $offset += 2;

        // 加盐长度 1byte
        // int<1> length of scramble's 2nd part
        $salt_len = ord($pack[$offset]);
        $offset++;

        // mysql-server/sql/auth/sql_authentication.cc 2696 native_password_authenticate
        $salt_len = max(12, $salt_len - 9);

        // 10byte 填充值 0x00
        $offset += 10;

        // if (server_capabilities & CLIENT_SECURE_CONNECTION)
        // string<n> scramble 2nd part . Length = max(12, plugin data length - 9)
        // 第二部分加盐信息，至少12字符
        $this->salt .= substr($pack, $offset, $salt_len);
        $offset += $salt_len;

        // string<1> reserved byte
        $offset += 1;

        // if (server_capabilities & PLUGIN_AUTH)
        // string<NUL> authentication plugin name
        // $length - 1 去除null字符
        $len = $length-$offset-1;
        if ($len > 0) {
            $this->authPluginName = substr($pack,$offset, $len);
        }
    }

}
