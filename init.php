<?php

// Check PHP version
if (!defined('PHP_VERSION_ID')) {
    $version = explode('.', PHP_VERSION);
    define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
}
if (PHP_VERSION_ID < 70000) {
    die('The application requires PHP 7.0 or higher.');
}
if (PHP_INT_SIZE !== 8) {
    die('The application requires PHP x64 only.');
}

// Set memory limit
ini_set('memory_limit', '256M');

// Set time limit to 5 minutes
set_time_limit(15 * 60);

// Default charset encoding
mb_internal_encoding("UTF-8");

// Default timezone
date_default_timezone_set('Europe/Minsk');

// Register autoload class function
spl_autoload_register(function ($className) {
    $className = str_replace('_', '/', $className);
    $classFileName = 'classes/' . $className . '.php';
    if (file_exists($classFileName)) {
        require_once($classFileName);
    }
});

Compression::setTmpDir('tmp/');
Compression::setExecLzma('/usr/local/arc/lzma.exe');
Compression::setExecLzo('/usr/local/arc/xcomlzo.exe');
