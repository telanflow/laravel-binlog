<?php

namespace Telanflow\Binlog\Event;

use JsonSerializable;

class BinlogCurrent implements JsonSerializable
{
    /**
     * @var int
     */
    private $binlogPosition = 0;

    /**
     * @var string
     */
    private $binlogFileName = '';

    /**
     * @var string
     */
    private $gtid;

    public function getBinlogPosition(): int
    {
        return $this->binlogPosition;
    }

    public function setBinlogPosition($binlogPosition): void
    {
        $this->binlogPosition = intval($binlogPosition);
    }

    public function getBinlogFileName(): string
    {
        return $this->binlogFileName;
    }

    public function setBinlogFileName($binlogFileName): void
    {
        $this->binlogFileName = strval($binlogFileName);
    }

    public function getGtid(): string
    {
        return $this->gtid;
    }

    public function setGtid($gtid): void
    {
        $this->gtid = strval($gtid);
    }

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
