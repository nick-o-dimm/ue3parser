<?php

/**
 * Class Upk_File_List
 */
class Upk_File_List implements Upk_File_ElementInterface
{

    /** @var Upk_Archive */
    protected $upkArc = null;

    /** @var Upk_File_ElementInterface[] */
    private $elements = array();

    /** @var bool */
    private $debugFlag = false;

    /**
     * Upk_File_List constructor.
     * @param Reader $reader
     * @param Upk_Archive $upkArc
     * @param bool $debugFlag
     */
    public function __construct(Reader $reader, Upk_Archive $upkArc, $debugFlag = false)
    {
        $this->upkArc = $upkArc;
        $this->debugFlag = $debugFlag;
        $this->read($reader);
    }

    /**
     * @param Reader $reader
     * @throws Exception
     */
    public function read(Reader $reader)
    {
        do {
            $element = Upk_File_Element_Factory::getElement($reader, $this->upkArc, $this->debugFlag);
            $this->elements[] = $element;
        } while (!is_null($element));

        // delete NULL element
        array_pop($this->elements);
    }

    /**
     * @return int
     */
    public function getElementsCount()
    {
        return count($this->elements);
    }

    /**
     * @param int $elementNum
     * @return Upk_File_Element_Abstract|Upk_File_List
     */
    public function getElement($elementNum)
    {
        return $this->elements[$elementNum];
    }

    /**
     * Add new array element.
     * Warning! You should check type of element by yourself.
     * @param $element
     */
    public function addElement($element)
    {
        $this->elements[] = $element;
    }

    /**
     * @param string $elementName
     * @return bool|Upk_File_Element_Abstract|Upk_File_Element_Property_Array
     */
    public function searchElement($elementName)
    {
        for ($elemNum=0; $elemNum < $this->getElementsCount(); $elemNum++) {
            $elemObj = $this->getElement($elemNum);
            if ($elemObj->getElemName() === $elementName) {
                return $elemObj;
            }
        }

        return false;
    }

    /**
     * Save parsed file data to packed file.
     * @param int $byteOrder
     * @param null|Upk_Archive $upkArc
     * @return string
     */
    public function pack($byteOrder, Upk_Archive $upkArc = null)
    {
        $result = '';

        foreach ($this->elements as $element) {
            $result .= $element->pack($byteOrder, $upkArc);
        }

        // add 'None' element
        $noneId = $upkArc->getNameTable()->getIdByName('None');
        $result .= Reader::packInt64($noneId, $byteOrder);

        return $result;
    }

    /**
     * @param string $indentStr
     * @return string
     */
    public function toString($indentStr = '')
    {
        $result = $indentStr . "(\n";
        foreach ($this->elements as $element) {
            $result .= $element->toString($indentStr . ' ') . "\n";
        }
        $result .= $indentStr . ")";

        return $result;
    }

}
