<?php

/**
 * Class Upk_File_Element_Property_Byte
 */
class Upk_File_Element_Property_Byte extends Upk_File_Element_Abstract
{

    /** @var string */
    private $dataName = '';

    /** @var string */
    private $dataValue = '';

    /**
     * @param Reader $reader
     */
    protected function readElement(Reader $reader)
    {
        $nameTable = $this->upkArc->getNameTable();

        if (defined('GAME_TUROK')) {
            $this->dataValue = $reader->readByte();
        }
        else {
            $dataNameId = $reader->readInt64();
            $this->dataName = $nameTable->getNameById($dataNameId);

            $dataValueId = $reader->readInt64();
            $this->dataValue = $nameTable->getNameById($dataValueId);
        }
    }

    /**
     * @param int $byteOrder
     * @param Upk_Archive $upkArc
     * @return string
     */
    protected function packElement($byteOrder, Upk_Archive $upkArc)
    {
        $nameTable = $this->upkArc->getNameTable();

        if (defined('GAME_TUROK')) {
            $packedData = Reader::packByte($this->dataValue);

            $dataSize = 0x0001;
        }
        else {
            $dataNameId = $nameTable->getIdByName($this->dataName);
            $packedData = Reader::packInt64($dataNameId, $byteOrder);

            $dataValueId = $nameTable->getIdByName($this->dataValue);
            $packedData .= Reader::packInt64($dataValueId, $byteOrder);

            $dataSize = 0x0008;
        }

        return Reader::packInt64($dataSize, $byteOrder) . $packedData;
    }

    /**
     * @param string $indentStr
     * @return string
     */
    public function toString($indentStr = '')
    {
        if (defined('GAME_TUROK')) {
            return parent::toString($indentStr) . '=(' . $this->dataValue . ')';
        }
        else {
            return parent::toString($indentStr) . '=(' . $this->dataName . ':' . $this->dataValue . ')';
        }
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->dataValue;
    }

}
