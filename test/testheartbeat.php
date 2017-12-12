<?php
// 发送心跳包
$host = '127.0.0.1';
$port = 6379;
$pass = '!@#qweASD2017';
$redis = new \Redis();
$redis->pconnect($host, $port, 0);
$redis->auth($pass);
$redis->setOption(\Redis::OPT_READ_TIMEOUT, - 1);
do {
    $redis->publish('tcporder', 4);
    sleep(3);
} while (true);
