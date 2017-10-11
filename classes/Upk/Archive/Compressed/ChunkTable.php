<?php

/**
 * Class Upk_Archive_Compressed_ChunkTable
 */
class Upk_Archive_Compressed_ChunkTable
{
    /** @var Reader_File */
    protected $rawReader = null;

    /** @var int */
    protected $compressedTableOffset = 0;

    /** @var int */
    protected $compressionFlags = 0;

    /** @var Upk_Archive_Compressed_Chunk[] */
    protected $compressedChunks = array();

    /** @var bool */
    protected $debugFlag = false;

    /** @var bool */
    protected $debugChunks = false;

    /**
     * Upk_Archive_Compressed_ChunkTable constructor.
     * @param Reader_File $reader
     * @param bool $debugFlag
     */
    public function __construct(Reader_File $reader, $debugFlag = false)
    {
        $this->rawReader = $reader;
        $this->compressedTableOffset = $reader->getPosition();
        $this->debugFlag = $debugFlag;
        $this->debugChunks = defined('DEBUG_COMPRESSED_CHUNKS');

        $this->readChunksData();
    }

    protected function readChunksData()
    {
        $reader = $this->rawReader;

        $this->compressionFlags = $reader->readDWord();
        if ($this->debugFlag || $this->debugChunks) {
            echo 'compressionFlags: ' . sprintf('%08X', $this->compressionFlags) . '<br/>';
        }

        $chunkCount = $reader->readDWord();
        if ($this->debugFlag || $this->debugChunks) {
            echo 'number of chunks: ' . $chunkCount . '<br/>';
        }

        $this->compressedChunks = array();
        for ($chunkNum = 0; $chunkNum < $chunkCount; $chunkNum++) {
            $uncompressedOffset = $reader->readDWord();
            $uncompressedSize = $reader->readDWord();
            $compressedOffset = $reader->readDWord();
            $compressedSize = $reader->readDWord();

            $chunk = new Upk_Archive_Compressed_Chunk($this, $compressedOffset, $compressedSize, $uncompressedOffset, $uncompressedSize, $this->debugChunks);
            $this->compressedChunks[$chunkNum] = $chunk;

            if ($this->debugFlag || $this->debugChunks) {
                echo 'Chunk #' . $chunkNum . ': ' .
                    'uncompressed (' . sprintf('0x%08X', $uncompressedOffset) . ': ' . $uncompressedSize . ' bytes); ' .
                    'compressed (' . sprintf('0x%08X', $compressedOffset) . ': ' . $compressedSize . ' bytes) ' . '<br/>';
            }
        }
    }

    public function writeChunksData(Reader_File $dstFileReader)
    {
        $dstFileReader->setPosition( $this->getChunk(0)->getCompressedOffset() );
        foreach ($this->compressedChunks as $chunk) {
            $chunk->writeChunk($dstFileReader);
        }

        // Fix table
        $dstFileReader->setPosition( $this->compressedTableOffset + 2*4 );
        foreach ($this->compressedChunks as $chunk) {
            $dstFileReader->writeDWord( $chunk->getUncompressedOffset() );
            $dstFileReader->writeDWord( $chunk->getUncompressedSize() );
            $dstFileReader->writeDWord( $chunk->getCompressedOffset() );
            $dstFileReader->writeDWord( $chunk->getCompressedSize() );
        }
    }

    /**
     * @return Reader_File
     */
    public function getReader()
    {
        return $this->rawReader;
    }

    /**
     * @return int
     */
    public function getCompressionFlags()
    {
        return $this->compressionFlags;
    }

    /**
     * @return bool
     */
    public function isCompressed()
    {
        return ($this->compressionFlags) && (count($this->compressedChunks) > 0);
    }

    /**
     * @return Upk_Archive_Compressed_Chunk
     */
    public function getLastChunk()
    {
        return $this->getChunk( $this->getChunkCount() - 1 );
    }

    /**
     * @param int $chunkNum
     * @return Upk_Archive_Compressed_Chunk
     */
    public function getChunk($chunkNum)
    {
        return $this->compressedChunks[$chunkNum];
    }

    /**
     * @return Upk_Archive_Compressed_Chunk[]
     */
    public function getChunks()
    {
        return $this->compressedChunks;
    }

    /**
     * @return int
     */
    public function getChunkCount()
    {
        return count($this->compressedChunks);
    }

    /**
     * @param int $position
     * @return Upk_Archive_Compressed_Chunk
     * @throws Exception
     */
    protected function getChunkByPosition($position)
    {
        foreach ($this->compressedChunks as $chunk) {
            if (($position >= $chunk->getUncompressedOffset())
                && ($position < $chunk->getUncompressedOffset() + $chunk->getUncompressedSize()))
            {
                return $chunk;
            }
        }

        throw new Exception('Can\'t find chunk for position: ' . sprintf('0x%08X', $position));
    }

    /**
     * @param int $position
     * @return Upk_Archive_Compressed_Block
     * @throws Exception
     */
    public function getBlockByPosition($position)
    {
        $chunk = $this->getChunkByPosition($position);
        $block = $chunk->getBlockByPosition($position);

        return $block;
    }

}
