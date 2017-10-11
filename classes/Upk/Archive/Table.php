<?php

/**
 * Class Upk_Archive_Table
 */
abstract class Upk_Archive_Table
{

    /** @var bool */
    protected $debugFlag = false;

    /** @var Upk_Archive */
    protected $upk = null;

    /** @var array */
    protected $rows = array();

    /** @var int */
    protected $rowsCount = 0;

    /** @var int */
    protected $tableOffset = 0x0000;

    /**
     * Upk_Archive_Table constructor.
     * @param Upk_Archive $upkArc
     * @param array $tableInfo
     */
    public function __construct(Upk_Archive $upkArc, $tableInfo)
    {
        $this->upk = $upkArc;
        $this->debugFlag = $upkArc->isDebugFlag();

        $this->rowsCount = $tableInfo['cnt'];
        $this->tableOffset = $tableInfo['offset'];

        $this->readTable();
    }

    /**
     * Read table.
     */
    private function readTable()
    {
        $this->rows = array();

        $r = $this->upk->getReader();

        if ($this->debugFlag) {
            echo '<br/>' . $this->getTableName() . ' table:<br/>';
            echo '--------------------------------<br/>';
            echo 'Offset: ' . sprintf('0x%08X', $this->tableOffset) . '<br/>';
            echo 'Count: ' . $this->rowsCount . '<br/>';
            echo '--------------------------------<br/>';
        }
        $r->setPosition($this->tableOffset);

        $this->readRows();
    }

    /**
     * Get rows count
     * @return int
     */
    public function count()
    {
        return $this->rowsCount;
    }

    /**
     * @param $rowId
     * @return array
     */
    public function getRowById($rowId)
    {
        return $this->rows[$rowId];
    }

    abstract protected function getTableName();

    abstract protected function readRows();

}
