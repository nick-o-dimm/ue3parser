<?php

/**
 * Class Upk_Archive_Compressed_Chunk
 */
class Upk_Archive_Compressed_Chunk
{
    /** @var Upk_Archive_Compressed_ChunkTable */
    private $parentChunkTable = null;

    /** @var int */
    private $compressedOffset = 0;

    /** @var int */
    private $compressedSize = 0;

    /** @var int */
    private $uncompressedOffset = 0;

    /** @var int */
    private $uncompressedSize = 0;

    /** @var int */
    private $blockSize = 0;

    /** @var bool */
    private $isLoaded = false;

    /** @var bool */
    private $debugFlag = false;

    /** @var Upk_Archive_Compressed_Block[] */
    private $compressedBlocks = array();

    /**
     * Upk_Archive_Compressed_Chunk constructor.
     * @param Upk_Archive_Compressed_ChunkTable $parentChunkTable
     * @param int $compressedOffset
     * @param int $compressedSize
     * @param int $uncompressedOffset
     * @param int $uncompressedSize
     * @param bool $debugFlag
     */
    public function __construct(Upk_Archive_Compressed_ChunkTable $parentChunkTable, $compressedOffset, $compressedSize, $uncompressedOffset, $uncompressedSize, $debugFlag = false)
    {
        $this->parentChunkTable = $parentChunkTable;

        $this->compressedOffset = $compressedOffset;
        $this->compressedSize = $compressedSize;
        $this->uncompressedOffset = $uncompressedOffset;
        $this->uncompressedSize = $uncompressedSize;

        $this->compressedBlocks = array();
        $this->blockSize = 0;

        $this->debugFlag = $debugFlag;
        $this->isLoaded = false;
    }

    /**
     * @return Reader_File
     */
    public function getReader()
    {
        return $this->parentChunkTable->getReader();
    }

    /**
     * @return int
     */
    public function getCompressionFlags()
    {
        return $this->parentChunkTable->getCompressionFlags();
    }

    /**
     * @return int
     */
    public function getCompressedOffset()
    {
        return $this->compressedOffset;
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

    public function getBlockByPosition($position)
    {
        if (!$this->isLoaded) {
            $this->readBlocks();
        }

        foreach ($this->compressedBlocks as $block) {
            if (($position >= $block->getUncompressedOffset())
                && ($position < $block->getUncompressedOffset() + $block->getUncompressedSize()))
            {
                return $block;
            }
        }

        throw new Exception('Can\'t find block for position: ' . sprintf('0x%08X', $position));
    }

    /**
     * @return Upk_Archive_Compressed_Block
     */
    private function getLastBlock()
    {
        $blocksCount = count($this->compressedBlocks);
        return $this->compressedBlocks[$blocksCount - 1];
    }

    public function addSpace($length)
    {
        if (!$this->isLoaded) {
            $this->readBlocks();
        }

        // Set $block with last existed block
        $block = $this->getLastBlock();

        while ($length > 0) {
            $availableSize = $this->blockSize - $block->getUncompressedSize();

            // assert($availableSize >= 0); // check for block overflow.
            if ($availableSize <= 0) {
                $newUncompressedOffset = $block->getUncompressedOffset() + $block->getUncompressedSize();
                // Create empty block
                $block = new Upk_Archive_Compressed_Block($this, 0, 0, $newUncompressedOffset, 0, $this->debugFlag, true);
                $this->compressedBlocks[] = $block;
                $availableSize = $this->blockSize;
            }

            $processLength = ($length > $availableSize) ? $availableSize : $length;

            $block->addSpace($processLength);
            $this->uncompressedSize += $processLength;
            $length -= $processLength;
        }
    }

    private function readBlocks()
    {
        if ($this->debugFlag) {
            echo 'Load chunk: ' . sprintf('0x%08X', $this->compressedOffset) . '<br/>';
        }

        $reader = $this->getReader();
        $reader->setPosition($this->compressedOffset);

        if (($dwMagic = $reader->readDWord()) !== Upk_Archive::UPK_MAGIC) {
            throw new Exception('Wrong \'magic\' in chunk header: 0x' . sprintf('%08X', $dwMagic));
        }

        $this->blockSize = $reader->readDWord();

        $totalCompressedSize = $reader->readDWord();
        $totalUncompressedSize = $reader->readDWord();

        // Read block sizes from header
        $compressedOffset = 0; // We don't know yet which size header is, so save relative position and fix later.
        $compressedSizeCounter = 0;
        $uncompressedOffset = $this->uncompressedOffset;
        $uncompressedSizeCounter = 0;
        $this->compressedBlocks = array();

        while (($compressedSizeCounter < $totalCompressedSize) && ($uncompressedSizeCounter < $totalUncompressedSize)) {
            $blockCompressedSize = $reader->readDWord();
            $blockUncompressedSize = $reader->readDWord();

            // Create new block. Note that we should fix it's real $compressedOffset!
            $block = new Upk_Archive_Compressed_Block($this, $compressedOffset, $blockCompressedSize, $uncompressedOffset, $blockUncompressedSize, $this->debugFlag);
            $this->compressedBlocks[] = $block;

            $compressedSizeCounter += $blockCompressedSize;
            $compressedOffset += $blockCompressedSize;
            $uncompressedSizeCounter += $blockUncompressedSize;
            $uncompressedOffset += $blockUncompressedSize;
        }

        if ($uncompressedSizeCounter !== $totalUncompressedSize) {
            $this->compressedBlocks = array();
            throw new Exception('Incorrect length of the read blocks.');
        }

        // Set real compressed offsets in blocks
        $compressedOffset = $reader->getPosition();
        foreach ($this->compressedBlocks as $block) {
            $block->setCompressedOffset($block->getCompressedOffset() + $compressedOffset);
        }

        $this->isLoaded = true;
    }

    public function writeChunk(Reader_File $dstFileReader)
    {
        if (!$this->isLoaded) {
            $this->readBlocks();
        }

        $this->compressedOffset = $dstFileReader->getPosition();

        // Skip chunk header. Write it later.
        $chunkHeaderSize = 4*4 + 2*4*count($this->compressedBlocks);
        $dstFileReader->writeData( str_repeat("\0", $chunkHeaderSize) );

        $blocksTable = array();
        $totalCompressedSize = 0;
        $totalUncompressedSize = 0;
        foreach ($this->compressedBlocks as $block) {
            $block->writeBlock($dstFileReader);
            $totalCompressedSize += ($blockCompressedSize = $block->getCompressedSize());
            $totalUncompressedSize += ($blockUncompressedSize = $block->getUncompressedSize());
            $blocksTable[] = array($blockCompressedSize, $blockUncompressedSize);
        }
        $saveEndPosition = $dstFileReader->getPosition();
        $this->compressedSize = $chunkHeaderSize + $totalCompressedSize;

        if ($totalUncompressedSize !== $this->uncompressedSize) {
            throw new Exception('Incorrect total uncompressed size of the written blocks.');
        }
        if (($saveEndPosition - $this->compressedOffset) !== $this->compressedSize) {
            throw new Exception('Incorrect total compressed size of the written blocks.');
        }

        // Write chunk header.
        $dstFileReader->setPosition($this->compressedOffset);

        $dstFileReader->writeDWord(Upk_Archive::UPK_MAGIC);
        $dstFileReader->writeDWord($this->blockSize);
        $dstFileReader->writeDWord($totalCompressedSize);
        $dstFileReader->writeDWord($totalUncompressedSize);
        foreach ($blocksTable as $item) {
            $dstFileReader->writeDWord($item[0]);
            $dstFileReader->writeDWord($item[1]);
        }

        $dstFileReader->setPosition($saveEndPosition);
    }

}
