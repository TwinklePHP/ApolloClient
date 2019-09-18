<?php
/**
 * Created by PhpStorm.
 * User: huanjin
 * Date: 2019/08/31
 * Time: 16:48
 */

define('CONF_DIR', isset($_SERVER['CONF_DIR']) ? $_SERVER['CONF_DIR'] : __DIR__);
define('CONF_NAMESPACE', isset($_SERVER['CONF_NAMESPACE']) ? $_SERVER['CONF_NAMESPACE'] : 'application');

error_reporting(E_ALL);
$autoLoader = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoLoader)) {
    echo "Composer autoloader not found: $autoLoader" . PHP_EOL;
    echo "Please issue 'composer install' and try again." . PHP_EOL;
    exit(1);
}
require $autoLoader;