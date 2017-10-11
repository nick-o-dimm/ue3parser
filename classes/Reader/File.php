<?php

/**
 * Class Reader_File
 * Read/write from/to file.
 */
class Reader_File extends Reader
{

    /** @var resource File handler */
    private $f = false;

    /** @var string File name */
    private $fileName = '';

    /**
     * Reader_File constructor.
     * @param string $pathToFile
     * @param int $byteOrder default set to little-endian.
     * @param bool $createFile
     */
    public function __construct($pathToFile, $byteOrder = self::PC, $createFile = false)
    {
        $this->fileName = $pathToFile;
        $this->byteOrder = $byteOrder;

        if ($createFile) {
            file_put_contents($pathToFile, '');
        }

        parent::__construct();
    }

    /**
     * File_Reader destructor.
     */
    public function __destruct()
    {
        // Close file if somehow it is still opened.
        $this->closeFile();
    }

    /**
     * Set data length
     */
    protected function setDataLength()
    {
        $this->dataLength = filesize($this->fileName);
    }

    /**
     * @return string
     */
    public function getFileName()
    {
        return $this->fileName;
    }

    /**
     * @param string $mode
     * @throws Exception
     */
    private function openFile($mode = 'r')
    {
        $this->f = fopen($this->fileName, $mode);
        if ($this->f === false) {
            throw new Exception('Can\'t open file: '.$this->fileName);
        }
    }

    /**
     * @throws Exception
     */
    public function openFileForRead()
    {
        $this->openFile('r');
    }

    /**
     * @throws Exception
     */
    public function openFileForWrite()
    {
        $this->openFile('r+');
    }

    /**
     * Close file handler
     */
    public function closeFile()
    {
        if (is_resource($this->f)) {
            fclose($this->f);
            $this->f = false;
        }
    }

    /**
     * Read $len bytes from current position.
     * @param int $length
     * @return string
     */
    public function readData($length)
    {
        if ($length > 0) {
            $data = fread($this->f, $length);
        } else {
            $data = '';
        }
        return $data;
    }

    /**
     * Write $data to the file
     * @param $data
     * @return int
     */
    public function writeData($data)
    {
        return fwrite($this->f, $data);
    }

    /**
     * Get current file position.
     * @return int
     */
    public function getPosition()
    {
        return ftell($this->f);
    }

    /**
     * @param int $newPosition
     * @param int $whence
     */
    public function setPosition($newPosition, $whence = SEEK_SET)
    {
        fseek($this->f, $newPosition, $whence);
    }

    /**
     * Move file pointer to the end of file.
     */
    public function setEndPosition()
    {
        $this->setPosition(0, SEEK_END);
    }

}
