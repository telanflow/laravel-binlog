<?php

namespace Telanflow\Binlog\Event\Record;

use Telanflow\Binlog\Constants\RecordTypeConst;
use Telanflow\Binlog\Event\EventInfo;
use Telanflow\Binlog\DTO\TableMapDTO;
use Telanflow\Binlog\Event\EventRecord;

class Update extends EventRecord
{
    /**
     * @var int
     */
    protected $recordType = RecordTypeConst::UPDATE;

    /**
     * @var TableMapDTO
     */
    protected $tableMapDTO;

    /**
     * @var array
     */
    protected $changeValue;

    public function __construct(EventInfo $eventInfo, TableMapDTO $tableMapDTO, array $changeValue)
    {
        parent::__construct($eventInfo);
        $this->tableMapDTO = $tableMapDTO;
        $this->changeValue = $changeValue;
    }

    public function getTableId(): string
    {
        return $this->tableMapDTO->getTableId();
    }

    public function getTableName(): string
    {
        return $this->tableMapDTO->getTableName();
    }

    public function getDatabase(): string
    {
        return $this->tableMapDTO->getDatabase();
    }

    public function getPrimaryKey(): string
    {
        return $this->tableMapDTO->getPrimaryKey();
    }

    public function getData()
    {
        return $this->changeValue;
    }

    public function __toString(): string
    {
        return sprintf('RecordType: %s  Database: %s  Table: %s', $this->getRecordTypeName(), $this->getDatabase(), $this->getTableName());
    }

}
