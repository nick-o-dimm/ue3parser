<?php

/**
 * Class Upk_Archive
 * Archive manager
 */
class Upk_Archive
{

    const UPK_MAGIC = 0x9E2A83C1;

    /** @var bool */
    private $debugFlag = false;

    /** @var Upk_Archive_Reader|Reader_File */
    private $upkReader = null;

    /** @var int */
    private $arcVer = 0;
    /** @var int */
    private $arcLicenseVer = 0;
    /** @var int */
    private $headersSize = 0;
    /** @var string */
    private $packageGroup = '';
    /** @var int Save position for writeHeader() method */
    private $packageFlagsPos = 0;
    /** @var int */
    private $packageFlags = 0;
    /** @var array */
    private $tablesInfo = array();
    /** @var int[] */
    private $packageGuid = array();
    /** @var array */
    private $generations = array();
    /** @var int */
    private $engineVer = 0;
    /** @var int */
    private $cookerVer = 0;

    /** @var int Save position for writeHeader() method */
    private $compressionPos = 0;
    /** @var Upk_Archive_Compressed_ChunkTable */
    private $compressedChunkTable = null;

    /** @var Upk_Archive_Table_Name */
    private $nameTable = null;
    /** @var Upk_Archive_Table_Import */
    private $importTable = null;
    /** @var Upk_Archive_Table_Export */
    private $exportTable = null;

    /** @var array */
    private $freeBlocks = array();

    /**
     * Upk_Archive constructor.
     * @param string $pathToFile
     * @param int $byteOrder
     * @param bool|false $debugFlag
     * @throws Exception
     */
    public function __construct($pathToFile, $byteOrder = Reader::PC, $debugFlag = false)
    {
        $this->debugFlag = $debugFlag;

        $this->upkReader = new Reader_File($pathToFile, $byteOrder);
        $this->upkReader->openFileForRead();

        try {
            $this->parseHeader();
        }
        catch (Exception $e) {
            $this->upkReader->closeFile();
            throw $e;
        }

        if ($this->isCompressed()) {
            // replace Reader_File with special reader for compressed UE3 archives
            $this->upkReader = new Upk_Archive_Reader($this->compressedChunkTable);
        }

        $this->nameTable = new Upk_Archive_Table_Name($this, $this->tablesInfo['nameTable']);
        $this->importTable = new Upk_Archive_Table_Import($this, $this->tablesInfo['importTable']);
        $this->exportTable = new Upk_Archive_Table_Export($this, $this->tablesInfo['exportTable']);

        $this->upkReader->closeFile();
    }

    protected function parseHeader()
    {
        $br = '<br/>';

        $upkReader = $this->upkReader;

        $dwMagic = $upkReader->readDWord();
        $strMagic = sprintf('%08X', $dwMagic);
        if ($dwMagic !== self::UPK_MAGIC) {
            throw new Exception('Wrong \'magic\' in package: 0x' . $strMagic);
        }
        if ($this->debugFlag) {
            echo 'HEADER:' . $br;
            echo '====================' . $br;
            echo 'MAGIC: 0x' . $strMagic . $br;
        }

        $dwVersion = $upkReader->readDWord();
        if (($dwVersion === 0x10000) || ($dwVersion === 0x20000)) {
            throw new Exception('Fully compressed arc.');
        }
        $this->arcVer = $dwVersion & 0xFFFF;
        $this->arcLicenseVer = $dwVersion >> 16;

        if ($this->debugFlag) {
            echo 'VERSION: ' . $this->arcVer . '.' . $this->arcLicenseVer . $br;
        }
        if (defined('GAME_MASS_EFFECT') && ($upkReader->getByteOrder() === Reader::PC)) {
            $this->arcLicenseVer = 90; // real version is 1008, which is greater than LicenseeVersion of Mass Effect 2 and 3
        }

        $this->headersSize = $upkReader->readDWord();
        if ($this->debugFlag) {
            echo 'headersSize: ' . sprintf('0x%08X', $this->headersSize) . $br;
        }

        if ($this->arcVer >= 269) {
            $this->packageGroup = $upkReader->readString();
            if ($this->debugFlag) {
                echo 'packageGroup: ' . $this->packageGroup . $br;
            }
        }

        $this->packageFlagsPos = $upkReader->getPosition();
        $this->packageFlags = $upkReader->readDWord();
        if ($this->debugFlag) {
            echo 'packageFlags: ' . sprintf('0x%08X', $this->packageFlags) . $br;
        }

        $this->tablesInfo['nameTable']   = array( 'cnt' => $upkReader->readDWord(), 'offset' => $upkReader->readDWord() );
        $this->tablesInfo['exportTable'] = array( 'cnt' => $upkReader->readDWord(), 'offset' => $upkReader->readDWord() );
        $this->tablesInfo['importTable'] = array( 'cnt' => $upkReader->readDWord(), 'offset' => $upkReader->readDWord() );

        if ($this->arcVer >= 415) {
            $dependsOffset = $upkReader->readDWord();
            if ($this->debugFlag) {
                echo 'dependsOffset: ' . sprintf('0x%08X', $dependsOffset) . $br;
            }
        }

        if ($this->arcVer >= 623) {
            $f38 = $upkReader->readDWord();
            $f3c = $upkReader->readDWord();
            $f40 = $upkReader->readDWord();
        }

        if ($this->arcVer >= 584) {
            $unk38 = $upkReader->readDWord();
        }

        $strDebug = 'GUID: ';
        for ($i=0; $i<=3; $i++) {
            $this->packageGuid[$i] = $upkReader->readDWord();
            $strDebug .= ($this->debugFlag) ? sprintf('%08X', $this->packageGuid[$i]) : '';
        }
        if ($this->debugFlag) {
            echo $strDebug . $br;
        }

        $generationsCnt = $upkReader->readDWord();
        $this->generations = array();
        for ($i=0; $i < $generationsCnt; $i++) {
            $record = array(
                'exportCnt' => $upkReader->readDWord(),
                'nameCnt' => $upkReader->readDWord(),
            );
            $record['netObjCnt'] = ($this->arcVer >= 322) ? $upkReader->readDWord() : 0;
            $this->generations[] = $record;
            if ($this->debugFlag) {
                echo 'Generation #' . $i . ' (exportCnt: ' . $record['exportCnt'] . '; nameCnt:' . $record['nameCnt'] .
                    '; netObjCnt: ' . $record['netObjCnt'] . ')' . $br;
            }
        }

        if ($this->arcVer >= 245) {
            $this->engineVer = $upkReader->readDWord();
            if ($this->debugFlag) {
                echo 'engineVer: ' . $this->engineVer . $br;
            }
        }

        if ($this->arcVer >= 277) {
            $this->cookerVer = $upkReader->readDWord();
            if ($this->debugFlag) {
                echo 'cookerVer: ' . $this->cookerVer . $br;
            }
        }

        if (defined('GAME_MASS_EFFECT')) {
            $licVer = $this->arcLicenseVer;
            if ($licVer >= 16 && $licVer < 136) { $dummy = $upkReader->readDWord(); } // random value, ME1&2
            if ($licVer >= 32 && $licVer < 136) { $dummy = $upkReader->readDWord(); } // unknown, ME1&2
            if ($licVer >= 35 && $licVer < 113) { // ME1
                if ($arrayCnt = $upkReader->readDWord()) {
                    throw new Exception('Can\'t parse header.');
                    // TMap<FString, TArray<FString> > unk5;
                    // Ar << unk5;
                }
            }
            if ($licVer >= 37) {
                $dummy = $upkReader->readDWord(); // 2 ints: 1, 0
                $dummy = $upkReader->readDWord();
            }
            if ($licVer >= 39 && $licVer < 136) {
                $dummy = $upkReader->readDWord(); // 2 ints: -1, -1 (ME1&2)
                $dummy = $upkReader->readDWord();
            }
        }

        if ($this->arcVer >= 334) {
            $this->compressionPos = $upkReader->getPosition();
            $this->compressedChunkTable = new Upk_Archive_Compressed_ChunkTable($upkReader, $this->debugFlag);
        }
    }

    /**
     * @return bool
     */
    public function isCompressed()
    {
        return $this->compressedChunkTable->isCompressed();
    }

    /**
     * @return int
     */
    public function getArcVer()
    {
        return $this->arcVer;
    }

    /**
     * @return Upk_Archive_Reader|Reader_File
     */
    public function getReader()
    {
        return $this->upkReader;
    }

    /**
     * @return Upk_Archive_Table_Name
     */
    public function getNameTable()
    {
        return $this->nameTable;
    }

    /**
     * @return Upk_Archive_Table_Import
     */
    public function getImportTable()
    {
        return $this->importTable;
    }

    /**
     * @return Upk_Archive_Table_Export
     */
    public function getExportTable()
    {
        return $this->exportTable;
    }

    /**
     * @return boolean
     */
    public function isDebugFlag()
    {
        return $this->debugFlag;
    }

    /**
     * Get file data
     * @param string $relFilePath
     * @return bool|string
     */
    public function getFileData($relFilePath)
    {
        $fileData = false;

        $row = $this->exportTable->getRowByRelPath($relFilePath);
        if ($row !== false) {
            $r = $this->upkReader;

            $r->openFileForRead();
            $r->setPosition($row['serialOffset']);
            $fileData = $r->readData($row['serialSize']);
            $r->closeFile();
        }

        return $fileData;
    }

    /**
     * @param string $relFilePath
     * @param bool $debugFlag
     * @return bool|Upk_File_Abstract
     */
    public function getUpkFile($relFilePath, $debugFlag = false)
    {
        $fileData = $this->getFileData($relFilePath);
        if ($fileData === false) {
            return false;
        }

        $reader = new Reader_Memory($fileData, $this->upkReader->getByteOrder());

        $extName = pathinfo($relFilePath, PATHINFO_EXTENSION);
        // Fixes for long extensions in King's Quest
        if ($extName === 'GppDialogueSeqVar_Choice') {
            $extName = 'ChoiceDialog';
        }
        elseif ($extName === 'GppHudSeqAct_TextAndImagesDisplayToggle') {
            $extName = 'ChoiceImage';
        }
        $className = 'Upk_File_' . $extName;
        if (!class_exists($className)) {
            $className = 'Upk_File';
        }
        $upkFile = new $className($reader, $this, $debugFlag);

        return $upkFile;
    }

    /**
     * @param array|string $dataTypes
     * @return array
     */
    public function searchFiles($dataTypes)
    {
        return $this->exportTable->searchFilesByTypes($dataTypes);
    }

    /**
     * @return array
     */
    public function getFreeBlocks()
    {
        return $this->freeBlocks;
    }

    /**
     * @param int $startPos
     * @param int $length
     */
    public function addFreeBlock($startPos, $length)
    {
        $newBlock = array('startPos' => $startPos, 'length' => $length, 'endPos' => $startPos+$length);

        foreach ($this->freeBlocks as $index => $blockBefore) {
            if ($blockBefore['endPos'] === $newBlock['startPos']) {
                $newBlock['startPos'] = $blockBefore['startPos'];
                $newBlock['length'] += $blockBefore['length'];
                unset($this->freeBlocks[$index]);
            }
        }

        foreach ($this->freeBlocks as $index => $blockAfter) {
            if ($blockAfter['startPos'] === $newBlock['endPos']) {
                $newBlock['endPos'] = $blockAfter['endPos'];
                $newBlock['length'] += $blockAfter['length'];
                unset($this->freeBlocks[$index]);
            }
        }

        $this->freeBlocks[] = $newBlock;
    }

    /**
     * @param int $length
     * @return int|bool
     */
    private function getFreeBlock($length)
    {
        $result = false;

        foreach ($this->freeBlocks as &$freeBlock) {
            if ($freeBlock['length'] >= $length) {
                $result = $freeBlock['startPos'];
                $freeBlock['startPos'] += $length;
                $freeBlock['length'] -= $length;
                break;
            }
        }

        return $result;
    }

    /**
     * @param string $fileNames
     * @param bool $fillWithStamp
     * @param bool $openAndCloseFile
     */
    public function markFreeSpace($fileNames, $fillWithStamp = false, $openAndCloseFile = false)
    {
        if (is_string($fileNames)) {
            $fileNames = array($fileNames);
        }

        foreach ($fileNames as $relPath) {
            $exportRecord = $this->exportTable->getRowByRelPath($relPath);
            $this->addFreeBlock($exportRecord['serialOffset'], $exportRecord['serialSize']);

            if ($fillWithStamp) {
                // Fill free space from $fileNames with copyright stamp
                $stamp = '-=[ Ported by Nick o\'DIMM ]=- (' . date('Y-m-d H:i:s') . ') ';
                $fillData = str_pad('', $exportRecord['serialSize'], $stamp);
                $r = $this->upkReader;
                if ($openAndCloseFile) {
                    $r->openFileForWrite();
                }
                $r->setPosition( $exportRecord['serialOffset'] );
                $r->writeData($fillData);
                if ($openAndCloseFile) {
                    $r->closeFile();
                }
            }
        }
    }

    /**
     * Inject file in the archive.
     * If it is smaller than original file, it will be written to the same place,
     * otherwise at the end of the archive.
     *
     * @param string $relFilePath
     * @param string|Upk_File $upkFile
     * @param bool|true $flagOpenClose open file before insert data and close after if true.
     * @throws Exception
     */
    public function injectFile($relFilePath, $upkFile, $flagOpenClose = true)
    {
        $row = $this->exportTable->getRowByRelPath($relFilePath);
        if ($row === false) {
            throw new Exception('Can\'t find file '.$relFilePath.' in arc.');
        }

        $r = $this->upkReader;
        if ($flagOpenClose) {
            $r->openFileForWrite();
        }

        if (is_string($upkFile)) {
            $fileData = $upkFile;
            $hardLinks = array();
        }
        else {
            $fileData = $upkFile->pack($r->getByteOrder(), $this);
            $hardLinks = $upkFile->getHardLinks();
        }

        $dataLength = strlen($fileData);
        // Copyright stamp
        $stampDraft = '.Nick.o\'DIMM.' . date('Y-m-d') . '.';

        // New code.
        // Try to allocate space for fileData and stamp.
        $startFrom = $this->getFreeBlock($dataLength + strlen($stampDraft));
        if ($startFrom !== false) {
            // Allocated space for fileData and stamp.
            $r->setPosition($startFrom);
            $stampLen = strlen($stampDraft);
        }
        else {
            // Have no space for fileData and stamp. Try without stamp.
            $startFrom = $this->getFreeBlock($dataLength);
            if ($startFrom !== false) {
                $r->setPosition($startFrom);
                $stampLen = 0;
            } else {
                // There is no free space. Put fileData and stamp in the end of file.
                $r->setEndPosition();
                $currentPosition = $r->getPosition();
                // Align
                $stampLen = 0x30-($currentPosition & 0x0F);
            }
        }

        $stampStr = str_pad('', $stampLen, $stampDraft, STR_PAD_LEFT);
        $r->writeData($stampStr);

        // Inject
        $newPosition = $r->getPosition();
        $r->writeData($fileData);

        // Fix file link
        $r->setPosition( $row['linkOffset'] );
        $r->writeDWord($dataLength);
        $r->writeDWord($newPosition);

        // Fix hard links
        foreach ($hardLinks as $hardLink) {
            $hardLinkOfs = $newPosition + $hardLink;
            $r->setPosition($hardLinkOfs);
            $r->writeDWord($hardLinkOfs + 4);
        }

        if ($flagOpenClose) {
            $r->closeFile();
        }
    }

    /**
     * Replace one file with another. Both files should be exist in arc.
     * This method just replaces file offset and length in export
     * table for $relPathDst with $relPathSrc values.
     *
     * @param string $relPathDst
     * @param string $relPathSrc
     * @throws Exception
     */
    public function replaceFile($relPathDst, $relPathSrc)
    {
        $rowDst = $this->exportTable->getRowByRelPath($relPathDst);
        if ($rowDst === false) {
            throw new Exception('Can\'t find file '.$relPathDst.' in arc.');
        }

        $rowSrc = $this->exportTable->getRowByRelPath($relPathSrc);
        if ($rowSrc === false) {
            throw new Exception('Can\'t find file '.$relPathSrc.' in arc.');
        }

        $r = $this->upkReader;

        $r->openFileForWrite();

        $this->markFreeSpace($relPathDst, true, false);

        // Fix file link
        $r->setPosition( $rowDst['linkOffset'] );
        $r->writeDWord( $rowSrc['serialSize'] );
        $r->writeDWord( $rowSrc['serialOffset'] );

        // TODO: Refactor this code. It is very slow. We don't need to reload export table each time after replace file.
        // TODO: Just implement ExportRow classes so we should be able to set new 'serialSize' and 'serialOffset' values
        // TODO: in this object.
        $saveFlag = $this->debugFlag;
        $this->debugFlag = false;
        $this->exportTable = new Upk_Archive_Table_Export($this, $this->tablesInfo['exportTable']);
        $this->debugFlag = $saveFlag;

        $r->closeFile();
    }

    /**
     * Extract one file from archive
     *
     * @param string $relFilePath Relative path to file in archive
     * @param string $pathTo Dir for extract
     * @param bool|false $withSubDirs extract with creating sub dirs if true.
     * @param bool|false $printLog print extracted filename.
     * @return bool
     */
    public function extractFile($relFilePath, $pathTo, $withSubDirs = false, $printLog = false)
    {
        $fileData = $this->getFileData($relFilePath);
        if ($fileData === false) {
            return false;
        }

        if ($printLog) {
            echo 'Extract: ' . $relFilePath . '<br/>';
        }

        // Fix for Mass Effect
        if (strpos($relFilePath, ':') !== false) {
            $relFilePath = str_replace(':', '/', $relFilePath);
        }

        if (($pathTo !== '') && (substr($pathTo, -1) !== '/')) {
            $pathTo .= '/';
        }

        $pathInfo = pathinfo($relFilePath);
        $fileName = $pathInfo['basename'];

        if ($withSubDirs) {
            $relDir = $pathInfo['dirname'];
            if (!is_dir($pathTo . $relDir)) {
                mkdir($pathTo . $relDir, 0777, true);
            }
            $pathTo .= $relDir .'/';
        }

        file_put_contents($pathTo . $fileName, $fileData);

        return true;
    }

    /**
     * Extract all files from archive.
     * @param string $pathTo
     * @param bool|false $printLog print extracted file names.
     */
    public function extractAll($pathTo, $printLog = false)
    {
        for ($i=0; $i<$this->exportTable->count(); $i++) {
            $relPath = $this->exportTable->getRelPathById($i);
            $this->extractFile($relPath, $pathTo, true, $printLog);
        }
    }

    /**
     * @param string $pathToFile
     */
    public function saveDecompressedArc($pathToFile)
    {
        if (!$this->isCompressed()) {
            return;
        }

        // Create dst file and open it for write.
        $decompressedFileDst = new Reader_File($pathToFile, $this->upkReader->getByteOrder(), true);
        $decompressedFileDst->openFileForWrite();

        // Get reader (wrapper) of decompressed src file.
        $decompressedFileSrc = $this->upkReader;
        $decompressedFileSrc->openFileForRead();

        $this->writeDecompressedHeader($decompressedFileDst);

        $chunks = $this->compressedChunkTable->getChunks();
        foreach ($chunks as $chunk) {
            $offset = $chunk->getUncompressedOffset();
            $decompressedFileSrc->setPosition($offset);
            $decompressedFileDst->setPosition($offset);

            $data = $decompressedFileSrc->readData($chunk->getUncompressedSize());
            $decompressedFileDst->writeData($data);
        }

        $decompressedFileSrc->closeFile();
        $decompressedFileDst->closeFile();
    }

    /**
     * @param Reader $decompressedFileDst
     */
    protected function writeDecompressedHeader(Reader $decompressedFileDst)
    {
        $compressedStartOffset = $this->compressedChunkTable->getChunk(0)->getCompressedOffset();

        // Read original header
        $compressedFileSrc = $this->compressedChunkTable->getReader();
        $compressedFileSrc->setPosition(0);
        $headerData = $compressedFileSrc->readData($compressedStartOffset);

        // Remove compression table
        $headerLeftPart = substr($headerData, 0, $this->compressionPos);
        $compressionTableLength = 2*4 + ($this->compressedChunkTable->getChunkCount() * 16);
        $headerRightPart = substr($headerData, $this->compressionPos + $compressionTableLength);

        $headerReader = new Reader_Memory('', $compressedFileSrc->getByteOrder());
        $headerReader->writeData($headerLeftPart);
        $headerReader->writeDWord(0); // Compression flags
        $headerReader->writeDWord(0); // Chunk num
        $headerReader->writeData($headerRightPart);

        // Change package flags
        $headerReader->setPosition($this->packageFlagsPos);
        $headerReader->writeDWord($this->packageFlags & ~0x2000000);

        $decompressedFileDst->writeData($headerReader->getData());
    }

    public function saveCompressedArc($pathToFile)
    {
        if (!$this->isCompressed()) {
            return;
        }

        // Create dst file and open it for write.
        $compressedFileDst = new Reader_File($pathToFile, $this->upkReader->getByteOrder(), true);
        $compressedFileDst->openFileForWrite();

        // Open original src file
        $compressedFileSrc = $this->compressedChunkTable->getReader();
        $compressedFileSrc->openFileForRead();

        // Copy original header to dst file.
        $compressedFileSrc->setPosition(0);
        $compressedOffsetStart = $this->compressedChunkTable->getChunk(0)->getCompressedOffset();
        $compressedFileDst->writeData( $compressedFileSrc->readData($compressedOffsetStart) );

        // Write all chunks, blocks and table to dst file.
        $this->compressedChunkTable->writeChunksData($compressedFileDst);

        $compressedFileSrc->closeFile();
        $compressedFileDst->closeFile();
    }

}
