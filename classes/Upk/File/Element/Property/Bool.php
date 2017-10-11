<?php

/**
 * Class Upk_File_Element_Property_Bool
 */
class Upk_File_Element_Property_Bool extends Upk_File_Element_Abstract
{

    /** @var bool */
    private $value = false;

    /**
     * @param Reader $reader
     */
    protected function readElement(Reader $reader)
    {
        if (defined('GAME_TUROK')) {
            $this->value = boolval($reader->readDWord());
        }
        else {
            $this->value = boolval($reader->readByte());
        }
    }

    /**
     * @param int $byteOrder
     * @param Upk_Archive $upkArc
     * @return string
     */
    protected function packElement($byteOrder, Upk_Archive $upkArc)
    {
        if (defined('GAME_TUROK')) {
            $packedData = Reader::packDWord($this->value, $byteOrder);
        }
        else {
            $packedData = Reader::packByte($this->value);
        }

        $dataSize = 0x0000;
        return Reader::packInt64($dataSize, $byteOrder) . $packedData;
    }

    /**
     * @param string $indentStr
     * @return string
     */
    public function toString($indentStr = '')
    {
        return parent::toString($indentStr) . '=' . (($this->value) ? 'true' : 'false');
    }

}
