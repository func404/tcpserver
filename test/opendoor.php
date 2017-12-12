<?php
/**
 * tcp client  test
 * 2017-11-19
 * @author DuXin
 */
namespace tcp;

include 'Byte.php';
include 'Cache.php';
include 'Config.php';
date_default_timezone_set(Config::timezone);
include 'DB.php';
$client = new \swoole_client(SWOOLE_SOCK_TCP);
$client->connect('118.190.205.103', 9566, - 1);
$data = [
        'device_id' => 1000000001,
        'order_id' => 1223444 
 ];
$client->send(json_encode([
'command'=>'SHOPPING',
'data'=>$data
]));

                $string = $client->recv();
    if (! empty($string)) {
        $length = strlen($string);
        $decArr = unpack('C' . $length, $string);
        print_r($decArr);
    }
