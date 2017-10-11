<?php

/**
 * Class Texture
 */
abstract class Texture {

    const ATI2 = 'PF_ATI2';
    const G8 = 'PF_G8';
    const DXT1 = 'PF_DXT1';
    const DXT3 = 'PF_DXT3';
    const DXT5 = 'PF_DXT5';
    const V8U8 = 'PF_V8U8';
    const ARGB8 = 'PF_A8R8G8B8';
    const PVRTC4 = 'PF_PVRTC_4BPP';

    const FORMATS = array(
        self::G8 => 'G8',
        self::DXT1 => 'DXT1',
        self::DXT5 => 'DXT5',
        self::V8U8 => 'V8U8',
        self::ARGB8 => 'ARGB8',
        self::PVRTC4 => 'PVRTC4'
    );

    /** @var int Texture width */
    protected $width = 0;

    /** @var int Texture height */
    protected $height = 0;

    /** @var Reader_Memory Raw texture data */
    protected $textureCompressed = null;

    /** @var resource */
    protected $image = null;

    /**
     * Texture_Abstract constructor.
     * @param int $width
     * @param int $height
     * @param Reader_Memory $textureReader
     */
    public function __construct($width, $height, Reader_Memory $textureReader)
    {
        $this->height = $height;
        $this->width = $width;
        $this->textureCompressed = $textureReader;
    }

    /**
     * @param string $format
     * @return string
     */
    public static function getClassName($format)
    {
        $classSuffix = 'Unknown_' . substr($format, 3);
        if (in_array($format, array_keys(self::FORMATS))) {
            $classSuffix = self::FORMATS[$format];
        }

        return 'Texture_' . $classSuffix;
    }

    /**
     * Decompress image from $this->textureCompressed and draw it to $this->image
     */
    abstract protected function decompressAndDrawImage();

    /**
     * Draw single pixel.
     * This method is public due it used as callable parameter for DxtTools::decompressImage
     * @param int $x
     * @param int $y
     * @param int $r
     * @param int $g
     * @param int $b
     * @param int $a value between 0 (transparent) and 255 (opaque).
     */
    public function drawPixel($x, $y, $r, $g, $b, $a)
    {
        // Convert 8bit alpha to 7bit GD lib where 0 is opaque while 127 is transparent.
        $a = ($a ^ 0xff) >> 1;
        $color = imagecolorallocatealpha($this->image, $r, $g, $b, $a);
        imagesetpixel($this->image, $x, $y, $color);
    }

    /**
     * @param bool $saveAlpha
     * @return resource
     */
    public function getImage($saveAlpha = false)
    {
        $this->image = imagecreatetruecolor($this->width, $this->height);

        if ($saveAlpha) {
            imagealphablending($this->image, false);
            imagesavealpha($this->image, true);
        }

        $this->textureCompressed->setPosition(0);
        $this->decompressAndDrawImage();

        return $this->image;
    }

}
