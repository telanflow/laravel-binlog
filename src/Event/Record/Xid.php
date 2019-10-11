<?php

namespace Telanflow\Binlog\Event\Record;

use Telanflow\Binlog\Constants\RecordTypeConst;
use Telanflow\Binlog\Event\EventInfo;
use Telanflow\Binlog\Event\EventRecord;

class Xid extends EventRecord
{
    /**
     * @var int
     */
    protected $recordType = RecordTypeConst::XID;

    /**
     * @var string
     */
    protected $xid;

    public function __construct(EventInfo $eventInfo, string $xid)
    {
        parent::__construct($eventInfo);
        $this->xid = $xid;
    }

    public function getData()
    {
        return $this->xid;
    }

}
