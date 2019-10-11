<?php

namespace Telanflow\Binlog\DTO;

use Illuminate\Support\Collection;

class FieldDTOCollection extends Collection
{
    public static function makeFromArray(array $fields): self
    {
        $collection = new self();
        foreach ($fields as $field)
        {
            if (is_array($field)) {
                $v = FieldDTO::makeFromArray($field);
            } else {
                $v = FieldDTO::makeFromObject($field);
            }
            $collection->add($v);
        }
        return $collection;
    }
}
