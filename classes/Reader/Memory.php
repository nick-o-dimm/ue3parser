<?php

/**
 * Class Reader_Memory
 */
class Reader_Memory extends Reader
{

    /** @var string Content */
    private $data = '';

    /** @var int Content pointer */
    private $position = 0;

    /**
     * Reader_Memory constructor.
     * @param string $fileData Content
     * @param int $byteOrder default set to little-endian.
     */
    public function __construct($fileData, $byteOrder = self::PC)
    {
        $this->data = $fileData;
        $this->byteOrder = $byteOrder;
        $this->position = 0;

        parent::__construct();
    }

    /**
     * Set data length
     */
    protected function setDataLength()
    {
        $this->dataLength = strlen($this->data);
    }

    /**
     * @return string
     */
    final public function getData()
    {
        return $this->data;
    }

    /**
     * @param string $data
     */
    final public function setData($data)
    {
        $this->data = $data;
        $this->dataLength = strlen($this->data);
        $this->position = 0;
    }

    /**
     * Read $length bytes
     * @param int $length
     * @return string
     */
    public function readData($length)
    {
        $data = substr($this->data, $this->position, $length);
        $this->position += $length;
        return $data;
    }

    /**
     * Write data
     * @param string $writeData
     * @return int
     */
    public function writeData($writeData)
    {
        $writeDataLength = strlen($writeData);

        if ($this->getPosition() === $this->dataLength) {
            // Append data
            $this->data .= $writeData;
            $this->dataLength = strlen($this->data);
            $this->setPosition($this->dataLength);
        }
        else {
            // Check if part of new data is above current data length
            $overLength = $this->getPosition() + $writeDataLength - $this->dataLength;
            if ($overLength > 0) {
                // Reserve more space with 0-filled string
                $this->data .= str_repeat("\0", $overLength);
                $this->dataLength = strlen($this->data);
            }

            // all new data is below data length
            for ($i=0; $i<$writeDataLength; $i++) {
                $this->data[$this->position] = $writeData[$i];
                $this->position++;
            }
        }

        return $writeDataLength;
    }

    /**
     * Get current position
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Set position
     * @param int $newPosition
     */
    public function setPosition($newPosition)
    {
        // If we try to set new position over data length then append string.
        if ($newPosition > $this->dataLength) {
            $this->data .= str_repeat("\0", $newPosition - $this->dataLength);
            $this->dataLength = $newPosition;
        }

        $this->position = $newPosition;
    }

}
