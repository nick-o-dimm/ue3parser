<?php

/**
 * Class Upk_File_Abstract
 */
abstract class Upk_File_Abstract implements Upk_File_ElementInterface
{

    /** @var Upk_Archive */
    protected $upkArc = null;

    /** @var int File ID */
    protected $fileId = 0;

    /** @var Upk_File_List */
    protected $list = null;

    /** @var int */
    protected $byteOrder = Reader::PC;

    /** @var bool */
    protected $debugFlag = false;

    /** @var int */
    protected $extraDataOfs = 0;

    /** @var array */
    protected $hardLinks = array();

    /**
     * Upk_File constructor.
     * @param Reader $reader
     * @param Upk_Archive $upkArc
     * @param bool $debugFlag
     */
    public function __construct(Reader $reader, Upk_Archive $upkArc, $debugFlag = false)
    {
        $this->upkArc = $upkArc;
        $this->byteOrder = $reader->getByteOrder();
        $this->debugFlag = $debugFlag;
        $this->read($reader);
    }

    /**
     * Parse packed file
     * @param Reader $reader
     * @throws Exception
     */
    public function read(Reader $reader)
    {
        if ($this->debugFlag) {
            echo 'Offset: ' . sprintf('0x%08X', $reader->getPosition()) . '<br/>';
        }

        $this->fileId = $reader->readSignedDWord();

        if ($this->debugFlag) {
            echo 'FileID: ' . $this->fileId . '<br/>';
        }

        $this->list = new Upk_File_List($reader, $this->upkArc, $this->debugFlag);

        if ($this->debugFlag) {
            echo '<br/>==================<br/>';
        }

        $this->readExtraData($reader);

        if ($reader->getDataLength() !== $reader->getPosition()) {
            $currentPos = $reader->getPosition();
            $errorMsg = 'Error. Unknown extra data (' . ($reader->getDataLength() - $currentPos) . ' bytes)'
                . '; Offset: ' . sprintf('0x%08X', $currentPos) ;
            throw new Exception($errorMsg);
        }
    }

    /**
     * Add new array element.
     * Warning! You should check type of element by yourself.
     * @param $element
     */
    protected function addElement($element)
    {
        $this->list->addElement($element);
    }

    /**
     * Read extra data (soundNodeWave, textures, etc.)
     *
     * @param Reader $reader
     */
    abstract protected function readExtraData(Reader $reader);

    /**
     * @param Reader $reader
     * @return string
     * @throws Exception
     */
    protected function readBlockWithHardLink(Reader $reader)
    {
        if ($this->debugFlag) {
            echo '<br/>Offset: ' . sprintf('0x%08X', $reader->getPosition()) . '<br/>';
        }

        $dummy = $reader->readDWord();
        if ($dummy !== 0x0000) {
            throw new Exception('Can\'t parse extra data. (Dummy word = ' . sprintf('%08X', $dummy) . ')');
        }
        if ($this->debugFlag) {
            echo ' Dummy: ' . sprintf('0x%08X', $dummy) . '<br/>';
        }

        $blockLength1 = $reader->readDWord();
        $blockLength2 = $reader->readDWord();
        if ($blockLength1 !== $blockLength2) {
            throw new Exception('Can\'t parse extra data. (Different block lengths)');
        }
        if ($this->debugFlag) {
            echo 'Length: ' . sprintf('0x%08X', $blockLength1) . ' (hex)<br/>';
            echo 'Length: ' . $blockLength1 . ' (dec)<br/>';
        }

        $hardLink = $reader->readDWord();
        if ($this->debugFlag) {
            echo '  Link: ' . sprintf('0x%08X', $hardLink) . '<br/>';
        }

        return $reader->readData($blockLength1);
    }

    /**
     * Save parsed file data to packed file.
     * @param int $byteOrder
     * @param null|Upk_Archive $upkArc
     * @return string
     */
    public function pack($byteOrder, Upk_Archive $upkArc = null)
    {
        if (is_null($upkArc)) {
            $upkArc = $this->upkArc;
        }

        $result = '';

        $result .= Reader::packDWord($this->fileId, $byteOrder);
        $result .= $this->list->pack($byteOrder, $upkArc);

        $this->hardLinks = array();
        $this->extraDataOfs = strlen($result);

        $result .= $this->packExtraData($byteOrder);

        return $result;
    }

    /**
     * @param int $ofs
     */
    protected function addHardLink($ofs)
    {
        $this->hardLinks[] = $this->extraDataOfs + $ofs;
    }

    /**
     * @return array
     */
    public function getHardLinks()
    {
        return $this->hardLinks;
    }

    /**
     * Read extra data (soundNodeWave, Texture2D, etc.)
     * @param int $byteOrder
     * @return string
     */
    abstract protected function packExtraData($byteOrder);

    /**
     * @param string $elementName
     * @return bool|Upk_File_Element_Abstract|Upk_File_List|Upk_File_Element_Property_Array
     */
    public function searchElement($elementName)
    {
        return $this->list->searchElement($elementName);
    }

    public function __toString()
    {
        return $this->toString();
    }

    /**
     * @param string $indentStr
     * @return string
     */
    public function toString($indentStr = '')
    {
        $result = get_class($this)."\n";
        $result .= 'FileID='.$this->fileId."\n";

        $result .= $this->list->toString($indentStr);

        $result .= $this->toStringExtraData();

        $result .= '--------------------------------' . "\n";

        return $result;
    }

    /**
     * @return string
     */
    abstract protected function toStringExtraData();

}
