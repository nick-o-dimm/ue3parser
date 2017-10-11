<?php

/**
 * Class Upk_Archive_Table_Import
 * Read Import table from upk archive.
 */
class Upk_Archive_Table_Import extends Upk_Archive_Table
{

    /**
     * @return string
     */
    protected function getTableName()
    {
        return 'Import';
    }

    /**
     * Read Import table rows
     */
    protected function readRows()
    {
        $r = $this->upk->getReader();
        $nameTable = $this->upk->getNameTable();

        for ($i=0; $i < $this->rowsCount; $i++) {

            $row = array();
            for ($j=0; $j<7; $j++) {
                $row[$j] = $r->readDWord();
            }

            $packageName = $nameTable->getNameById( $row[5] );
            if ($row[6] !== 0) {
                $packageName .= '_' . ($row[6] - 1);
            }

            if ($this->debugFlag) {
                echo '(' . sprintf('%4d %04X', $i, $i) . ') ' . $packageName . '<br/>';
            }

            $this->rows[$i] = $packageName;
        }
    }

}
