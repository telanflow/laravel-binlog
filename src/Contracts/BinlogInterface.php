<?php

namespace Telanflow\Binlog\Contracts;

interface BinlogInterface
{
    public function getBinlogType(): int;
    public function getBinlogPosition(): int;
    public function getBinlogFileName(): string;
    public function getBinlogTimestamp(): int;
}
