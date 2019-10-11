<?php

namespace Telanflow\Binlog\Event\Record;

use Telanflow\Binlog\Constants\RecordTypeConst;
use Telanflow\Binlog\Event\EventInfo;
use Telanflow\Binlog\Event\EventRecord;

class GTID extends EventRecord
{
    /**
     * @var int
     */
    protected $recordType = RecordTypeConst::GTID;

    /**
     * @var bool
     */
    protected $commit;

    /**
     * @var string
     */
    protected $gtid;

    public function __construct(EventInfo $eventInfo, bool $commit)
    {
        parent::__construct($eventInfo);
        $this->commit = $commit;
    }

    public function getData()
    {
        return $this->eventInfo->getBinlogCurrent()->getGtid();
    }

    public function isCommit(): bool
    {
        return $this->commit;
    }

}
