<?php

namespace Telanflow\Binlog\Event;

use Exception;
use Illuminate\Contracts\Cache\Repository;
use Telanflow\Binlog\Constants\EventTypeConst;
use Telanflow\Binlog\Contracts\EventInterface;
use Telanflow\Binlog\DTO\ColumnDTOCollection;
use Telanflow\Binlog\Constants\FieldTypeConst;
use Telanflow\Binlog\DTO\ColumnDTO;
use Telanflow\Binlog\Event\Record\Delete;
use Telanflow\Binlog\Event\Record\FormatDescription;
use Telanflow\Binlog\Event\Record\GTID;
use Telanflow\Binlog\Event\Record\Heartbeat;
use Telanflow\Binlog\Event\Record\Insert;
use Telanflow\Binlog\Event\Record\Query;
use Telanflow\Binlog\Event\Record\Rotate;
use Telanflow\Binlog\Event\Record\TableMap;
use Telanflow\Binlog\Event\Record\Update;
use Telanflow\Binlog\Event\Record\Xid;
use Telanflow\Binlog\DTO\FieldDTO;
use Telanflow\Binlog\DTO\TableMapDTO;
use Telanflow\Binlog\Exceptions\EventBinaryDataException;
use Telanflow\Binlog\JsonBinaryDecoder\JsonBinaryDecoderService;
use Telanflow\Binlog\Server\Database;
use DateTime;

class EventBuilder
{
    /**
     * @var EventBinaryData
     */
    private $eventBinaryData;

    /**
     * @var EventInfo
     */
    private $eventInfo;

    /**
     * @var Repository
     */
    private $cache;

    /**
     * @var TableMapDTO
     */
    private $currentTableMapDTO;

    /**
     * @var array
     */
    public static $bitCountInByte = [
        0, 1, 1, 2, 1, 2, 2, 3, 1, 2, 2, 3, 2, 3, 3, 4,
        1, 2, 2, 3, 2, 3, 3, 4, 2, 3, 3, 4, 3, 4, 4, 5,
        1, 2, 2, 3, 2, 3, 3, 4, 2, 3, 3, 4, 3, 4, 4, 5,
        2, 3, 3, 4, 3, 4, 4, 5, 3, 4, 4, 5, 4, 5, 5, 6,
        1, 2, 2, 3, 2, 3, 3, 4, 2, 3, 3, 4, 3, 4, 4, 5,
        2, 3, 3, 4, 3, 4, 4, 5, 3, 4, 4, 5, 4, 5, 5, 6,
        2, 3, 3, 4, 3, 4, 4, 5, 3, 4, 4, 5, 4, 5, 5, 6,
        3, 4, 4, 5, 4, 5, 5, 6, 4, 5, 5, 6, 5, 6, 6, 7,
        1, 2, 2, 3, 2, 3, 3, 4, 2, 3, 3, 4, 3, 4, 4, 5,
        2, 3, 3, 4, 3, 4, 4, 5, 3, 4, 4, 5, 4, 5, 5, 6,
        2, 3, 3, 4, 3, 4, 4, 5, 3, 4, 4, 5, 4, 5, 5, 6,
        3, 4, 4, 5, 4, 5, 5, 6, 4, 5, 5, 6, 5, 6, 6, 7,
        2, 3, 3, 4, 3, 4, 4, 5, 3, 4, 4, 5, 4, 5, 5, 6,
        3, 4, 4, 5, 4, 5, 5, 6, 4, 5, 5, 6, 5, 6, 6, 7,
        3, 4, 4, 5, 4, 5, 5, 6, 4, 5, 5, 6, 5, 6, 6, 7,
        4, 5, 5, 6, 5, 6, 6, 7, 5, 6, 6, 7, 6, 7, 7, 8,
    ];

    public function __construct(EventBinaryData $eventBinaryData, EventInfo $eventInfo, $cache)
    {
        $this->eventInfo = $eventInfo;
        $this->eventBinaryData = $eventBinaryData;
        $this->cache = $cache;
    }

    public function makeTableMap(): EventInterface
    {
        $data = [];
        $data['table_id'] = $this->eventBinaryData->readTableId();
        $this->eventBinaryData->advance(2);
        $schemaLength = $this->eventBinaryData->readUInt8();
        $data['schema_name'] = $this->eventBinaryData->read($schemaLength);

        $this->eventBinaryData->advance(1);
        $tableNameLength = $this->eventBinaryData->readUInt8();
        $data['table_name'] = $this->eventBinaryData->read($tableNameLength);

        $this->eventBinaryData->advance(1);
        $data['columns_amount'] = (int)$this->eventBinaryData->readCodedBinary();
        $data['column_types'] = $this->eventBinaryData->read($data['columns_amount']);

        if ($this->cache->has($data['table_id'])) {
            return new TableMap($this->eventInfo, $this->cache->get($data['table_id']));
        }

        $this->eventBinaryData->readCodedBinary();
        $fieldCollection = Database::getTableFields($data['schema_name'], $data['table_name']);
        $columnCollection = new ColumnDTOCollection();
        // if you drop tables and parse of logs you will get empty scheme
        if (!$fieldCollection->isEmpty())
        {
            $columnLength = strlen($data['column_types']);
            for ($offset = 0; $offset < $columnLength; ++$offset)
            {
                // this a dirty hack to prevent row events containing columns which have been dropped
                if ($fieldCollection->offsetExists($offset)) {
                    $type = ord($data['column_types'][$offset]);
                } else {
                    $fieldCollection->offsetSet($offset, FieldDTO::makeDummy($offset));
                    $type = FieldTypeConst::IGNORE;
                }

                /**@var FieldDTO $fieldDTO **/
                $fieldDTO = $fieldCollection->offsetGet($offset);
                if (null !== $fieldDTO) {
                    $columnCollection->offsetSet($offset, ColumnDTO::make($type, $fieldDTO, $this->eventBinaryData));
                }
            }
        }

        $tableMapDTO = new TableMapDTO(
            $data['table_id'],
            $data['schema_name'],
            $data['table_name'],
            $data['columns_amount'],
            $columnCollection
        );

        $this->cache->set($data['table_id'], $tableMapDTO);

        return new TableMap($this->eventInfo, $tableMapDTO);
    }

    public function makeRotate(): EventInterface
    {
        $binFilePos = (int)$this->eventBinaryData->readUInt64();
        $binFileName = $this->eventBinaryData->read($this->eventInfo->getSizeNoHeader() - 8);

        $this->eventInfo->getBinlogCurrent()->setBinlogPosition($binFilePos);
        $this->eventInfo->getBinlogCurrent()->setBinlogFileName($binFileName);

        return new Rotate($this->eventInfo);
    }

    public function makeHeartbeat(): EventInterface
    {
        return new Heartbeat($this->eventInfo);
    }

    public function makeGTIDLog(): EventInterface
    {
        $commitFlag = 1 === $this->eventBinaryData->readUInt8();
        $sid = unpack('H*', $this->eventBinaryData->read(16))[1];
        $gno = $this->eventBinaryData->readUInt64();

        $gtid = vsprintf('%s%s%s%s%s%s%s%s-%s%s%s%s-%s%s%s%s-%s%s%s%s-%s%s%s%s%s%s%s%s%s%s%s%s', str_split($sid)) . ':' . $gno;
        $this->eventInfo->getBinlogCurrent()->setGtid($gtid);

        return new GTID($this->eventInfo, $commitFlag);
    }

    public function makeUpdateRecord(): ?EventInterface
    {
        if (!$this->recordInit()) {
            return null;
        }

        $columnsBinarySize = self::getColumnsBinarySize($this->currentTableMapDTO->getColumnsAmount());
        $beforeBinaryData = $this->eventBinaryData->read($columnsBinarySize);
        $afterBinaryData = $this->eventBinaryData->read($columnsBinarySize);

        $values = [];
        while (!$this->eventBinaryData->isComplete($this->eventInfo->getSizeNoHeader()))
        {
            $values[] = [
                'before' => $this->getColumnData($beforeBinaryData),
                'after' => $this->getColumnData($afterBinaryData)
            ];
        }

        return new Update(
            $this->eventInfo,
            $this->currentTableMapDTO,
            $values
        );
    }

    public function makeInsertRecord(): ?EventInterface
    {
        if (!$this->recordInit()) {
            return null;
        }

        $values = $this->getValues();

        return new Insert(
            $this->eventInfo,
            $this->currentTableMapDTO,
            $values
        );
    }

    public function makeDeleteRecord(): ?EventInterface
    {
        if (!$this->recordInit()) {
            return null;
        }

        $values = $this->getValues();

        return new Delete(
            $this->eventInfo,
            $this->currentTableMapDTO,
            $values
        );
    }

    /**
     * @return EventInterface
     */
    public function makeXidRecord(): EventInterface
    {
        return new Xid(
            $this->eventInfo,
            $this->eventBinaryData->readUInt64()
        );
    }

    /**
     * @return EventInterface
     */
    public function makeQueryRecord(): EventInterface
    {
        $this->eventBinaryData->advance(4);
        $executionTime = $this->eventBinaryData->readUInt32();
        $schemaLength = $this->eventBinaryData->readUInt8();
        $this->eventBinaryData->advance(2);
        $statusVarsLength = $this->eventBinaryData->readUInt16();
        $this->eventBinaryData->advance($statusVarsLength);
        $schema = $this->eventBinaryData->read($schemaLength);
        $this->eventBinaryData->advance(1);
        $query = $this->eventBinaryData->read($this->eventInfo->getSizeNoHeader() - 13 - $statusVarsLength - $schemaLength - 1);

        return new Query(
            $this->eventInfo,
            $schema,
            $executionTime,
            $query
        );
    }

    /**
     * @return EventInterface
     */
    public function makeFormatDescriptionRecord(): EventInterface
    {
        return new FormatDescription($this->eventInfo);
    }

    /**
     * @return bool
     * @throws EventBinaryDataException
     */
    protected function recordInit(): bool
    {
        $tableId = $this->eventBinaryData->readTableId();
        $this->eventBinaryData->advance(2);

        if (in_array(
            $this->eventInfo->getType(), [
            EventTypeConst::DELETE_ROWS_EVENT_V2,
            EventTypeConst::WRITE_ROWS_EVENT_V2,
            EventTypeConst::UPDATE_ROWS_EVENT_V2
        ], true
        )) {
            $this->eventBinaryData->read((int)($this->eventBinaryData->readUInt16() / 8));
        }

        $this->eventBinaryData->readCodedBinary();

        if ($this->cache->has($tableId)) {
            /** @var TableMapDTO currentTableMapDTO */
            $this->currentTableMapDTO = $this->cache->get($tableId);

            return true;
        }

        return false;
    }

    /**
     * @return array
     * @throws EventBinaryDataException
     */
    protected function getValues(): array
    {
        // if we don't get columns from information schema we don't know how to assign them
        if ($this->currentTableMapDTO === null || $this->currentTableMapDTO->getColumnDTOCollection()->isEmpty()) {
            return [];
        }

        $binaryData = $this->eventBinaryData->read(
            $this->getColumnsBinarySize($this->currentTableMapDTO->getColumnsAmount())
        );

        $values = [];
        while (!$this->eventBinaryData->isComplete($this->eventInfo->getSizeNoHeader())) {
            $values[] = $this->getColumnData($binaryData);
        }

        return $values;
    }

    protected static function getColumnsBinarySize($columnsAmount): int
    {
        return intval(($columnsAmount + 7) / 8);
    }

    /**
     * @param string $colsBitmap
     * @return array
     * @throws EventBinaryDataException
     * @throws Exception
     */
    protected function getColumnData(string $colsBitmap): array
    {
        if (null === $this->currentTableMapDTO) {
            throw new Exception('Current table map is missing!');
        }

        $values = [];

        // null bitmap length = (bits set in 'columns-present-bitmap'+7)/8
        // see http://dev.mysql.com/doc/internals/en/rows-event.html
        $nullBitmap = $this->eventBinaryData->read($this->getColumnsBinarySize($this->bitCount($colsBitmap)));
        $nullBitmapIndex = 0;

        foreach ($this->currentTableMapDTO->getColumnDTOCollection() as $i => $columnDTO)
        {
            $name = $columnDTO->getName();
            $type = $columnDTO->getType();

            if (0 === $this->bitGet($colsBitmap, $i)) {
                $values[$name] = null;
                continue;
            }

            if ($this->checkNull($nullBitmap, $nullBitmapIndex)) {
                $values[$name] = null;
            } else if ($type === FieldTypeConst::IGNORE) {
                $this->eventBinaryData->advance($columnDTO->getLengthSize());
                $values[$name] = null;
            } else if ($type === FieldTypeConst::TINY) {
                if ($columnDTO->isUnsigned()) {
                    $values[$name] = $this->eventBinaryData->readUInt8();
                } else {
                    $values[$name] = $this->eventBinaryData->readInt8();
                }
            } else if ($type === FieldTypeConst::SHORT) {
                if ($columnDTO->isUnsigned()) {
                    $values[$name] = $this->eventBinaryData->readUInt16();
                } else {
                    $values[$name] = $this->eventBinaryData->readInt16();
                }
            } else if ($type === FieldTypeConst::LONG) {
                if ($columnDTO->isUnsigned()) {
                    $values[$name] = $this->eventBinaryData->readUInt32();
                } else {
                    $values[$name] = $this->eventBinaryData->readInt32();
                }
            } else if ($type === FieldTypeConst::LONGLONG) {
                if ($columnDTO->isUnsigned()) {
                    $values[$name] = $this->eventBinaryData->readUInt64();
                } else {
                    $values[$name] = $this->eventBinaryData->readInt64();
                }
            } else if ($type === FieldTypeConst::INT24) {
                if ($columnDTO->isUnsigned()) {
                    $values[$name] = $this->eventBinaryData->readUInt24();
                } else {
                    $values[$name] = $this->eventBinaryData->readInt24();
                }
            } else if ($type === FieldTypeConst::FLOAT) {
                // http://dev.mysql.com/doc/refman/5.7/en/floating-point-types.html FLOAT(7,4)
                $values[$name] = round($this->eventBinaryData->readFloat(), 4);
            } else if ($type === FieldTypeConst::DOUBLE) {
                $values[$name] = $this->eventBinaryData->readDouble();
            } else if ($type === FieldTypeConst::VARCHAR || $type === FieldTypeConst::STRING) {
                $values[$name] = $columnDTO->getMaxLength() > 255 ? $this->getString(2) : $this->getString(1);
            } else if ($type === FieldTypeConst::NEWDECIMAL) {
                $values[$name] = $this->getDecimal($columnDTO);
            } else if ($type === FieldTypeConst::BLOB) {
                $values[$name] = $this->getString($columnDTO->getLengthSize());
            } else if ($type === FieldTypeConst::DATETIME) {
                $values[$name] = $this->getDatetime();
            } else if ($type === FieldTypeConst::DATETIME2) {
                $values[$name] = $this->getDatetime2($columnDTO);
            } else if ($type === FieldTypeConst::TIMESTAMP) {
                $values[$name] = date('Y-m-d H:i:s', $this->eventBinaryData->readUInt32());
            } else if ($type === FieldTypeConst::TIME) {
                $values[$name] = $this->getTime();
            } else if ($type === FieldTypeConst::TIME2) {
                $values[$name] = $this->getTime2($columnDTO);
            } else if ($type === FieldTypeConst::TIMESTAMP2) {
                $values[$name] = $this->getTimestamp2($columnDTO);
            } else if ($type === FieldTypeConst::DATE) {
                $values[$name] = $this->getDate();
            } else if ($type === FieldTypeConst::YEAR) {
                // https://dev.mysql.com/doc/refman/5.7/en/year.html
                $year = $this->eventBinaryData->readUInt8();
                $values[$name] = 0 === $year ? null : 1900 + $year;
            } else if ($type === FieldTypeConst::ENUM) {
                $values[$name] = $this->getEnum($columnDTO);
            } else if ($type === FieldTypeConst::SET) {
                $values[$name] = $this->getSet($columnDTO);
            } else if ($type === FieldTypeConst::BIT) {
                $values[$name] = $this->getBit($columnDTO);
            } else if ($type === FieldTypeConst::GEOMETRY) {
                $values[$name] = $this->getString($columnDTO->getLengthSize());
            } else if ($type === FieldTypeConst::JSON) {
                $values[$name] = JsonBinaryDecoderService::makeJsonBinaryDecoder($this->getString($columnDTO->getLengthSize()))->parseToString();
            } else {
                throw new Exception('Unknown row type: ' . $type);
            }

            ++$nullBitmapIndex;
        }

        return $values;
    }

    /**
     * @param string $bitmap
     * @return int
     */
    protected static function bitCount(string $bitmap): int
    {
        $n = 0;
        $bitmapLength = strlen($bitmap);
        for ($i = 0; $i < $bitmapLength; ++$i) {
            $bit = $bitmap[$i];
            if (is_string($bit)) {
                $bit = ord($bit);
            }
            $n += self::$bitCountInByte[$bit];
        }

        return $n;
    }

    /**
     * @param string $bitmap
     * @param int $position
     * @return int
     */
    protected static function bitGet($bitmap, $position): int
    {
        return self::getBitFromBitmap($bitmap, $position) & (1 << ($position & 7));
    }

    /**
     * @param string $bitmap
     * @param int $position
     * @return int
     */
    protected static function getBitFromBitmap($bitmap, $position): int
    {
        $bit = $bitmap[(int)($position / 8)];
        if (is_string($bit)) {
            $bit = ord($bit);
        }
        return $bit;
    }

    /**
     * @param string $nullBitmap
     * @param int $position
     * @return int
     */
    protected static function checkNull(string $nullBitmap, int $position): int
    {
        return self::getBitFromBitmap($nullBitmap, $position) & (1 << ($position % 8));
    }

    /**
     * @param int $size
     * @return string
     * @throws EventBinaryDataException
     */
    protected function getString($size): string
    {
        return $this->eventBinaryData->readLengthString($size);
    }

    /**
     * Read MySQL's new decimal format introduced in MySQL 5
     * https://dev.mysql.com/doc/refman/5.6/en/precision-math-decimal-characteristics.html
     *
     * @param ColumnDTO $columnDTO
     * @return string
     * @throws EventBinaryDataException
     */
    protected function getDecimal(ColumnDTO $columnDTO): string
    {
        $digitsPerInteger = 9;
        $compressedBytes = [0, 1, 1, 2, 2, 3, 3, 4, 4, 4];
        $integral = $columnDTO->getPrecision() - $columnDTO->getDecimals();
        $unCompIntegral = (int)($integral / $digitsPerInteger);
        $unCompFractional = (int)($columnDTO->getDecimals() / $digitsPerInteger);
        $compIntegral = $integral - ($unCompIntegral * $digitsPerInteger);
        $compFractional = $columnDTO->getDecimals() - ($unCompFractional * $digitsPerInteger);

        $value = $this->eventBinaryData->readUInt8();
        if (0 !== ($value & 0x80)) {
            $mask = 0;
            $res = '';
        } else {
            $mask = -1;
            $res = '-';
        }
        $this->eventBinaryData->unread(pack('C', $value ^ 0x80));

        $size = $compressedBytes[$compIntegral];
        if ($size > 0) {
            $value = $this->eventBinaryData->readIntBeBySize($size) ^ $mask;
            $res .= $value;
        }

        for ($i = 0; $i < $unCompIntegral; ++$i) {
            $value = $this->eventBinaryData->readInt32Be() ^ $mask;
            $res .= sprintf('%09d', $value);
        }

        $res .= '.';

        for ($i = 0; $i < $unCompFractional; ++$i) {
            $value = $this->eventBinaryData->readInt32Be() ^ $mask;
            $res .= sprintf('%09d', $value);
        }

        $size = $compressedBytes[$compFractional];
        if ($size > 0) {
            $value = $this->eventBinaryData->readIntBeBySize($size) ^ $mask;
            $res .= sprintf('%0' . $compFractional . 'd', $value);
        }

        return bcmul($res, '1', $columnDTO->getDecimals());
    }

    protected function getDatetime(): ?string
    {
        $value = $this->eventBinaryData->readUInt64();
        // nasty mysql 0000-00-00 dates
        if ('0' === $value) {
            return null;
        }

        $date = DateTime::createFromFormat('YmdHis', $value)->format('Y-m-d H:i:s');
        if (array_sum(DateTime::getLastErrors()) > 0) {
            return null;
        }

        return $date;
    }

    /**
     * Date Time
     * 1 bit  sign           (1= non-negative, 0= negative)
     * 17 bits year*13+month  (year 0-9999, month 0-12)
     * 5 bits day            (0-31)
     * 5 bits hour           (0-23)
     * 6 bits minute         (0-59)
     * 6 bits second         (0-59)
     * ---------------------------
     * 40 bits = 5 bytes
     *
     * @param ColumnDTO $columnDTO
     * @return string|null
     * @throws EventBinaryDataException
     *
     * @link https://dev.mysql.com/doc/internals/en/date-and-time-data-type-representation.html
     */
    protected function getDatetime2(ColumnDTO $columnDTO): ?string
    {
        $data = $this->eventBinaryData->readIntBeBySize(5);

        $yearMonth = $this->eventBinaryData->getBinarySlice($data, 1, 17, 40);

        $year = (int)($yearMonth / 13);
        $month = $yearMonth % 13;
        $day = $this->eventBinaryData->getBinarySlice($data, 18, 5, 40);
        $hour = $this->eventBinaryData->getBinarySlice($data, 23, 5, 40);
        $minute = $this->eventBinaryData->getBinarySlice($data, 28, 6, 40);
        $second = $this->eventBinaryData->getBinarySlice($data, 34, 6, 40);
        $fsp = $this->getFSP($columnDTO);

        try {
            $date = new DateTime($year . '-' . $month . '-' . $day . ' ' . $hour . ':' . $minute . ':' . $second);
        } catch (Exception $exception) {
            return null;
        }
        if (array_sum(DateTime::getLastErrors()) > 0) {
            return null;
        }

        return $date->format('Y-m-d H:i:s') . $fsp;
    }

    /**
     * @param ColumnDTO $columnDTO
     * @return string
     * @throws EventBinaryDataException
     * @link https://dev.mysql.com/doc/internals/en/date-and-time-data-type-representation.html
     */
    protected function getFSP(ColumnDTO $columnDTO): string
    {
        $read = 0;
        $time = '';
        $fsp = $columnDTO->getFsp();
        if ($fsp === 1 || $fsp === 2) {
            $read = 1;
        } else if ($fsp === 3 || $fsp === 4) {
            $read = 2;
        } else if ($fsp === 5 || $fsp === 6) {
            $read = 3;
        }
        if ($read > 0) {
            $microsecond = $this->eventBinaryData->readIntBeBySize($read);
            if ($fsp % 2) {
                $microsecond = (int)($microsecond / 10);

            }
            $time = $microsecond * (10 ** (6 - $fsp));
        }

        return (string)$time;
    }

    /**
     * @return string
     */
    protected function getTime(): string
    {
        $data = $this->eventBinaryData->readUInt24();
        if (0 === $data) {
            return '00:00:00';
        }

        return sprintf('%s%02d:%02d:%02d', $data < 0 ? '-' : '', $data / 10000, ($data % 10000) / 100, $data % 100);
    }

    /**
     * TIME encoding for non fractional part:
     * 1 bit sign    (1= non-negative, 0= negative)
     * 1 bit unused  (reserved for future extensions)
     * 10 bits hour   (0-838)
     * 6 bits minute (0-59)
     * 6 bits second (0-59)
     * ---------------------
     * 24 bits = 3 bytes
     *
     * @param ColumnDTO $columnDTO
     * @return string
     * @throws EventBinaryDataException
     */
    protected function getTime2(ColumnDTO $columnDTO): string
    {
        $data = $this->eventBinaryData->readInt24Be();

        $hour = $this->eventBinaryData->getBinarySlice($data, 2, 10, 24);
        $minute = $this->eventBinaryData->getBinarySlice($data, 12, 6, 24);
        $second = $this->eventBinaryData->getBinarySlice($data, 18, 6, 24);

        return (new DateTime())->setTime($hour, $minute, $second)->format('H:i:s') . $this->getFSP($columnDTO);
    }

    /**
     * @param ColumnDTO $columnDTO
     * @return string
     * @throws EventBinaryDataException
     */
    protected function getTimestamp2(ColumnDTO $columnDTO): string
    {
        $datetime = (string)date('Y-m-d H:i:s', $this->eventBinaryData->readInt32Be());
        $fsp = $this->getFSP($columnDTO);
        if ('' !== $fsp) {
            $datetime .= '.' . $fsp;
        }

        return $datetime;
    }

    protected function getDate(): ?string
    {
        $time = $this->eventBinaryData->readUInt24();
        if (0 === $time) {
            return null;
        }

        $year = ($time & ((1 << 15) - 1) << 9) >> 9;
        $month = ($time & ((1 << 4) - 1) << 5) >> 5;
        $day = ($time & ((1 << 5) - 1));
        if ($year === 0 || $month === 0 || $day === 0) {
            return null;
        }

        return (new DateTime())->setDate($year, $month, $day)->format('Y-m-d');
    }

    /**
     * @param ColumnDTO $columnDTO
     * @return string
     * @throws EventBinaryDataException
     */
    protected function getEnum(ColumnDTO $columnDTO): string
    {
        $value = $this->eventBinaryData->readUIntBySize($columnDTO->getSize()) - 1;

        // check if given value exists in enums, if there not existing enum mysql returns empty string.
        if (array_key_exists($value, $columnDTO->getEnumValues())) {
            return $columnDTO->getEnumValues()[$value];
        }

        return '';
    }

    /**
     * @param ColumnDTO $columnDTO
     * @return array
     * @throws EventBinaryDataException
     */
    protected function getSet(ColumnDTO $columnDTO): array
    {
        // we read set columns as a bitmap telling us which options are enabled
        $bit_mask = $this->eventBinaryData->readUIntBySize($columnDTO->getSize());
        $sets = [];
        foreach ($columnDTO->getSetValues() as $k => $item) {
            if ($bit_mask & (2 ** $k)) {
                $sets[] = $item;
            }
        }

        return $sets;
    }

    /**
     * @param ColumnDTO $columnDTO
     * @return string
     */
    protected function getBit(ColumnDTO $columnDTO): string
    {
        $res = '';
        for ($byte = 0; $byte < $columnDTO->getBytes(); ++$byte) {
            $current_byte = '';
            $data = $this->eventBinaryData->readUInt8();
            if (0 === $byte) {
                if (1 === $columnDTO->getBytes()) {
                    $end = $columnDTO->getBits();
                } else {
                    $end = $columnDTO->getBits() % 8;
                    if (0 === $end) {
                        $end = 8;
                    }
                }
            } else {
                $end = 8;
            }

            for ($bit = 0; $bit < $end; ++$bit) {
                if ($data & (1 << $bit)) {
                    $current_byte .= '1';
                } else {
                    $current_byte .= '0';
                }

            }
            $res .= strrev($current_byte);
        }

        return $res;
    }

}
