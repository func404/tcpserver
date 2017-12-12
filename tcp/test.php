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
include 'DB.php';
date_default_timezone_set(Config::timezone);
//$client = new \swoole_client(SWOOLE_SOCK_TCP);
$client = new \Swoole\Client(SWOOLE_SOCK_TCP);
$myTrim = function($str)
{
 $search = array(" ","　","\n","\r","\t");
 $replace = array("","","","","");
 return str_replace($search, $replace, $str);
};

$client->connect('127.0.0.1', 9566, - 1);
$host = '127.0.0.1';
$port = 6379;
$pass = '!@#qweASD2017';
$redis = new \Redis();
$redis->pconnect($host, $port, 0);
$redis->auth($pass);
$redis->setOption(\Redis::OPT_READ_TIMEOUT, - 1);
$redis->subscribe([
    'tcporder'
], function ($i, $c, $m) use ($client,$myTrim) {
    if (! $client->isConnected()) {
        echo "client is disconnected!\n";
        return false;
    }
    $login_id = time();
    $byte = new Byte();
    $allTagss = file('testalltags.csv');
    $salelefts = file('testonsale.csv');
    $saleds = file('testshop.csv');
foreach($allTagss as $t2){
      $allTags[]= trim($t2);
}
foreach($salelefts as $t){
      $saleleft[]= trim($t);
}
foreach($saleds as $t1){
      $saled[]= trim($t1);
}
    $sn = 0;
    $string = '';
    switch ($m) {
        case 1:
            $tagsAmount = count($allTags);
            $loadArr = [
                'protocol_version' => '00000002',
                'hardware_version' => 'h0000001',
                'softeare_version' => 's0000001',
                'device_number' => '01ad555d15e4cf134f9e6c550b79522c',
                'device_key' => '61f2c1daaad64484a58187b49da4fc0e',
                'status' => bindec(00000010), // Bit7：0-关门 1-开门 Bit6:0-无重力传感器 1-
                'tags_amount' => $tagsAmount, // 柜子标签总数，高字节在前
                'weight' => 0,
                'transaction_number' => $login_id
            ];
            $data = $byte->setSn($sn ++)
                ->setLogin($loadArr)
                ->pack();
            $client->send($data);
            $string = $client->recv();
            $sn ++;
            break;
        case 2: // 分批上tag
            Cache::getInstance()->set('alltgasonsale', json_encode($allTags));
            $tagsPackages = array_chunk($allTags, 5);
            $tagsPackagesCount = count($tagsPackages) - 1;
            if ($tagsPackagesCount > 0) {
                for ($i = 0; $i < $tagsPackagesCount; $i ++) {
                    $data = $byte->setSn($sn ++)
                        ->setLoginTags($tagsPackages[$i], 0)
                        ->pack();
                    $client->send($data);
                    $string = $client->recv();
                }
                $data = $byte->setSn($sn ++)
                    ->setLoginTags($tagsPackages[$tagsPackagesCount], 1)
                    ->pack();
                $client->send($data);
                $string = $client->recv();
            } else {
                $data = $byte->setSn($sn ++)
                    ->setLoginTags($tagsPackages[0], 1)
                    ->pack();
                $client->send($data);
                $string = $client->recv();
            }
            $sn ++;
            break;
        case 3:
            $data = $byte->setHeader()
                ->setCommand(2)
                ->setLoginTags([
                2123,
                2234,
                234
            ], 1)
                ->pack();
            $client->send($data);
            $string = $client->recv();
            break;
        case 4:
            $data = $byte->setSn($sn ++)
                ->setHeartbeat()
                ->pack();
            $client->send($data);
            $string = $client->recv();
            $sn ++;
            break;
        case 5: // 开门盘存
            $device = Cache::getInstance()->hGet(Config::caches['clients'], '1000000001');
            $device = json_decode($device);
            $trid =  DB::getInstance()->insert('wl_device_door_logs', [
               'login_id'=>$device->login_id,
                'device_id'=>1000000001,
                'status'=>0,
                'action'=>0,
                'open_time'=>date("Y-m-d H:i:s")
                
            ],true);
            $device->current_data = $trid;
            $device->current_transaction='shopping';
            Cache::getInstance()->hSet(Config::caches['clients'], '1000000001',json_encode($device));
            break;
        case 6: // 关门盘存发送
            $onsaleJson = Cache::getInstance()->get('alltgasonsale');
            $onsale = json_decode($onsaleJson, true);
            $saled = array_unique(array_diff($onsale,$saleleft));
            $device = Cache::getInstance()->hGet(Config::caches['clients'], '1000000001');
            $device = json_decode($device);
            $tr = $device->current_data;
            $arr = [
                'status' => 0, // 开关门状态 0 关门 1 开门
                'different_count' => count($saled),
                'weight' => 0x0010f001,
                'transaction_number' => $tr
            ];
            
            $client->send($byte->setSn($sn ++)
                ->setClosedoor($arr)
                ->pack());
             $string = $client->recv();
            $sn ++;
            
            if (count($saled)) {
                $client->send($byte->setSn($sn ++)
                    ->setTransactionTags([],$saled, 1)
                    ->pack());
                $string = $client->recv();
                $sn ++;
            }
    }
if(!empty($string)){    
    $length = strlen($string);
    $decArr = unpack('C' . $length, $string);
    print_r($decArr);
}});
