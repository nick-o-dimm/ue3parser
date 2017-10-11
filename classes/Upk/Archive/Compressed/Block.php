<?php

/**
 * Class Upk_Archive_Compressed_Block
 */
class Upk_Archive_Compressed_Block
{
    const COMPRESSION_ZLIB = 0x01;
    const COMPRESSION_LZO = 0x02;
    const COMPRESSION_LZMA = 0x08;

    /** @var Upk_Archive_Compressed_Chunk */
    private $parentChunk = null;

    /** @var int */
    private $compressedOffset = 0;

    /** @var int */
    private $compressedSize = 0;

    /** @var Reader_Memory */
    private $compressedData = null;

    /** @var int */
    private $uncompressedOffset = 0;

    /** @var int */
    private $uncompressedSize = 0;

    /** @var Reader_Memory */
    private $uncompressedData = null;

    /** @var bool */
    private $isLoaded = false;

    /** @var bool */
    private $isDecompressed = false;

    /** @var bool */
    private $isModified = false;

    /** @var bool */
    private $debugFlag = false;

    /**
     * Upk_Archive_Compressed_Block constructor.
     * @param Upk_Archive_Compressed_Chunk $parentChunk
     * @param int $compressedOffset
     * @param int $compressedSize
     * @param int $uncompressedOffset
     * @param int $uncompressedSize
     * @param bool $debugFlag
     * @param bool $createEmptyBlock
     */
    public function __construct(Upk_Archive_Compressed_Chunk $parentChunk, $compressedOffset, $compressedSize, $uncompressedOffset, $uncompressedSize, $debugFlag = false, $createEmptyBlock = false)
    {
        $this->parentChunk = $parentChunk;

        $this->compressedOffset = $compressedOffset;
        $this->compressedSize = $compressedSize;
        $this->compressedData = null;

        $this->uncompressedOffset = $uncompressedOffset;
        $this->uncompressedSize = $uncompressedSize;
        $this->uncompressedData = null;

        $this->isLoaded = false;
        $this->isDecompressed = false;
        $this->isModified = false;
        $this->debugFlag = $debugFlag;

        if ($createEmptyBlock) {
            $this->isLoaded = true;
            $this->isDecompressed = true;
            $this->isModified = true;
            $this->uncompressedData = new Reader_Memory('', $this->getReader()->getByteOrder());
        }
    }

    /**
     * @return Reader_File
     */
    private function getReader()
    {
        return $this->parentChunk->getReader();
    }

    /**
     * @return int
     */
    public function getCompressedOffset()
    {
        return $this->compressedOffset;
    }

    /**
     * @param int $compressedOffset
     */
    public function setCompressedOffset($compressedOffset)
    {
        $this->compressedOffset = $compressedOffset;
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
    public function getUncompressedOffset()
    {
        return $this->uncompressedOffset;
    }

    /**
     * @return int
     */
    public function getUncompressedSize()
    {
        return $this->uncompressedSize;
    }

    public function addSpace($length)
    {
        if (!$this->isDecompressed) {
            $this->decompressBlock();
        }

        if ($this->debugFlag) {
            echo 'Add free space to block: ' . $length . ' bytes<br/>';
        }

        $r = $this->uncompressedData;
        $r->setPosition($this->uncompressedSize);
        $r->writeData( str_repeat("\0", $length) );
        $r->setPosition(0);

        $this->isModified = true;
        $this->uncompressedSize += $length;
    }

    /**
     * @param int $offset
     * @param int $length
     * @return bool
     * @throws Exception
     */
    private function validate($offset, $length)
    {
        // Validate offset and length
        if (($offset < $this->uncompressedOffset) || ($offset >= $this->uncompressedOffset + $this->uncompressedSize)) {
            throw new Exception('Position ' . sprintf('0x%08X', $offset) . ' is out of bounds for current block.');
        }
        if ($offset + $length > $this->uncompressedOffset + $this->uncompressedSize) {
            throw new Exception('Too big length for current block.');
        }

        return true;
    }

    public function readData($offset, $length)
    {
        $this->validate($offset, $length);
        if (!$this->isDecompressed) {
            $this->decompressBlock();
        }

        $this->uncompressedData->setPosition($offset - $this->uncompressedOffset);
        return $this->uncompressedData->readData($length);
    }

    public function writeData($offset, $data, $length)
    {
        $this->validate($offset, $length);
        if (!$this->isDecompressed) {
            $this->decompressBlock();
        }

        $this->uncompressedData->setPosition($offset - $this->uncompressedOffset);
        $this->isModified = true;
        $this->uncompressedData->writeData($data);
    }

    private function decompressBlock()
    {
        if (!$this->isLoaded) {
            $this->loadCompressedBlock();
        }
        if ($this->debugFlag) {
            echo 'Decompress block: ' . sprintf('0x%08X', $this->uncompressedOffset) . '<br/>';
        }

        $compressionFlags = $this->parentChunk->getCompressionFlags();

        if ($this->compressedSize === $this->uncompressedSize) {
            $data = $this->compressedData->getData();
        }
        elseif ($compressionFlags === self::COMPRESSION_ZLIB) {
            // ZLIB
            $data = zlib_decode($this->compressedData->getData());
        }
        elseif ($compressionFlags === self::COMPRESSION_LZO) {
            // LZO
            $lzoData = Reader::packDWord(Upk_Archive::UPK_MAGIC, Reader::PC) .
                Reader::packDWord(0x20000, Reader::PC) .
                Reader::packDWord($this->compressedSize, Reader::PC) .
                Reader::packDWord($this->uncompressedSize, Reader::PC) .
                Reader::packDWord($this->compressedSize, Reader::PC) .
                Reader::packDWord($this->uncompressedSize, Reader::PC) .
                $this->compressedData->getData();

            $compression = Compression::getInstance();
            $data = $compression->lzoDecompress($lzoData);
        }
        elseif (defined('GAME_MASS_EFFECT') && ($compressionFlags === self::COMPRESSION_LZMA)) {
            // LZMA compression in Mass Effect
            $lzmaData = $this->compressedData->readData(5) . Reader::packDWord($this->uncompressedSize, Reader::PC) .
                Reader::packDWord(0, Reader::PC) . $this->compressedData->readRestData();
            $compression = Compression::getInstance();
            $data = $compression->lzmaDecompress($lzmaData);
        }
        else {
            throw new Exception('Unknown compression flags: ' . sprintf('0x%08X', $compressionFlags));
        }

        if (strlen($data) !== $this->uncompressedSize) {
            throw new Exception('Incorrect uncompressed block length. ('.strlen($data).':'.$this->uncompressedSize.')');
        }

        $this->uncompressedData = new Reader_Memory($data, $this->compressedData->getByteOrder());

        $this->isDecompressed = true;
    }

    private function loadCompressedBlock()
    {
        if ($this->debugFlag) {
            echo 'Load compressed block: ' . sprintf('0x%08X', $this->uncompressedOffset) . '<br/>';
        }

        $reader = $this->getReader();
        $reader->setPosition($this->compressedOffset);
        $this->compressedData = new Reader_Memory($reader->readData($this->compressedSize), $reader->getByteOrder());

        $this->isLoaded = true;
    }

    private function compressBlock()
    {
        if ($this->debugFlag) {
            echo 'Compress modified block: ' . sprintf('0x%08X', $this->uncompressedOffset) . '<br/>';
        }

        $compressionFlags = $this->parentChunk->getCompressionFlags();

        if ($compressionFlags === self::COMPRESSION_ZLIB) {
            // ZLIB
            $compressedData = zlib_encode($this->uncompressedData->getData(), ZLIB_ENCODING_DEFLATE);
        }
        elseif ($compressionFlags === self::COMPRESSION_LZO) {
            // LZO compression
            $compression = Compression::getInstance();
            $tmpCompressedData = $compression->lzoCompress($this->uncompressedData->getData());

            // Skip header.
            $compressedData = substr($tmpCompressedData, 6*4);
        }
        elseif (defined('GAME_MASS_EFFECT') && ($compressionFlags === self::COMPRESSION_LZMA)) {
            // LZMA compression in Mass Effect
            $compression = Compression::getInstance();
            $tmpCompressedData = $compression->lzmaCompress($this->uncompressedData->getData());

            // Skip part of header.
            $compressedData = substr($tmpCompressedData, 0, 5) . substr($tmpCompressedData, 5+2*4);
        }
        else {
            throw new Exception('Unknown compression flags: ' . sprintf('0x%08X', $compressionFlags));
        }

        if (strlen($compressedData) >= $this->uncompressedSize) {
            $compressedData = $this->uncompressedData->getData();
        }

        $this->compressedData = new Reader_Memory($compressedData, $this->getReader()->getByteOrder());
        $this->compressedSize = $this->compressedData->getDataLength();
    }

    public function writeBlock(Reader_File $dstFileReader)
    {
        if (!$this->isLoaded) {
            $this->loadCompressedBlock();
        }
        elseif ($this->isModified) {
            $this->compressBlock();
            $this->isModified = false;
        }

        $this->compressedOffset = $dstFileReader->getPosition();
        $dstFileReader->writeData( $this->compressedData->getData() );
    }

}
