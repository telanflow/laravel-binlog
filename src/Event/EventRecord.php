<?php

namespace Telanflow\Binlog\Event;

use Telanflow\Binlog\Constants\RecordTypeConst;
use Telanflow\Binlog\Contracts\EventInterface;

abstract class EventRecord implements EventInterface
{
    /**
     * @var EventInfo
     */
    protected $eventInfo;

    /**
     * @var int
     */
    protected $recordType = RecordTypeConst::UNKNOWN;

    /**
     * EventDTO constructor.
     * @param EventInfo $eventInfo
     */
    public function __construct(EventInfo $eventInfo)
    {
        $this->eventInfo = $eventInfo;
    }

    public function getBinlogType(): int
    {
        return $this->eventInfo->getType();
    }

    public function getBinlogPosition(): int
    {
        return $this->eventInfo->getPos();
    }

    public function getBinlogFileName(): string
    {
        return $this->eventInfo->getBinlogCurrent()->getBinlogFileName();
    }

    public function getBinlogTimestamp(): int
    {
        return $this->eventInfo->getTimestamp();
    }

    public function getRecordType(): int
    {
        return $this->recordType;
    }

    public function getRecordTypeName(): string
    {
        return RecordTypeConst::getRecordTypeName($this->recordType);
    }

    public function getTableId(): string
    {
        return '';
    }

    public function getTableName(): string
    {
        return '';
    }

    public function getDatabase(): string
    {
        return '';
    }

    public function getPrimaryKey(): string
    {
        return '';
    }

    public function getData()
    {
        return null;
    }

    public function __toString(): string
    {
        return sprintf('RecordType: %s', $this->getRecordTypeName());
    }

 }
