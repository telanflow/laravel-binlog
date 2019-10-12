<?php

/*
 * This file is part of the telanflow/binlog.
 *
 * (c) telanflow <telanflow@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Telanflow\Binlog\Constants\RecordTypeConst;

return [

    /*
    |--------------------------------------------------------------------------
    | Connection config
    |--------------------------------------------------------------------------
    */
    'connection' => [
        'host' => env('BINLOG_HOST', '127.0.0.1'),
        'port' => env('BINLOG_PORT', '3306'),
        'username' => env('BINLOG_USERNAME', 'forge'),
        'password' => env('BINLOG_PASSWORD', ''),

        // Binlog slave_id
        'slave_id' => env('BINLOG_SLAVE_ID', '1'),
        // Binlog heartbeat
        'heartbeat' => env('BINLOG_HEARTBEAT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Options
    |--------------------------------------------------------------------------
    */
    'options' => [
        // Process
        'process_name' => env('BINLOG_PROCESS_NAME', 'binlog'),
        'pid_file' => env('BINLOG_PID_FILE', base_path('storage/logs/binlog.pid')),
        'log_file' => env('BINLOG_LOG_FILE', base_path('storage/logs/binlog.log')),

        // Cache the binlog read location and the binlog file name.
        // Continue reading from current position after reboot
        'pos_file' => env('BINLOG_POS_FILE', base_path('storage/logs/binlog.pos')),

        // Daemon process
        'daemon' => env('BINLOG_DAEMON', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Specifies to listen to databases, tables
    |
    | （If not specified, listen to all databases, tables）
    |--------------------------------------------------------------------------
    */
    'listen' => [
        // Specifies the listening table
        // 'database' => [
        //     'table',
        //     'table',
        // ],

        // Specify listening database
        // 'database'
    ],

    /*
    |--------------------------------------------------------------------------
    | Specify listening events
    |--------------------------------------------------------------------------
    */
    'listen_event' => [
        RecordTypeConst::INSERT,
        RecordTypeConst::UPDATE,
        RecordTypeConst::DELETE,
        RecordTypeConst::BEGIN,
        RecordTypeConst::ROLLBACK,
        // RecordTypeConst::FORMAT_DESCRIPTION,
        // RecordTypeConst::TABLE_MAP,
        // RecordTypeConst::HEARTBEAT,
        // RecordTypeConst::GTID,
        // RecordTypeConst::QUERY,
    ],

];
