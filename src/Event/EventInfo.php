<?php

namespace Telanflow\Binlog\Event;

use JsonSerializable;

class EventInfo implements JsonSerializable
{
    /**
     * @var int
     */
    private $timestamp;

    /**
     * @var int
     */
    private $type;

    /**
     * @var int
     */
    private $id;

    /**
     * @var int
     */
    private $size;

    /**
     * @var int
     */
    private $pos;

    /**
     * @var int
     */
    private $flag;

    /**
     * @var bool
     */
    private $checkSum;

    /**
     * @var int
     */
    private $sizeNoHeader;

    /**
     * @var string
     */
    private $dateTime;

    /**
     * @var BinlogCurrent
     */
    private $binlogCurrent;

    public function __construct(
        int $timestamp,
        int $type,
        int $id,
        int $size,
        int $pos,
        int $flag,
        bool $checkSum,
        BinlogCurrent $binlogCurrent
    ) {
        $this->timestamp = $timestamp;
        $this->type = $type;
        $this->id = $id;
        $this->size = $size;
        $this->pos = $pos;
        $this->flag = $flag;
        $this->checkSum = $checkSum;
        $this->binlogCurrent = $binlogCurrent;

        if ($pos > 0) {
            $this->binlogCurrent->setBinlogPosition($pos);
        }
    }

    public function getBinlogCurrent(): BinlogCurrent
    {
        return $this->binlogCurrent;
    }

    public function getDateTime(): string
    {
        if (empty($this->dateTime)) {
            $this->dateTime = date('c', $this->timestamp);
        }

        return $this->dateTime;
    }

    public function getSizeNoHeader(): int
    {
        if (empty($this->sizeNoHeader)) {
            $this->sizeNoHeader = (true === $this->checkSum ? $this->size - 23 : $this->size - 19);
        }

        return $this->sizeNoHeader;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getId(): int
    {
        return $this->id;
    }


    public function getSize(): int
    {
        return $this->size;
    }

    public function getPos(): int
    {
        return $this->pos;
    }

    public function getFlag(): int
    {
        return $this->flag;
    }

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
