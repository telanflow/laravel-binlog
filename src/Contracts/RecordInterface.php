<?php

namespace Telanflow\Binlog\Contracts;

interface RecordInterface
{
    public function getRecordType(): int;
    public function getRecordTypeName(): string;
    public function getTableId(): string;
    public function getTableName(): string;
    public function getDatabase(): string;
    public function getPrimaryKey(): string;
    public function getData();
}
