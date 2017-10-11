<?php

/**
 * Class Upk_File
 */
class Upk_File extends Upk_File_Abstract
{

    /** @var string Rest data */
    private $restData = '';

    /**
     * @param Reader $reader
     */
    protected function readExtraData(Reader $reader)
    {
        $this->restData = $reader->readRestData();
    }

    /**
     * @param int $byteOrder
     * @return string
     */
    protected function packExtraData($byteOrder)
    {
        return $this->restData;
    }

    /**
     * @return string
     */
    public function getRestData()
    {
        return $this->restData;
    }

    /**
     * @param string $restData
     */
    public function setRestData($restData)
    {
        $this->restData = $restData;
    }

    /**
     * @return string
     */
    protected function toStringExtraData()
    {
        $result = '';

        $restDataLength = strlen($this->restData);
        if ($restDataLength > 0) {
            $result .= 'restData=[' . $restDataLength . ' bytes]' . "\n";
        }

        return $result;
    }

}
