<?php

/**
 * Class Upk_File_Element_Property_Str
 */
class Upk_File_Element_Property_Str extends Upk_File_Element_Abstract
{

    /** @var int */
    private $length = 0;

    /** @var string */
    private $str = '';

    /**
     * @param Reader $reader
     */
    protected function readElement(Reader $reader)
    {
        $this->length = $reader->readSignedDWord();
        $readLength = ($this->length > 0) ? $this->length : -($this->length*2);
        $this->str = $reader->readData($readLength);
    }

    /**
     * @param int $byteOrder
     * @param Upk_Archive $upkArc
     * @return string
     */
    protected function packElement($byteOrder, Upk_Archive $upkArc)
    {
        $packedData = Reader::packDWord($this->length, $byteOrder) . $this->str;
        $dataSize = strlen($packedData);
        return Reader::packInt64($dataSize, $byteOrder) . $packedData;
    }

    /**
     * @return string
     */
    public function getStr()
    {
        $str = $this->str;
        if ($this->length < 0) {
            $str = iconv('UTF-16LE', 'UTF-8', $str);
        }
        $str = trim($str, "\0");
        return $str;
    }

    /**
     * @param string $indentStr
     * @return string
     */
    public function toString($indentStr = '')
    {
        return $indentStr . $this->elemName . '="' . $this->getStr() . '"';
    }

}
