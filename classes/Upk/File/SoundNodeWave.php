<?php

/**
 * Class Upk_File_SoundNodeWave
 */
class Upk_File_SoundNodeWave extends Upk_File_Abstract
{
    const ENG = 0;
    const DEU = 3;
    const ESN = 5;
    const FRA = 6;
    const ITA = 8;
    const JPN = 9;
    const KOR = 10;
    const POL = 11;
    const RUS = 13;
    const CZH = 15;
    const UKR = 16;

    protected $langs = array(
        self::ENG => 'INT',
        self::DEU => 'DEU',
        self::FRA => 'FRA',
        self::RUS => 'RUS'
    );

    /**
     * @var Upk_BulkData[]
     */
    private $soundFiles = array();

    /**
     * @param Reader $reader
     * @throws Exception
     */
    protected function readExtraData(Reader $reader)
    {
        $bulkCount = 8;
        if (defined('GAME_XCOM')) {
            if ($this->byteOrder === Reader::PSVITA) { $bulkCount = 9; }
            elseif ($this->byteOrder === Reader::PS3) { $bulkCount = 5; }
        }
        if (defined('GAME_KINGS_QUEST')) {
            $bulkCount = 7;
        }

        for ($i=0; $i < $bulkCount; $i++) {
            $this->soundFiles[$i] = new Upk_BulkData($reader, $this->debugFlag);
        }
    }

    /**
     * @param int $byteOrder
     * @return string
     *
     * @see Upk_BulkData::pack
     */
    protected function packExtraData($byteOrder)
    {
        $bulkCount = count($this->soundFiles);

        $reader = new Reader_Memory('', $byteOrder);

        for ($i = 0; $i < $bulkCount; $i++) {
            $soundData = $this->soundFiles[$i];

            $currentPos = $reader->getPosition();
            $this->addHardLink($currentPos + 12);
            $reader->writeData($soundData->pack($byteOrder));
        }

        return $reader->getData();
    }

    /**
     * @param $blockNum
     * @return string
     */
    public function exportSoundFile($blockNum)
    {
        return $this->soundFiles[$blockNum]->getData();
    }

    /**
     * @param int $blockNum
     * @param string $data
     */
    public function importSoundFile($blockNum, $data)
    {
        $this->soundFiles[$blockNum]->setData($data);
    }

    /**
     * @return bool
     */
    public function hasSubtitles()
    {
        return ($this->searchElement('Subtitles') !== false);
    }

    /**
     * @return string[]
     * @throws Exception
     */
    public function getRusSubtitles()
    {
        return $this->getSubtitles(self::RUS);
    }

    /**
     * @param int $langIndex
     * @return string[]
     * @throws Exception
     */
    public function getSubtitles($langIndex)
    {
        $localizedSubtitles = $this->searchElement('LocalizedSubtitles');
        $localizedLang = $localizedSubtitles->getElement($langIndex);

        // Check 'LanguageExt' property
        /** @var Upk_File_Element_Property_Str $langExt */
        $langExt = $localizedLang->searchElement('LanguageExt');
        if ($langExt->getStr() !== $this->langs[$langIndex]) {
            throw new Exception('Can\'t find ' . $this->langs[$langIndex] . ' subtitles.');
        }

        $subtitles = array();
        /** @var Upk_File_Element_Property_Array $langSubtitlesArr */
        $langSubtitlesArr = $localizedLang->searchElement('Subtitles');
        for ($i=0; $i < $langSubtitlesArr->getElementsCount(); $i++) {
            $subtitles[$i] = $langSubtitlesArr->getElement($i)->searchElement('Text')->getStr();
        }

        return $subtitles;
    }

    /**
     * @return string
     */
    protected function toStringExtraData()
    {
        $bulkCount = count($this->soundFiles);

        $br = "\n";

        $result = $br . 'soundFiles=[' . $br;
        $result .= ' # Compressed Uncompressed    Offset' . $br;
        for ($i = 0; $i < $bulkCount; $i++) {
            $bulkData = $this->soundFiles[$i];

            $result .= ' ' . $i . ': ' . sprintf('%9d', $bulkData->getCompressedSize()) .
                       sprintf('%13d', $bulkData->getUncompressedSize()) .
                       sprintf('  0x%08X', $bulkData->getOffsetToData()) . $br;
        }
        $result .= ']' . $br;

        return $result;
    }

}
