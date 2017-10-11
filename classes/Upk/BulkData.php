<?php

/**
 * Class Upk_BulkData
 */
class Upk_BulkData
{
    const BULKDATA_StoreInSeparateFile = 0x01; // bulk stored in different file
    const BULKDATA_CompressedZlib = 0x02; // unknown name
    const BULKDATA_CompressedLzo = 0x10; // unknown name
    const BULKDATA_Unused = 0x20; // empty bulk block
    const BULKDATA_SeparateData = 0x40; // unknown name - bulk stored in a different place in the same file
    const BULKDATA_CompressedLzx = 0x80; // unknown name

    const BULKDATA_CompressedMask = 0x92;

    /** @var int */
    protected $flags = 0;

    /** @var int */
    protected $uncompressedSize = 0;

    /** @var int */
    protected $compressedSize = 0;

    /** @var int */
    protected $offsetToData = 0;

    /** @var Reader_Memory */
    protected $dataReader = null;

    /** @var bool */
    protected $debugFlag = false;

    /**
     * Upk_BulkData constructor.
     * @param Reader|null $reader
     * @param bool $debugFlag
     */
    public function __construct(Reader $reader = null, $debugFlag = false)
    {
        $this->debugFlag = $debugFlag;
        if (!is_null($reader)) {
            $this->read($reader);
        }
    }

    public function __clone()
    {
        $this->dataReader = clone $this->dataReader;
    }

    /**
     * @param int $flags
     * @param int $uncompressedSize
     * @param int $compressedSize
     * @param int $offsetToData
     * @param string $data
     */
    public function init($flags, $uncompressedSize, $compressedSize, $offsetToData, $data = '')
    {
        $this->flags = $flags;
        $this->uncompressedSize = $uncompressedSize;
        $this->compressedSize = $compressedSize;
        $this->offsetToData = $offsetToData;
        // TODO: For now this method is used for create empty BulkData, so byte order does not matter.
        // TODO: need to refactor it to be able set any byte order.
        $this->dataReader = new Reader_Memory($data, Reader::PC);
    }

    /**
     * @param Reader $reader
     */
    protected function read(Reader $reader)
    {
        if ($this->debugFlag) {
            echo '<br/>Offset: ' . sprintf('0x%08X', $reader->getPosition()) . '<br/>';
        }

        $this->flags = $reader->readDWord();
        $this->uncompressedSize = $reader->readDWord();
        $this->compressedSize = $reader->readDWord();
        $this->offsetToData = $reader->readDWord();

        if ($this->flags === 0x00) {
            // Uncompressed data placed right after header in the same file.
            // Read it.
            $data = $reader->readData($this->uncompressedSize);
            $this->dataReader = new Reader_Memory($data, $reader->getByteOrder());
        }

        if ($this->debugFlag) {
            echo sprintf("Flags : 0x%08X\nUnc.sz: 0x%08X\nCmp.sz: 0x%08X\nOffset: 0x%08X (Upk_Archive)\n",
                $this->flags, $this->uncompressedSize, $this->compressedSize, $this->offsetToData);
        }
    }


    /**
     * @param $byteOrder
     * @return string
     * @throws Exception
     */
    public function pack($byteOrder)
    {
        $reader = new Reader_Memory('', $byteOrder);

        $reader->writeDWord($this->flags);            // +0
        $reader->writeDWord($this->uncompressedSize); // +4
        $reader->writeDWord($this->compressedSize);   // +8
        $reader->writeDWord($this->offsetToData);     // +12
        // TODO: Implement data store for all possible flags
        if ($this->flags === 0x00) {
            $reader->writeData($this->dataReader->getData());
        }

        return $reader->getData();
    }

    /**
     * @return string
     */
    public function getData()
    {
        return $this->dataReader->getData();
    }

    /**
     * @param string $data
     * @throws Exception
     */
    public function setData($data)
    {
        if ($this->flags !== 0x00) {
            throw new Exception('Implemented for uncompressed data in the same file only.');
        }

        // TODO: Implement data store for all possible flags
        $this->flags = 0x0000;
        // TODO: need to refactor it to be able set any byte order.
        $this->dataReader = new Reader_Memory($data, Reader::PC);
        $this->uncompressedSize = $this->dataReader->getDataLength();
        $this->compressedSize = $this->uncompressedSize;
    }

    /**
     * @return bool
     */
    public function isCompressed()
    {
        return $this->flags & self::BULKDATA_CompressedMask;
    }

    /**
     * @return bool
     */
    public function isUnused()
    {
        return $this->flags & self::BULKDATA_Unused;
    }

    /**
     * @return bool
     */
    public function isStoredInSeparateFile()
    {
        return $this->flags & self::BULKDATA_StoreInSeparateFile;
    }

    /**
     * @return int
     */
    public function getUncompressedSize()
    {
        return $this->uncompressedSize;
    }

    /**
     * @return int
     */
    public function getCompressedSize()
    {
        return $this->compressedSize;
    }

    /**
     * @return int
     */
    public function getOffsetToData()
    {
        return $this->offsetToData;
    }

}
