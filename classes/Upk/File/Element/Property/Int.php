<?php

/**
 * Class Upk_File_Element_Property_Int
 */
class Upk_File_Element_Property_Int extends Upk_File_Element_Abstract
{

    /** @var int */
    private $value = 0;

    /**
     * @param Reader $reader
     */
    protected function readElement(Reader $reader)
    {
        $this->value = $reader->readSignedDWord();
    }

    /**
     * @param int $byteOrder
     * @param Upk_Archive $upkArc
     * @return string
     */
    protected function packElement($byteOrder, Upk_Archive $upkArc)
    {
        $packedData = Reader::packDWord($this->value, $byteOrder);
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
     * @return int
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param int $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

}
