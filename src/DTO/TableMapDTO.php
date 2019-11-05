<?php

namespace Telanflow\Binlog\DTO;

class TableMapDTO
{
    /**
     * @var string
     */
    public $tableId;

    /**
     * @var string
     */
    public $database;

    /**
     * @var string
     */
    public $tableName;

    /**
     * @var int
     */
    private $columnsAmount;

    /**
     * @var ColumnDTOCollection
     */
    private $columnDTOCollection;

    public function __construct($tableId, $database, $tableName, $columnsAmount, ColumnDTOCollection $columnDTOCollection)
    {
        $this->tableId = $tableId;
        $this->database = $database;
        $this->tableName = $tableName;
        $this->columnsAmount = $columnsAmount;
        $this->columnDTOCollection = $columnDTOCollection;
    }

    public function getDatabase(): string
    {
        return $this->database;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getTableId(): string
    {
        return $this->tableId;
    }

    public function getPrimaryKey(): string
    {
        return $this->columnDTOCollection->getPrimaryKey();
    }

    public function getColumnsAmount(): int
    {
        return $this->columnsAmount;
    }

    /**
     * @return ColumnDTOCollection|ColumnDTO[]
     */
    public function getColumnDTOCollection(): ColumnDTOCollection
    {
        return $this->columnDTOCollection;
    }

}
