<?php

/**
 * Class Upk_TextureBulkData
 */
class Upk_TextureBulkData extends Upk_BulkData
{
    /** @var int */
    protected $width = 0;

    /** @var int */
    protected $height = 0;

    /** @var string */
    protected $format = '';

    /**
     * Upk_TextureBulkData constructor.
     * @param Reader $reader
     * @param string $format
     * @param bool $debugFlag
     */
    public function __construct(Reader $reader, $format, $debugFlag = false)
    {
        $this->format = $format;

        parent::__construct($reader, $debugFlag);
    }

    protected function read(Reader $reader)
    {
        parent::read($reader);

        $this->width = $reader->readDWord();
        $this->height = $reader->readDWord();
    }

    public function pack($byteOrder)
    {
        return parent::pack($byteOrder) . Reader::packDWord($this->width, $byteOrder) .
            Reader::packDWord($this->height, $byteOrder);
    }

    /**
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * @param int $width
     */
    public function setWidth($width)
    {
        $this->width = $width;
    }

    /**
     * @param int $height
     */
    public function setHeight($height)
    {
        $this->height = $height;
    }

    /**
     * @return int
     */
    public function getByteOrder()
    {
        return $this->dataReader->getByteOrder();
    }

    /**
     * @return Reader_Memory
     */
    protected function getUnSwizzledData()
    {
        $dataReader = $this->dataReader;

        if (!$this->isStoredInSeparateFile() && !$this->isCompressed() && !$this->isUnused()) {
            $swizzler = Texture_Swizzler_Factory::getInstance($dataReader->getByteOrder());
            if (!is_null($swizzler)) {
                $swizzler->init($this->width, $this->height, $this->format, $dataReader);
                $dataReader = $swizzler->unSwizzle();
            }
        }

        return $dataReader;
    }

    /**
     * @param int $byteOrder
     */
    public function swizzle($byteOrder)
    {
        $dataReader = $this->getUnSwizzledData();

        if (!$this->isStoredInSeparateFile() && !$this->isCompressed() && !$this->isUnused()) {
            $swizzler = Texture_Swizzler_Factory::getInstance($byteOrder);
            if (!is_null($swizzler)) {
                $swizzler->init($this->width, $this->height, $this->format, $dataReader);
                $dataReader = $swizzler->swizzle();
            }
        }

        $this->dataReader = $dataReader;
    }

    /**
     * @return resource
     */
    public function getImage()
    {
        $className = Texture::getClassName($this->format);
        /** @var Texture $textureObj */
        $textureObj = new $className($this->width, $this->height, $this->getUnSwizzledData());
        return $textureObj->getImage();
    }

}
