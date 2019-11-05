<?php

namespace Telanflow\Binlog\Contracts;

use Telanflow\Binlog\Constants\RecordTypeConst;

interface RecordInterface
{
    /**
     * @see RecordTypeConst
     * @return int
     */
    public function getRecordType(): int;
    public function getRecordTypeName(): string;
    public function getTableId(): string;
    public function getTableName(): string;
    public function getDatabase(): string;
    public function getPrimaryKey(): string;
    public function getData();
}
