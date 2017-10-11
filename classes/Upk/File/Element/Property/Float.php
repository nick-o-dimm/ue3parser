<?php

/**
 * Class Upk_File_Element_Property_Float
 */
class Upk_File_Element_Property_Float extends Upk_File_Element_Abstract
{

    /** @var float */
    private $value = 0.0;

    /**
     * @param Reader $reader
     */
    protected function readElement(Reader $reader)
    {
        $this->value = $reader->readFloat();
    }

    /**
     * @param int $byteOrder
     * @param Upk_Archive $upkArc
     * @return string
     */
    protected function packElement($byteOrder, Upk_Archive $upkArc)
    {
        $packedData = Reader::packFloat($this->value, $byteOrder);
        $dataSize = strlen($packedData);
        return Reader::packInt64($dataSize, $byteOrder) . $packedData;
    }

    /**
     * @param string $indentStr
     * @return string
     */
    public function toString($indentStr = '')
    {
        return $indentStr . $this->elemName . '=' . $this->value;
    }

    /**
     * @return float
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param float $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

}
