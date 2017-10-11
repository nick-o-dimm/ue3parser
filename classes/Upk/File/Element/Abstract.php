<?php

/**
 * Class Upk_File_Element_Abstract
 */
abstract class Upk_File_Element_Abstract implements Upk_File_ElementInterface
{

    /** @var Upk_Archive */
    protected $upkArc = null;

    /** @var string Element name */
    protected $elemName = '';

    /** @var string Element data type */
    protected $elemDataType = '';

    /** @var int */
    protected $elemDataSize = 0;

    /** @var int */
    protected $byteOrder = 0;

    /** @var bool */
    protected $debugFlag = false;

    /**
     * Upk_File_Element_Abstract constructor.
     * @param string $elemName
     * @param string $elemDataType
     * @param Reader|null $reader
     * @param Upk_Archive $upkArc
     * @param bool $debugFlag
     */
    public function __construct($elemName, $elemDataType, $reader, Upk_Archive $upkArc, $debugFlag = false)
    {
        $this->elemName = $elemName;
        $this->elemDataType = $elemDataType;

        $this->byteOrder = (!is_null($reader)) ? $reader->getByteOrder() : Reader::PC;

        $this->upkArc = $upkArc;
        $this->debugFlag = $debugFlag;

        if (!is_null($reader)) {
            $this->read($reader);
        }
    }

    /**
     * @return string
     */
    public function getElemName()
    {
        return $this->elemName;
    }

    /**
     * @param Reader $reader
     */
    public function read(Reader $reader)
    {
        $this->elemDataSize = $reader->readInt64();

        if ($this->debugFlag) {
            echo '  Size: ' .  $this->elemDataSize . '<br/>';
        }

        $this->readElement($reader);
    }

    /**
     * @param Reader $reader
     */
    abstract protected function readElement(Reader $reader);

    /**
     * Save parsed file data to packed file.
     * @param int $byteOrder
     * @param null|Upk_Archive $upkArc
     * @return string
     */
    public function pack($byteOrder, Upk_Archive $upkArc = null)
    {
        $result = '';

        if (is_null($upkArc)) {
            $upkArc = $this->upkArc;
        }

        $nameTable = $upkArc->getNameTable();

        $elemNameId = $nameTable->getIdByName($this->elemName);
        $elemDataTypeId = $nameTable->getIdByName($this->elemDataType);

        $result .= Reader::packInt64($elemNameId, $byteOrder);
        $result .= Reader::packInt64($elemDataTypeId, $byteOrder);
        $result .= $this->packElement($byteOrder, $upkArc);

        return $result;
    }

    /**
     * @param int $byteOrder
     * @param Upk_Archive $upkArc
     * @return string
     */
    abstract protected function packElement($byteOrder, Upk_Archive $upkArc);

    /**
     * @param string $indentStr
     * @return string
     */
    public function toString($indentStr = '')
    {
        return $indentStr . $this->elemName . ':' . $this->elemDataType;
    }

}
