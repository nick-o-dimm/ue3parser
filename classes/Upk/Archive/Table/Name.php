<?php

/**
 * Class Upk_Archive_Table_Name
 * Read Name table from upk archive.
 */
class Upk_Archive_Table_Name extends Upk_Archive_Table
{

    /** @var array */
    private $indexes = array();

    /**
     * @return string
     */
    protected function getTableName()
    {
        return 'Name';
    }

    /**
     * Get name by Id
     * @param int $nameId
     * @return string
     */
    public function getNameById($nameId)
    {
        return $this->rows[$nameId];
    }

    /**
     * @param $strName
     * @return mixed
     */
    public function getIdByName($strName)
    {
        return $this->indexes[strtoupper($strName)];
    }

    /**
     * Read Name table rows
     * @throws Exception
     */
    protected function readRows()
    {
        $r = $this->upk->getReader();

        for ($i=0; $i<$this->rowsCount; $i++) {
            $strName = $r->readString();
            $strName = trim($strName, "\0");

            if ($this->debugFlag) {
                echo '(' . sprintf('%4d %04X', $i, $i) . ') ' . $strName . '<br/>';
            }

            if (!(defined('GAME_MASS_EFFECT') && ($r->getByteOrder() === Reader::PS3))) {
                $flags = $r->readInt64(true);
                if (!defined('GAME_TUROK') && ($flags !== 0x0000000000070010) && ($flags !== 0x0000000000071010) && ($flags !== 0x0000000000070000)) {
                    throw new Exception('Strange name table flags: ' . sprintf('%016X', $flags));
                }
            }

            $this->rows[$i] = $strName;

            $this->indexes[strtoupper($strName)] = $i;
        }
    }

}
