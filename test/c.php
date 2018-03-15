<?php
/**
 * tcp client  test
 * 2017-11-19
 * @author DuXin
 */

include '../tcp/Byte.php';
include '../lib/Cache.php';
include '../config/Config.php';
date_default_timezone_set(config\Config::timezone);
include '../lib/DB.php';
$device = lib\DB::getInstance()->getDeviceByNo('8a4ac9177f75860f9f0ca20e94b9391d');
print_r($device);
