<?php

namespace Telanflow\Binlog\Event\Record;

use Telanflow\Binlog\Constants\RecordTypeConst;
use Telanflow\Binlog\Event\EventInfo;
use Telanflow\Binlog\Event\EventRecord;

class Query extends EventRecord
{
    /**
     * @var int
     */
    protected $recordType = RecordTypeConst::QUERY;

    /**
     * @var string
     */
    protected $database;

    /**
     * @var int
     */
    protected $executionTime;

    /**
     * @var string
     */
    protected $query;

    public function __construct(EventInfo $eventInfo, string $database, int $executionTime, string $query)
    {
        parent::__construct($eventInfo);
        $this->database = $database;
        $this->executionTime = $executionTime;
        $this->query = $query;
    }

    public function getDatabase(): string
    {
        return $this->database;
    }

    public function getExecutionTime(): int
    {
        return $this->executionTime;
    }

    public function getData()
    {
        return $this->query;
    }

}
