<?php

namespace Telanflow\Binlog\Server;

use Illuminate\Support\Collection;
use Telanflow\Binlog\Configure\Configure;
use Telanflow\Binlog\DTO\FieldDTOCollection;

class Database
{

    /**
     * Check binlog opened
     *
     * @return bool
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function isOpenBinlog()
    {
        $row = Configure::getDbConnection()->fetchAssoc('select @@sql_log_bin');

        return isset($row['@@sql_log_bin']) && $row['@@sql_log_bin'] == 1;
    }

    /**
     * Get binlog format
     *
     * @return string
     */
    public static function getBinlogFormat()
    {
        $row = Configure::getDbConnection()->fetchAssoc('select @@binlog_format');

        return strtolower($row['@@binlog_format']);
    }

    /**
     * Global binlog checksum
     *
     * @return string
     */
    public static function getGlobalCheckSum()
    {
        $row = Configure::getDbConnection()->fetchAssoc("SHOW GLOBAL VARIABLES LIKE 'BINLOG_CHECKSUM'");

        return $row['Value'];
    }

    /**
     * 获取Binlog状态信息
     *
     * @return array
     */
    public static function getBinlogInfo()
    {
        $row = Configure::getDbConnection()->fetchAssoc('show master status');
        return [
            'File' => $row['File'],
            'Position' => $row['Position'],
        ];
    }

    /**
     * 获取表结构字段
     *
     * @param string $schema
     * @param string $table
     * @return Collection
     */
    public static function getTableFields($schema, $table)
    {
        $sql = '
             SELECT
                `COLUMN_NAME`,
                `COLLATION_NAME`,
                `CHARACTER_SET_NAME`,
                `COLUMN_COMMENT`,
                `COLUMN_TYPE`,
                `COLUMN_KEY`
            FROM
                `information_schema`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = ? AND `TABLE_NAME` = ?
            ORDER BY ORDINAL_POSITION';

        return FieldDTOCollection::makeFromArray(Configure::getDbConnection()->fetchAll($sql, [$schema, $table]));
    }

}
