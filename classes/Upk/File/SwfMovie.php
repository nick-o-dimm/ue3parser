<?php

/**
 * Class Upk_File_SwfMovie
 */
class Upk_File_SwfMovie extends Upk_File
{
    const HEADER_GFX = 'GFX';
    const HEADER_FWS = 'FWS';

    public function getRawData()
    {
        /** @var Upk_File_Element_Property_Array $rawDataObj */
        $rawDataObj = $this->searchElement('RawData');
        return $rawDataObj->getRawItems();
    }

    /**
     * @param string $rawData
     * @param bool $fixHeader
     * @param string $header
     */
    public function setRawData($rawData, $fixHeader = false, $header = self::HEADER_GFX)
    {
        if ($fixHeader) {
            for ($i=0; $i<strlen($header); $i++) {
                $rawData[$i] = $header[$i];
            }
        }

        // This is byte array. The byte order does not matter.
        $swfReader = new Reader_Memory($rawData, Reader::PC);

        /** @var Upk_File_Element_Property_Array $rawDataObj */
        $rawDataObj = $this->searchElement('RawData');
        $rawDataObj->setRawItems($swfReader->getDataLength(), $swfReader);
    }

}
