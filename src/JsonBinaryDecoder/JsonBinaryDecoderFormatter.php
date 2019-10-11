<?php

namespace Telanflow\Binlog\JsonBinaryDecoder;

class JsonBinaryDecoderFormatter
{
    public $jsonString = '';

    public function formatValueBool(bool $bool)
    {
        $this->jsonString .= var_export($bool, true);
    }

    public function formatValueNumeric(int $val)
    {
        $this->jsonString .= $val;
    }

    public function formatValue($val)
    {
        $this->jsonString .= '"' . self::escapeJsonString($val) . '"';
    }

    /**
     * Some characters needs to be escaped
     * @see http://www.json.org/
     * @see https://stackoverflow.com/questions/1048487/phps-json-encode-does-not-escape-all-json-control-characters
     */
    private static function escapeJsonString($value)
    {
        return str_replace(
            ["\\", '/', '"', "\n", "\r", "\t", "\x08", "\x0c"],
            ["\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b"],
            $value
        );
    }

    public function formatEndObject()
    {
        $this->jsonString .= '}';
    }

    public function formatBeginArray()
    {
        $this->jsonString .= '[';
    }

    public function formatEndArray()
    {
        $this->jsonString .= ']';
    }

    public function formatBeginObject()
    {
        $this->jsonString .= '{';
    }

    public function formatNextEntry()
    {
        $this->jsonString .= ',';
    }

    public function formatName(string $name)
    {
        $this->jsonString .= '"' . $name . '":';
    }

    public function formatValueNull()
    {
        $this->jsonString .= 'null';
    }

    public function getJsonString()
    {
        return $this->jsonString;
    }
}
