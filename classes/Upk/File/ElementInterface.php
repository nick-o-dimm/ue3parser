<?php

/**
 * Interface Upk_File_ElementInterface
 */
interface Upk_File_ElementInterface
{

    /**
     * Element must be able to self read from packed file.
     * @param Reader $reader
     */
    public function read(Reader $reader);

    /**
     * Save parsed file data to packed file.
     * @param int $byteOrder
     * @param null|Upk_Archive $upkArc
     * @return string
     */
    public function pack($byteOrder, Upk_Archive $upkArc = null);

    /**
     * Save parsed file data to INI-style string.
     * @param string $indentStr
     * @return string
     */
    public function toString($indentStr = '');

}
