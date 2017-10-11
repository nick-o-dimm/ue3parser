<?php

/**
 * Class Upk_File_Element_Property_Array
 */
class Upk_File_Element_Property_Array extends Upk_File_Element_Abstract
{

    /** @var int */
    protected $itemsCount = 0;

    /** @var mixed[] */
    protected $items = array();

    /** @var Upk_File_Element_ItemReader */
    protected $itemReader = null;

    /**
     * @return int
     */
    public function getElementsCount()
    {
        return $this->itemsCount;
    }

    /**
     * @param int $elementNum
     * @return mixed
     */
    public function getElement($elementNum)
    {
        return $this->items[$elementNum];
    }

    /**
     * @return mixed
     */
    public function getAllElements()
    {
        return $this->items;
    }

    /**
     * @param Reader $reader
     */
    protected function readElement(Reader $reader)
    {
        $this->itemsCount = $reader->readDWord();

        if ($this->debugFlag) {
            echo '   Cnt: ' . $this->itemsCount . ' elements<br/>';
        }

        $this->initItemsReader();

        for ($i = 0; $i < $this->itemsCount; $i++) {
            $this->items[] = $this->itemReader->readItem($reader);
        }
    }

    /**
     * Init array items reader
     */
    protected function initItemsReader()
    {
        $baseName = 'Upk_File_Element_ItemReader_';
        $className = $baseName . $this->elemName;

        if (!class_exists($className)) {
            // Default type
            $itemType = 'List';

            $totalSize = $this->elemDataSize - 4;

            if ($totalSize > 0) {
                $itemSize = intdiv($totalSize, $this->itemsCount);

                // Try to guess type
                $isFixedSize = ($totalSize % $itemSize) === 0;
                if ($isFixedSize) {
                    if ($itemSize === 1) {
                        $itemType = 'Byte';
                    } elseif ($itemSize === 2) {
                        $itemType = 'Word';
                    } elseif ($itemSize === 4) {
                        $itemType = 'Dword';
                    }
                }
            }

            $className = $baseName . $itemType;
        }

        if ($this->debugFlag) {
            echo 'Reader: ' . $className . '<br/>';
        }
        $this->itemReader = new $className($this->upkArc, $this->debugFlag);
    }

    /**
     * @param int $byteOrder
     * @param Upk_Archive $upkArc
     * @return string
     */
    protected function packElement($byteOrder, Upk_Archive $upkArc)
    {
        $packedData = Reader::packDWord($this->itemsCount, $byteOrder);
        $packedData .= $this->packRawItems($byteOrder, $upkArc);
        $dataSize = strlen($packedData);
        return Reader::packInt64($dataSize, $byteOrder) . $packedData;
    }

    /**
     * Remove all array elements
     */
    public function clear()
    {
        $this->items = array();
        $this->itemsCount = 0;
    }

    /**
     * Add new array element.
     * Warning! You should check type of element by yourself.
     * @param $item
     */
    public function addElement($item)
    {
        $this->items[$this->itemsCount] = $item;
        $this->itemsCount++;
    }

    /**
     * Remove element
     * Warning! Array indexes will not rebuild!
     * Use this method only to trim last elements!
     * @param int $elemNum
     */
    public function removeElement($elemNum)
    {
        unset($this->items[$elemNum]);
        $this->itemsCount--;
    }

    /**
     * @return string
     */
    public function getRawItems()
    {
        return $this->packRawItems();
    }

    /**
     * @param int|null $byteOrder
     * @param Upk_Archive|null $upkArc
     * @return string
     */
    public function packRawItems($byteOrder = null, Upk_Archive $upkArc = null)
    {
        if (is_null($upkArc)) {
            $upkArc = $this->upkArc;
        }
        if (is_null($byteOrder)) {
            $byteOrder = $this->byteOrder;
        }

        $packedData = '';
        for ($i = 0; $i < $this->itemsCount; $i++) {
            $packedData .= $this->itemReader->packItem($this->items[$i], $byteOrder, $upkArc);
        }

        return $packedData;
    }

    /**
     * @param $cnt
     * @param Reader $reader
     */
    public function setRawItems($cnt, Reader $reader)
    {
        $this->itemsCount = $cnt;
        $this->items = array();

        for ($i = 0; $i < $this->itemsCount; $i++) {
            $this->items[] = $this->itemReader->readItem($reader);
        }
    }

    /**
     * @param string $indentStr
     * @return string
     */
    public function toString($indentStr = '')
    {
        $readerName = substr(get_class($this->itemReader), 28);
        $result = $indentStr . $this->elemName . '=ArrayData(' . $readerName . ')[';

        if ($this->itemReader instanceof Upk_File_Element_ItemReader_List) {
            for ($i=0; $i<$this->itemsCount; $i++) {
                $result .= "\n" . $this->items[$i]->toString($indentStr . ' ');
            }
            if ($this->itemsCount > 0) {
                $result .= "\n" . $indentStr;
            }
        }
        elseif ($this->itemReader instanceof Upk_File_Element_ItemReader_Textures) {
            for ($i=0; $i<$this->itemsCount; $i++) {
                $result .= "\n" . $indentStr . ' ' . $this->items[$i];
            }
            if ($this->itemsCount > 0) {
                $result .= "\n" . $indentStr;
            }
        }
        else {
             $result .= $this->itemsCount . ' elements';
        }

        $result .= ']';

        return $result;
    }

}
