<?php

/**
 * Class Upk_File_Font
 */
class Upk_File_Font extends Upk_File_Abstract
{

    /** @var int */
    private $cnt = 0;

    /** @var array */
    private $map = array();

    /**
     * @param Reader $reader
     */
    protected function readExtraData(Reader $reader)
    {
        $this->cnt = $reader->readDWord();

        for ($i=0; $i < $this->cnt; $i++) {
            $charCode = $reader->readWord();
            $index = $reader->readWord();
            $this->map[$charCode] = $index;
        }
    }

    /**
     * @param int $byteOrder
     * @return string
     */
    protected function packExtraData($byteOrder)
    {
        $reader = new Reader_Memory('', $byteOrder);

        $reader->writeDWord($this->cnt);

        foreach ($this->map as $charCode => $index) {
            $reader->writeWord($charCode);
            $reader->writeWord($index);
        }

        return $reader->getData();
    }

    public function getTexturesList()
    {
        /** @var Upk_File_Element_Property_Array $texturesArray */
        $texturesArray = $this->searchElement('Textures');
        return $texturesArray->getAllElements();
    }

    protected function toStringExtraData()
    {
        $br = "\n";

        $result = $br . '==================' . $br;

        $result .= 'Map elements: ' . $this->cnt . $br;

        $i = 0;
        foreach ($this->map as $charCode => $index) {
            $result .= sprintf('%4d', $i) . ': ' . sprintf('%04X => %04X', $charCode, $index) . '<br/>';
            $i++;
        }

        return $result;
    }

    /**
     * @param string $c UTF-16 symbol
     * @return Upk_Font_Character Character info
     */
    public function getSymbolInfoByChar($c)
    {
        $a = unpack('n', $c);
        $index = $this->map[ $a[1] ] ?? $a[1];
        /** @var Upk_File_Element_Property_Array $characters */
        $characters = $this->searchElement('Characters');
        if ($index > $characters->getElementsCount()) {
            $index = 0;
        }
        return $characters->getElement( $index );
    }

    /**
     * @param int $index
     * @return Upk_Font_Character Character info
     */
    public function getSymbolInfoByIndex($index)
    {
        return $this->searchElement('Characters')->getElement( $index );
    }

    /**
     * @return array
     */
    public function getMap()
    {
        return $this->map;
    }

    /**
     * @param array $map
     */
    public function setMap($map)
    {
        $this->cnt = count($map);
        $this->map = $map;
    }

}
