<?php

namespace Telanflow\Binlog\Constants;

class RecordTypeConst
{
    const INSERT = 1;
    const UPDATE = 2;
    const DELETE = 3;
    const BEGIN = 4;
    const COMMIT = 5;
    const ROLLBACK = 6;
    const TABLE_MAP = 7;
    const HEARTBEAT = 8;
    const ROTATE = 9;
    const GTID = 10;
    const XID = 11;
    const FORMAT_DESCRIPTION = 12;
    const QUERY = 13;
    const UNKNOWN = 14;

    // 变更类型名称
    public static $recordTypeName = [
        self::INSERT    => 'insert',
        self::UPDATE    => 'update',
        self::DELETE    => 'delete',
        self::BEGIN     => 'begin',
        self::COMMIT    => 'commit',
        self::ROLLBACK  => 'rollback',
        self::TABLE_MAP => 'table_map',
        self::HEARTBEAT => 'heartbeat',
        self::ROTATE    => 'rotate',
        self::GTID      => 'gtid',
        self::XID       => 'xid',
        self::QUERY     => 'query',
        self::UNKNOWN   => 'unknown',
        self::FORMAT_DESCRIPTION => 'format_description',
    ];

    /**
     * 变更类型名称
     *
     * @param int $recordType
     * @return string
     */
    public static function getRecordTypeName($recordType)
    {
        $recordType = intval($recordType);
        return isset(self::$recordTypeName[$recordType]) ? self::$recordTypeName[$recordType] : '';
    }

}
