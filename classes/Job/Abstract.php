<?php

/**
 * Class Job_Abstract
 */
abstract class Job_Abstract
{
    /** @var string */
    protected $platform = '';

    /** @var int */
    protected $byteOrder = null;

    /**
     * @var array
     */
    protected $byteOrderList = array(
        'PC' => Reader::PC,
        'PS3' => Reader::PS3,
        'XBOX' => Reader::XBOX,
        'PSVITA' => Reader::PSVITA
    );

    /** @var array */
    protected $fileNamesIndexes = array();

    /**
     * @param string $platform
     * @return int
     */
    protected function getByteOrderFromPlatform($platform)
    {
        return $this->byteOrderList[strtoupper($platform)];
    }

    /**
     * @param string $path
     * @param string $arcName
     * @return string
     */
    protected function searchArcFileName($path, $arcName, $extensionsList = array())
    {
        if (!isset($this->fileNamesIndexes[$path])) {
            $this->fileNamesIndexes[$path] = array();

            $filesList = $this->scanDir($path, $extensionsList, true);

            foreach ($filesList as $filePath) {
                if (!is_dir($filePath)) {
                    $pathInfo = pathinfo($filePath);
                    $this->fileNamesIndexes[$path][strtoupper($pathInfo['filename'])] = $filePath;
                }
            }
        }

        return $this->fileNamesIndexes[$path][strtoupper($arcName)];
    }

    /**
     * @param string $path
     * @param string[] $extensionsList
     * @param bool $scanSubFolders
     * @param bool $getFullPath
     * @return string[]
     */
    protected function scanDir($path, $extensionsList = array(), $scanSubFolders = false, $getFullPath = false)
    {
        $result = array();

        $path = rtrim($path, "/\\") . '/';

        $curFiles = scandir($path);
        foreach ($curFiles as $curFile) {
            if (($curFile === '.') || ($curFile === '..')) {
                continue;
            }

            if (is_dir($path . $curFile)) {
                // This is directory
                if ($scanSubFolders) {
                    // Recursively scan sub dir
                    $subFiles = $this->scanDir($path . $curFile . '/', $extensionsList, $scanSubFolders, true);
                    // Returned file list already filtered by extensions list. Just add it to result
                    $result = array_merge($result, $subFiles);
                }
            }
            else {
                // This is file

                if (!empty($extensionsList)) {
                    // Check file extension with $extensionsList
                    $curFileExt = strtoupper( pathinfo($curFile, PATHINFO_EXTENSION) );
                    if (!in_array($curFileExt, $extensionsList)) {
                        // skip this file
                        continue;
                    }
                }

                $result[] = $path . $curFile;
            }
        }

        if (!$getFullPath) {
            foreach ($result as &$item) {
                $item = substr($item, strlen($path));
            }
        }

        return $result;
    }

    /**
     * Init some variables with context
     * @param Context $context
     */
    public function init(Context $context)
    {
        $this->platform = strtoupper( $context->get('platform') );
        $this->byteOrder = $this->getByteOrderFromPlatform($this->platform);
    }

    /**
     * Do job
     */
    abstract public function run();

}
