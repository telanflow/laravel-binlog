<?php

namespace Telanflow\Binlog\DTO;

use Illuminate\Support\Collection;

class ColumnDTOCollection extends Collection
{
    /**
     * @var string
     */
    private $primaryKey = '';

    /**
     * @return string
     */
    public function getPrimaryKey(): string
    {
        if (!empty($this->primaryKey)) {
            return $this->primaryKey;
        }

        if(!empty($this->items) && is_array($this->items))
        {
            /** @var ColumnDTO $v */
            foreach($this->items as $v)
            {
                if ($v->isPrimary()) {
                    $this->primaryKey = $v->getName();
                    break;
                }
            }
        }

        return $this->primaryKey;
    }

}
