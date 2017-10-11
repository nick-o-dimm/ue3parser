<?php

/**
 * Class Upk_File_Element_ItemReader
 */
abstract class Upk_File_Element_ItemReader
{

    /** @var Upk_Archive */
    protected $upkArc = null;

    /** @var bool */
    protected $debugFlag = false;

    /**
     * Upk_File_Element_ItemReader_List constructor.
     * @param Upk_Archive $upkArc
     * @param bool $debugFlag
     */
    public function __construct(Upk_Archive $upkArc, $debugFlag = false)
    {
        $this->upkArc = $upkArc;
        $this->debugFlag = $debugFlag;
    }

    /**
     * @param Reader $reader
     * @return mixed
     */
    abstract public function readItem(Reader $reader);

    /**
     * @param mixed $item
     * @param int $byteOrder
     * @param Upk_Archive $upkArc
     * @return string
     */
    abstract public function packItem($item, $byteOrder, Upk_Archive $upkArc);
}
