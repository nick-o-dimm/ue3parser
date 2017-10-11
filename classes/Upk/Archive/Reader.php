<?php

/**
 * Class Upk_Archive_Reader
 * This class allows to users work with compressed archive as with uncompressed Reader_File.
 * User even may not know that this is archive. He can read/write to any position, after that
 * archive will be recompressed and rebuilt.
 */
class Upk_Archive_Reader extends Reader
{
    /** @var Upk_Archive_Compressed_ChunkTable */
    protected $chunkTable = null;

    /** @var int */
    protected $position = 0;

    /** @var Upk_Archive_Compressed_Block */
    private $currentBlock = null;
    /** @var int */
    protected $currentBlockSize = -1;
    /** @var int */
    protected $currentBlockStart = -1;
    /** @var int */
    protected $currentBlockEnd = -1;

    /**
     * Upk_Archive_Reader constructor.
     * @param Upk_Archive_Compressed_ChunkTable $chunkTable
     */
    public function __construct(Upk_Archive_Compressed_ChunkTable $chunkTable)
    {
        $this->chunkTable = $chunkTable;
        $this->byteOrder = $chunkTable->getReader()->getByteOrder();

        parent::__construct();
    }

    protected function setDataLength()
    {
        $chunk = $this->chunkTable->getLastChunk();
        $this->dataLength = $chunk->getUncompressedOffset() + $chunk->getUncompressedSize();
    }

    /**
     * @return bool
     */
    private function validatePosition()
    {
        return ($this->position >= $this->currentBlockStart) && ($this->position < $this->currentBlockEnd);
    }

    public function readData($length)
    {
        $result = '';

        while (true) {
            // check for valid block
            if ($this->validatePosition())
            {
                // we already load needed compressed block.
                // lets copy data from it

                $toCopy = $this->currentBlockEnd - $this->position; // available size
                if ($toCopy > $length) {
                    $toCopy = $length; // shrink by required size
                }

                $result .= $this->currentBlock->readData($this->position, $toCopy);

                // advance pointers/counters
                $this->position += $toCopy;
                $length -= $toCopy;
                if ($length === 0) {
                    return $result; // copied enough
                }
            }

            // here: data/size points outside of currentBlock, so we need to search new one.
            $this->currentBlock = $this->chunkTable->getBlockByPosition($this->position);
            $this->currentBlockStart = $this->currentBlock->getUncompressedOffset();
            $this->currentBlockEnd = $this->currentBlock->getUncompressedOffset() + $this->currentBlock->getUncompressedSize();

            if (!$this->validatePosition()) {
                throw new Exception('Can\'t prepare data for position: ' . sprintf('0x%08X', $this->position));
            }
        }
    }

    public function writeData($data)
    {
        $length = strlen($data);
        $currentPos = 0;

        while (true) {
            // check for valid block
            if ($this->validatePosition())
            {
                // we already load needed compressed block.
                // lets copy data from it

                $toCopy = $this->currentBlockEnd - $this->position; // available size
                if ($toCopy > $length) {
                    $toCopy = $length; // shrink by required size
                }

                $this->currentBlock->writeData($this->position, substr($data, $currentPos, $toCopy), $toCopy);

                // advance pointers/counters
                $this->position += $toCopy;
                $currentPos += $toCopy;
                $length -= $toCopy;
                if ($length === 0) {
                    return; // copied enough
                }
            }

            // here: data/size points outside of currentBlock, so we need to search new one.
            if ($this->position === $this->dataLength) {
                $this->addSpace($length);
                $this->setDataLength();
            }

            $this->currentBlock = $this->chunkTable->getBlockByPosition($this->position);
            $this->currentBlockStart = $this->currentBlock->getUncompressedOffset();
            $this->currentBlockEnd = $this->currentBlock->getUncompressedOffset() + $this->currentBlock->getUncompressedSize();

            if (!$this->validatePosition()) {
                throw new Exception('Can\'t prepare data for position: ' . sprintf('0x%08X', $this->position));
            }
        }
    }

    private function addSpace($length)
    {
        $this->chunkTable->getLastChunk()->addSpace($length);
    }

    public function getPosition()
    {
        return $this->position;
    }

    public function setPosition($newPosition)
    {
        $this->position = $newPosition;
    }

    public function openFileForRead()
    {
        $this->chunkTable->getReader()->openFileForRead();
    }

    public function openFileForWrite()
    {
        $this->chunkTable->getReader()->openFileForWrite();
    }

    public function closeFile()
    {
        $this->chunkTable->getReader()->closeFile();
    }

    public function setEndPosition()
    {
        $this->position = $this->dataLength;
    }

}
