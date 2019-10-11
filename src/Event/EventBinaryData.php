<?php

namespace Telanflow\Binlog\Event;

use Telanflow\Binlog\Exceptions\EventBinaryDataException;

class EventBinaryData
{
    const NULL_COLUMN = 251;
    const UNSIGNED_CHAR_COLUMN = 251;
    const UNSIGNED_SHORT_COLUMN = 252;
    const UNSIGNED_INT24_COLUMN = 253;
    const UNSIGNED_INT64_COLUMN = 254;
    const UNSIGNED_CHAR_LENGTH = 1;
    const UNSIGNED_SHORT_LENGTH = 2;
    const UNSIGNED_INT24_LENGTH = 3;
    const UNSIGNED_INT32_LENGTH = 4;
    const UNSIGNED_FLOAT_LENGTH = 4;
    const UNSIGNED_DOUBLE_LENGTH = 8;
    const UNSIGNED_INT40_LENGTH = 5;
    const UNSIGNED_INT48_LENGTH = 6;
    const UNSIGNED_INT56_LENGTH = 7;
    const UNSIGNED_INT64_LENGTH = 8;

    /**
     * @var string
     */
    private $data;

    /**
     * @var int
     */
    private $offset = 0;

    public function __construct(string $data)
    {
        $this->data = $data;
    }

    public function read(int $len): string
    {
        $data = substr($this->data, 0, $len);
        $this->offset += $len;
        $this->data = substr($this->data, $len);
        return $data;
    }

    public function unread(string $data): void
    {
        $this->offset -= strlen($data);
        $this->data = $data . $this->data;
    }

    public function advance(int $len): void
    {
        $this->offset += $len;
        $this->data = substr($this->data, $len);
    }

    public function readUInt8(): int
    {
        return unpack('C', $this->read(self::UNSIGNED_CHAR_LENGTH))[1];
    }

    public function readUInt16(): int
    {
        return unpack('v', $this->read(self::UNSIGNED_SHORT_LENGTH))[1];
    }

    public function readUInt24(): int
    {
        $data = unpack('C3', $this->read(self::UNSIGNED_INT24_LENGTH));
        return $data[1] + ($data[2] << 8) + ($data[3] << 16);
    }

    public function readUInt32(): int
    {
        return unpack('I', $this->read(self::UNSIGNED_INT32_LENGTH))[1];
    }

    public function readUInt40(): int
    {
        $data = unpack('CI', $this->read(self::UNSIGNED_INT40_LENGTH));
        return $data[1] + ($data[2] << 8);
    }

    public function readUInt48(): int
    {
        $data = unpack('vvv', $this->read(self::UNSIGNED_INT48_LENGTH));
        return $data[1] + ($data[2] << 16) + ($data[3] << 32);
    }

    public function readUInt56(): int
    {
        $data = unpack('CSI', $this->read(self::UNSIGNED_INT56_LENGTH));
        return $data[1] + ($data[2] << 8) + ($data[3] << 24);
    }

    public function readUInt64(): string
    {
        return $this->unpackUInt64($this->read(self::UNSIGNED_INT64_LENGTH));
    }

    public function unpackUInt64(string $binary): string
    {
        $data = unpack('V*', $binary);
        return bcadd($data[1], bcmul($data[2], bcpow(2, 32)));
    }

    public function readInt8(): int
    {
        return unpack('c', $this->read(self::UNSIGNED_CHAR_LENGTH))[1];
    }

    public function readInt16(): int
    {
        return unpack('s', $this->read(self::UNSIGNED_SHORT_LENGTH))[1];
    }

    public function readInt16Be(): int
    {
        return unpack('n', $this->read(self::UNSIGNED_SHORT_LENGTH))[1];
    }

    public function readInt24(): int
    {
        $data = unpack('C3', $this->read(self::UNSIGNED_INT24_LENGTH));
        $res = $data[1] | ($data[2] << 8) | ($data[3] << 16);
        if ($res >= 0x800000) {
            $res -= 0x1000000;
        }
        return $res;
    }

    public function readInt24Be(): int
    {
        $data = unpack('C3', $this->read(self::UNSIGNED_INT24_LENGTH));
        $res = ($data[1] << 16) | ($data[2] << 8) | $data[3];
        if ($res >= 0x800000) {
            $res -= 0x1000000;
        }
        return $res;
    }

    public function readInt32(): int
    {
        return unpack('i', $this->read(self::UNSIGNED_INT32_LENGTH))[1];
    }

    public function readInt32Be(): int
    {
        return unpack('i', strrev($this->read(self::UNSIGNED_INT32_LENGTH)))[1];
    }

    public function readInt40Be(): int
    {
        $data1 = unpack('N', $this->read(self::UNSIGNED_INT32_LENGTH))[1];
        $data2 = unpack('C', $this->read(self::UNSIGNED_CHAR_LENGTH))[1];
        return $data2 + ($data1 << 8);
    }

    public function readInt64(): string
    {
        $data = unpack('V*', $this->read(self::UNSIGNED_INT64_LENGTH));
        return bcadd((string)$data[1], (string)($data[2] << 32));
    }

    public function readFloat(): float
    {
        return unpack('f', $this->read(self::UNSIGNED_FLOAT_LENGTH))[1];
    }

    public function readDouble(): float
    {
        return unpack('d', $this->read(self::UNSIGNED_DOUBLE_LENGTH))[1];
    }

    public function readTableId(): string
    {
        return $this->unpackUInt64($this->read(self::UNSIGNED_INT48_LENGTH) . chr(0) . chr(0));
    }

    public function unpackUInt16($data): int
    {
        return unpack('S', $data[0].$data[1])[1];
    }

    public function unpackInt24($data): int
    {
        $a  = (int)(ord($data[0]) & 0xFF);
        $a += (int)((ord($data[1]) & 0xFF) << 8);
        $a += (int)((ord($data[2]) & 0xFF) << 16);
        return $a;
    }

    /**
     * @param string|array $data
     * @return int
     */
    public function unpackInt64($data): int
    {
        $a  = (int)(ord($data[0]) & 0xFF);
        $a += (int)((ord($data[1]) & 0xFF) << 8);
        $a += (int)((ord($data[2]) & 0xFF) << 16);
        $a += (int)((ord($data[3]) & 0xFF) << 24);
        $a += (int)((ord($data[4]) & 0xFF) << 32);
        $a += (int)((ord($data[5]) & 0xFF) << 40);
        $a += (int)((ord($data[6]) & 0xFF) << 48);
        $a += (int)((ord($data[7]) & 0xFF) << 56);
        return $a;
    }

    /**
     * @param int $size
     * @return bool
     */
    public function isComplete(int $size): bool
    {
        return !($this->offset - 20 < $size);
    }

    /**
     * @return int
     */
    public function getBinaryDataLength(): int
    {
        return strlen($this->data);
    }

    /**
     * @param int $size
     * @return string
     * @throws EventBinaryDataException
     */
    public function readLengthString(int $size): string
    {
        return $this->read($this->readUIntBySize($size));
    }

    /**
     * @param int $size
     * @return int|string
     * @throws EventBinaryDataException
     */
    public function readUIntBySize($size)
    {
        switch($size)
        {
            case self::UNSIGNED_CHAR_LENGTH: return $this->readUInt8();
            case self::UNSIGNED_SHORT_LENGTH: return $this->readUInt16();
            case self::UNSIGNED_INT24_LENGTH: return $this->readUInt24();
            case self::UNSIGNED_INT32_LENGTH: return $this->readUInt32();
            case self::UNSIGNED_INT40_LENGTH: return $this->readUInt40();
            case self::UNSIGNED_INT48_LENGTH: return $this->readUInt48();
            case self::UNSIGNED_INT56_LENGTH: return $this->readUInt56();
            case self::UNSIGNED_INT64_LENGTH: return $this->readUInt64();
        }

        throw new EventBinaryDataException('$size ' . $size . ' not handled');
    }

    /**
     * @return int|null
     * @throws EventBinaryDataException
     */
    public function readCodedBinary(): ?int
    {
        $c = ord($this->read(self::UNSIGNED_CHAR_LENGTH));
        if ($c === self::NULL_COLUMN) {
            return null;
        }
        if ($c < self::UNSIGNED_CHAR_COLUMN) {
            return $c;
        }
        if ($c === self::UNSIGNED_SHORT_COLUMN) {
            return $this->readUInt16();
        }
        if ($c === self::UNSIGNED_INT24_COLUMN) {
            return $this->readUInt24();
        }
        if ($c == self::UNSIGNED_INT64_COLUMN) {
            return $this->unpackInt64($this->read(self::UNSIGNED_INT64_LENGTH));
        }

        throw new EventBinaryDataException('Column num ' . $c . ' not handled');
    }

    /**
     * @param int $size
     * @return int
     * @throws EventBinaryDataException
     */
    public function readIntBeBySize(int $size): int
    {
        if ($size === self::UNSIGNED_CHAR_LENGTH) {
            return $this->readInt8();
        }
        if ($size === self::UNSIGNED_SHORT_LENGTH) {
            return $this->readInt16Be();
        }
        if ($size === self::UNSIGNED_INT24_LENGTH) {
            return $this->readInt24Be();
        }
        if ($size === self::UNSIGNED_INT32_LENGTH) {
            return $this->readInt32Be();
        }
        if ($size === self::UNSIGNED_INT40_LENGTH) {
            return $this->readInt40Be();
        }

        throw new EventBinaryDataException('$size ' . $size . ' not handled');
    }

    /**
     * @param int $binary
     * @param int $start
     * @param int $size
     * @param int $binaryLength
     * @return int
     */
    public function getBinarySlice(int $binary, int $start, int $size, int $binaryLength): int
    {
        $binary >>= $binaryLength - ($start + $size);
        $mask = ((1 << $size) - 1);
        return $binary & $mask;
    }

}
