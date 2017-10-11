<?php

/**
 * Class Reader
 */
abstract class Reader
{

    // Byte order constants
    const MASK_ENDIAN = 0x0100;
    const LITTLE_ENDIAN = 0x0000;
    const BIG_ENDIAN = 0x0100;

    const PC = 0x00 | self::LITTLE_ENDIAN;
    const PS3 = 0x01 | self::BIG_ENDIAN;
    const XBOX = 0x02 | self::BIG_ENDIAN;
    const PSVITA = 0x03 | self::LITTLE_ENDIAN;

    /** @var int Byte order */
    protected $byteOrder = self::PC;

    /** @var int Data length */
    protected $dataLength = 0;

    /**
     * Reader constructor.
     */
    public function __construct()
    {
        $this->setDataLength();
    }

    /**
     * @return int
     */
    public function getByteOrder()
    {
        return $this->byteOrder;
    }

    /**
     * Get format string for byte pack/unpack
     * @return string
     */
    public static function getFormatStringByte()
    {
        return 'C';
    }

    /**
     * Get format string for word pack/unpack.
     * @param string $byteOrder
     * @return string
     */
    public static function getFormatStringWord($byteOrder)
    {
        return (($byteOrder & self::MASK_ENDIAN) === self::LITTLE_ENDIAN) ? 'v' : 'n';
    }

    /**
     * Get format string for dword pack/unpack.
     * @param string $byteOrder
     * @return string
     */
    public static function getFormatStringDWord($byteOrder)
    {
        return (($byteOrder & self::MASK_ENDIAN) === self::LITTLE_ENDIAN) ? 'V' : 'N';
    }

    /**
     * Get format string for int64 pack/unpack.
     * @param string $byteOrder
     * @return string
     */
    public static function getFormatStringInt64($byteOrder)
    {
        return (($byteOrder & self::MASK_ENDIAN) === self::LITTLE_ENDIAN) ? 'P' : 'J';
    }

    /**
     * Read byte
     * @return int
     */
    final public function readByte()
    {
        // Read 1 byte
        $packedByte = $this->readData(1);
        $formatStr = self::getFormatStringByte();
        $unpacked = unpack($formatStr, $packedByte);
        return $unpacked[1];
    }

    /**
     * Read word
     * @return int
     */
    final public function readWord()
    {
        // Read 2 bytes
        $packedWord = $this->readData(2);
        $formatStr = self::getFormatStringWord($this->byteOrder);
        $unpacked = unpack($formatStr, $packedWord);
        return $unpacked[1];
    }

    /**
     * Read dword
     * @return int
     */
    final public function readDWord()
    {
        // Read 4 bytes
        $packedDWord = $this->readData(4);
        $formatStr = self::getFormatStringDWord($this->byteOrder);
        $unpacked = unpack($formatStr, $packedDWord);
        return $unpacked[1];
    }

    /**
     * Read signed dword
     * @return int
     */
    final public function readSignedDWord()
    {
        $dw = $this->readDWord();
        if ($dw & 0x80000000) {
            $dw -= 0x100000000;
        }
        return $dw;
    }

    /**
     * Read int64
     * @param bool $forceSwap
     * @return int
     */
    final public function readInt64($forceSwap = false)
    {
        // Read 4 bytes
        $packedInt64 = $this->readData(8);
        $formatStr = self::getFormatStringInt64($this->byteOrder);
        $unpacked = unpack($formatStr, $packedInt64);
        $result = $unpacked[1];

        if ((($this->byteOrder & self::MASK_ENDIAN) === self::BIG_ENDIAN) || $forceSwap) {
            // Swap dwords
            $result = (($result & 0xFFFFFFFF) << 32) | ($result >> 32);
        }

        return $result;
    }

    /**
     * @return float
     */
    final public function readFloat()
    {
        $packedFloat = $this->readData(4);
        if (($this->byteOrder & self::MASK_ENDIAN) === self::BIG_ENDIAN) {
            $packedFloat = strrev($packedFloat);
        }
        $unpacked = unpack('f', $packedFloat);
        return $unpacked[1];
    }

    /**
     * @return string
     */
    final public function readString()
    {
        $strLength = $this->readSignedDWord();
        if ($strLength < 0) {
            $strLength = -2*$strLength;
        }

        return $this->readData($strLength);
    }

    /**
     * @param string $str
     * @param bool $utf
     */
    final public function writeString($str, $utf = false)
    {
        $strLength = ($utf === false) ? strlen($str) : -mb_strlen($str, 'UTF-16LE');
        $this->writeDWord($strLength);
        $this->writeData($str);
    }

    /**
     * @return string
     */
    final public function readZeroEndingString()
    {
        $str = '';
        while (($ch = $this->readData(1)) !== "\0") {
            $str .= $ch;
        }

        return $str;
    }

    /**
     * @param $str
     */
    final public function writeZeroEndingString($str)
    {
        $this->writeData($str);
        if (substr($str, -1) !== "\0") {
            $this->writeData("\0");
        }
    }

    /**
     * Write byte
     * @param int $b
     */
    final public function writeByte($b)
    {
        $formatStr = self::getFormatStringByte();
        $packedByte = pack($formatStr, $b);
        $this->writeData($packedByte);
    }

    /**
     * Write word
     * @param int $w
     */
    final public function writeWord($w)
    {
        $formatStr = self::getFormatStringWord($this->byteOrder);
        $packedWord = pack($formatStr, $w);
        $this->writeData($packedWord);
    }

    /**
     * Write dword
     * @param int $dw
     */
    final public function writeDWord($dw)
    {
        $formatStr = self::getFormatStringDWord($this->byteOrder);
        $packedDWord = pack($formatStr, $dw);
        $this->writeData($packedDWord);
    }

    /**
     * Write int64
     * @param int $int64
     */
    final public function writeInt64($int64)
    {
        $formatStr = self::getFormatStringInt64($this->byteOrder);

        if (($this->byteOrder & self::MASK_ENDIAN) === self::BIG_ENDIAN) {
            // Swap dwords
            $int64 = (($int64 & 0xFFFFFFFF) << 32) | ($int64 >> 32);
        }

        $packedInt64 = pack($formatStr, $int64);
        $this->writeData($packedInt64);
    }

    /**
     * @param float $f
     */
    final public function writeFloat($f)
    {
        $packedFloat = pack('f', $f);
        if (($this->byteOrder & self::MASK_ENDIAN) == self::BIG_ENDIAN) {
            $packedFloat = strrev($packedFloat);
        }
        $this->writeData($packedFloat);
    }

    /**
     * @param int $b
     * @return string
     */
    public static function packByte($b)
    {
        return pack('c', $b);
    }

    /**
     * @param int $w
     * @param int $byteOrder
     * @return string
     */
    public static function packWord($w, $byteOrder)
    {
        return pack(self::getFormatStringWord($byteOrder), $w);
    }

    /**
     * @param int $dw
     * @param int $byteOrder
     * @return string
     */
    public static function packDWord($dw, $byteOrder)
    {
        return pack(self::getFormatStringDWord($byteOrder), $dw);
    }

    /**
     * @param int $int64
     * @param int $byteOrder
     * @return string
     */
    public static function packInt64($int64, $byteOrder)
    {
        $dw1 = $int64 & 0xFFFFFFFF;
        $dw2 = ($int64 >> 32) & 0xFFFFFFFF;
        return self::packDWord($dw1, $byteOrder) . self::packDWord($dw2, $byteOrder);
    }

    /**
     * @param float $f
     * @param int $byteOrder
     * @return string
     */
    public static function packFloat($f, $byteOrder)
    {
        $packedFloat = pack('f', $f);
        if (($byteOrder & self::MASK_ENDIAN) === self::BIG_ENDIAN) {
            $packedFloat = strrev($packedFloat);
        }
        return $packedFloat;
    }

    /**
     * Read data from current position to the end.
     * @return string
     */
    final public function readRestData()
    {
        $length = $this->dataLength - $this->getPosition();
        return $this->readData($length);
    }

    /**
     * @return int
     */
    public function getDataLength()
    {
        return $this->dataLength;
    }

    /**
     * Set data length
     */
    abstract protected function setDataLength();

    /**
     * Read $length bytes
     * @param int $length
     * @return string
     */
    abstract public function readData($length);

    /**
     * Write data
     * @param $data
     * @return int
     */
    abstract public function writeData($data);

    /**
     * Get current position
     * @return int
     */
    abstract public function getPosition();

    /**
     * Set position
     * @param int $newPosition
     */
    abstract public function setPosition($newPosition);

}
