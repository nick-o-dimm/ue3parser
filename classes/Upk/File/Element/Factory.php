<?php

/**
 * Elements factory
 * Class Upk_File_Element_Factory
 */
class Upk_File_Element_Factory
{

    /** @var bool */
    private static $debugFlag = false;

    /**
     * @param Reader $reader
     * @param Upk_Archive $upkArc
     * @param bool $debugFlag
     *
     * @return Upk_File_Element_Abstract
     * @throws Exception
     */
    public static function getElement(Reader $reader, Upk_Archive $upkArc, $debugFlag = false)
    {
        self::$debugFlag = $debugFlag;

        if ($debugFlag) {
            echo '<br/>';
            echo 'Offset: ' . sprintf("0x%08X<br/>", $reader->getPosition());
        }

        $nameTable = $upkArc->getNameTable();

        $nameId = $reader->readInt64();
        $nameStr = $nameTable->getNameById($nameId);

        if ($debugFlag) {
            echo '  Name: ' . $nameStr . '<br/>';
        }

        if ($nameStr === 'None') {
            return null;
        }

        $dataTypeId = $reader->readInt64();
        $dataTypeStr = $nameTable->getNameById($dataTypeId);

        if ($debugFlag) {
            echo '  Type: ' . $dataTypeStr . '<br/>';
        }

        return self::createElement($dataTypeStr, $nameStr, $reader, $upkArc);
    }

    /**
     * @param string $dataType
     * @param string $elemName
     * @param Reader $reader
     * @param Upk_Archive $upkArc
     * @return Upk_File_ElementInterface
     * @throws Exception
     */
    private static function createElement($dataType, $elemName, Reader $reader, Upk_Archive $upkArc)
    {
        $baseName = 'Upk_File_Element_';
        $className = $baseName . $dataType;

        if (substr($dataType, -8) === 'Property') {
            $typeName = substr($dataType, 0, -8);
            $className = $baseName . 'Property_' . $typeName;
        } elseif (!class_exists($className)) {
            throw new Exception("Can't parse element:\nName = $elemName\nData Type = $dataType");
        }

        if (self::$debugFlag) {
            echo ' Class: ' . $className . '<br/>';
        }

        return new $className($elemName, $dataType, $reader, $upkArc, self::$debugFlag);
    }

}
