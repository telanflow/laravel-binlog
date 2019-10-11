<?php

namespace Telanflow\Binlog\Server;

use Illuminate\Support\Facades\DB;
use Telanflow\Binlog\DTO\FieldDTOCollection;

class Database
{

    /**
     * Check binlog opened
     *
     * @return bool
     */
    public static function isOpenBinlog()
    {
        return DB::selectOne('select @@sql_log_bin')->{'@@sql_log_bin'} == 1;
    }

    /**
     * Get binlog format
     *
     * @return string
     */
    public static function getBinlogFormat()
    {
        $format = DB::selectOne('select @@binlog_format')->{'@@binlog_format'};
        return strtolower($format);
    }

    /**
     * Global binlog checksum
     *
     * @return string
     */
    public static function getGlobalCheckSum()
    {
        return DB::selectOne("SHOW GLOBAL VARIABLES LIKE 'BINLOG_CHECKSUM'")->Value;
    }

    /**
     * 获取Binlog状态信息
     *
     * @return array
     */
    public static function getBinlogInfo()
    {
        $row = DB::selectOne('show master status');
        return [
            'File' => $row->File,
            'Position' => $row->Position,
        ];
    }

    /**
     * 获取表结构字段
     *
     * @param string $schema
     * @param string $table
     * @return \Illuminate\Support\Collection
     */
    public static function getTableFields($schema, $table)
    {
        $fields = [
            'COLUMN_NAME',
            'COLLATION_NAME',
            'CHARACTER_SET_NAME',
            'COLUMN_COMMENT',
            'COLUMN_TYPE',
            'COLUMN_KEY',
        ];
        $where = [
            'table_schema' => $schema,
            'table_name' => $table,
        ];

        $list = DB::table('information_schema.columns')->select($fields)->where($where)->get()->toArray();
        return FieldDTOCollection::makeFromArray($list);
    }

}
