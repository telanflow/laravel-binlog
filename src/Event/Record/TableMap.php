<?php

namespace Telanflow\Binlog\Event\Record;

use Telanflow\Binlog\Constants\RecordTypeConst;
use Telanflow\Binlog\Event\EventInfo;
use Telanflow\Binlog\DTO\TableMapDTO;
use Telanflow\Binlog\Event\EventRecord;

class TableMap extends EventRecord
{
    /**
     * @var int
     */
    protected $recordType = RecordTypeConst::TABLE_MAP;

    /**
     * @var TableMapDTO
     */
    protected $tableMapDTO;

    public function __construct(EventInfo $eventInfo, TableMapDTO $tableMapDTO)
    {
        parent::__construct($eventInfo);
        $this->tableMapDTO = $tableMapDTO;
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

}
