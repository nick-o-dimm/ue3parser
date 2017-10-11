<?php

/**
 * Class Upk_Archive_Table_Export
 * Read Export table from upk archive.
 */
class Upk_Archive_Table_Export extends Upk_Archive_Table
{

    /** @var array */
    private $indexes = array();

    /**
     * @return string
     */
    protected function getTableName()
    {
        return 'Export';
    }

    /**
     * @param $rowId
     * @return string
     */
    private function getFileNameById($rowId)
    {
        $row = $this->getRowById($rowId);
        return $row['fileName'];
    }

    /**
     * Get relative path to file by row ID.
     * @param int $rowId
     * @return string
     */
    public function getRelPathById($rowId)
    {
        $row = $this->getRowById($rowId);
        return $row['index'];
    }

    /**
     * Get list of relative paths to files.
     * @return array
     */
    public function getFilesList()
    {
        $result = array();

        foreach ($this->rows as $row) {
            $result[] = $row['index'];
        }

        return $result;
    }

    /**
     * Get row by relative path to the file
     * @param string $relPath
     * @return array|bool
     */
    public function getRowByRelPath($relPath)
    {
        $row = false;

        $relPathUpperCase = strtoupper($relPath);
        if (isset($this->indexes[$relPathUpperCase])) {
            $rowId = $this->indexes[$relPathUpperCase];
            $row = $this->getRowById($rowId);
        }

        return $row;
    }

    /**
     * Get row by relative path to the file
     * @param string $relPath
     * @return array|bool
     */
    public function getIdByRelPath($relPath)
    {
        $rowId = false;

        $relPathUpperCase = strtoupper($relPath);
        if (isset($this->indexes[$relPathUpperCase])) {
            $rowId = $this->indexes[$relPathUpperCase];
        }

        return $rowId;
    }

    /**
     * @param array|string $dataTypes
     * @return array
     */
    public function searchFilesByTypes($dataTypes)
    {
        if (is_string($dataTypes)) {
            $dataTypes = array($dataTypes);
        }

        $result = array();
        foreach ($this->rows as $row) {
            if (in_array($row['dataType'], $dataTypes)) {
                $result[] = $row['index'];
            }
        }
        return $result;
    }

    protected function readRows()
    {
        $this->indexes = array();

        $r = $this->upk->getReader();
        $nameTable = $this->upk->getNameTable();
        $arcVer = $this->upk->getArcVer();

        if ($this->debugFlag) {
            echo str_repeat(' ', 29) . 'SuperIdx DirName  FileName Suffix   Flags    Flags2'.
                 '           linkOfs  Length   Offset   ExpFlags GUID<br/>';
        }

        for ($rowId=0; $rowId < $this->rowsCount; $rowId++) {
            $recordPos = $r->getPosition();

            $exportRow = array(
                'classIndex'   => $r->readSignedDWord(), // dataType
                'superIndex'   => $r->readSignedDWord(),
                'packageIndex' => $r->readSignedDWord(), // dirId
                'objectName'   => $r->readSignedDWord(), // fileName
                'archetype'    => ($arcVer >= 220) ? $r->readSignedDWord() : 0, // suffix
                'objectFlags'  => $r->readDWord(),
                'objectFlags2' => ($arcVer >= 195) ? $r->readInt64() : 0,
                'linkOffset'   => $r->getPosition(),
                'serialSize'   => $serialSize = $r->readDWord(), // len
                'serialOffset' => (($serialSize > 0) || ($arcVer >= 249)) ? $r->readDWord() : 0, // ofs
            );

            if ($arcVer < 543) {
                // TODO: Parse $tmpComponentMap array correctly
                $tmpComponentMapCount = $r->readDWord();
                $tmpComponentMap = $r->readData($tmpComponentMapCount * 3*4);
            }

            $exportRow['exportFlags'] = ($arcVer >= 247) ? $r->readDWord() : 0;
            $exportRow['netObjects'] = array();
            $exportRow['guid'] = str_repeat('0', 32);

            if ($arcVer >= 322) {
                $netObjectCount = $r->readDWord();
                for ($i=0; $i < $netObjectCount; $i++) {
                    $exportRow['netObjects'][] = $r->readDWord();
                }

                $exportRow['guid'] = '';
                for ($i=0; $i < 4; $i++) {
                    $exportRow['guid'] .= sprintf('%08X', $r->readDWord());
                }
            }

            if ($arcVer >= 475) {
                $u3unk6C = $r->readDWord();
            }

            // Set filename
            $exportRow['fileName'] = $nameTable->getNameById( $exportRow['objectName'] );
            // Add suffix
            if ($exportRow['archetype'] !== 0) {
                $exportRow['fileName'] .= '_' . ($exportRow['archetype'] - 1);
            }

            if ($this->debugFlag) {
                echo '(' . sprintf('%5d %04X', $rowId, $rowId) . ') ' . sprintf('%08X: ', $recordPos) . sprintf('%5d', $exportRow['classIndex']);
                $keys = array_keys($exportRow);
                for ($keyId = 1; $keyId < 10; $keyId++) {
                    $key = $keys[$keyId];
                    if ($keyId !== 6) {
                        echo ' ' . sprintf('%08X', $exportRow[$key] & 0xFFFFFFFF);
                    }
                    else {
                        echo ' ' . sprintf('%016X', $exportRow[$key]);
                    }
                }
                echo ' ' . sprintf('%08X', $exportRow['exportFlags']) . ' ' . $exportRow['guid'];
                echo '<br/>';
            }

            $this->rows[$rowId] = $exportRow;
        }

        if ($this->debugFlag) {
            echo '<br/>';
        }

        // Post process Export table rows
        $this->processRows();
    }

    /**
     * Post process Export table rows.
     * Set data types and dir names.
     * @throws Exception
     */
    private function processRows()
    {
        $importTable = $this->upk->getImportTable();

        for ($i=0; $i < $this->rowsCount; $i++) {
            $currentRow = &$this->rows[$i];

            // Set data types
            $dataTypeId = $currentRow['classIndex'];
            if ($dataTypeId < 0) {
                $packageId = -$dataTypeId-1;
                // Extention
                $dataType = $importTable->getRowById($packageId);
            }
            elseif ($dataTypeId === 0) {
                $dataType = 'Class';
            }
            else {
                // $dataTypeId > 0
                $dataType = $this->getFileNameById($dataTypeId-1);
            }
            $currentRow['dataType'] = $dataType;

            // Set dirnames
            // Calc full path
            $dirStr = '';
            $dirId = $currentRow['packageIndex'];
            while ($dirId !== 0) {
                $parentDir = $this->getRowById($dirId-1);
                $dirStr = $parentDir['fileName'] . '/' . $dirStr;
                $dirId = $parentDir['packageIndex'];
            }

            // Set index with full path to file
            $strIndex = $dirStr;
            $strIndex .= $currentRow['fileName'] . '.' . $currentRow['dataType'];
            $currentRow['index'] = $strIndex;

            // Register index
            $strIndexUpperCase = strtoupper($strIndex);
            if (isset($this->indexes[$strIndexUpperCase])) {
                throw new Exception('Index ' . $strIndex . ' already exists.');
            }
            $this->indexes[$strIndexUpperCase] = $i;

            if ($this->debugFlag) {
                echo '(' . sprintf('%5d', $i) . ') ' . $strIndex . '<br/>';
            }
        }
    }

}
