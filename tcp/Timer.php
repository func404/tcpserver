<?php
/**
 * 定时器
 */
namespace tcp;

use lib\Cache;
use config\Config;
use config\Error;
use lib\DB;

class Timer
{

    /**
     * 客户端连接后需要马上登录 如没有则需要断开
     *
     * @param array $info
     *            [
     *            'server' => $server,
     *            'fd' => $fd,
     *            'connection'=>$connection
     *            ];
     */
    public static function onLoginTimeout($param)
    {
        $connection = Cache::getInstance()->hGet(Config::caches['connections'], $param['fd']);
        $connection = json_decode($connection, true);
        // 登录ID为0 则为超时
        if (0 == $connection['login_id']) {
            $data = Byte::getInstance()->response(Error::loginTimeOut)->pack();
            if ($param['server']->exist($param['fd'])) {
                $param['server']->send($param['fd'], $data);
                $param['server']->close($param['fd']);
                ;
            }
            Cache::getInstance()->hDel(Config::caches['connections'], $param['fd']);
        }
    }

    /**
     * 定时器
     *
     * @param int $interval            
     * @param Object $server            
     */
    public static function checkConnections($interval, $server)
    {
        $clients = Cache::getInstance()->hGetAll(Config::caches['clients']);
        $time = time();
        foreach ($clients as $deviceId => $clientJson) {
            $client = json_decode($clientJson);
            if ($client->last_time < $time - Config::heartbeat) {
                DB::getInstance()->update('wl_devices', [
                    'connecting' => 1
                ], [
                    'device_id' => $deviceId
                ]);
                Cache::getInstance()->hDel(Config::caches['connections'], $client->fd);
                Cache::getInstance()->hDel(Config::caches['clients'], $deviceId);
                
                if ($server->exist($client->fd)) {
                    $data = Byte::getInstance()->response(Error::heartBeatTimeout)->pack();
                    $server->send($client->fd, $data);
                    $server->close($client->fd);
                }
                Cache::getInstance()->publish(Config::broadcastChannels['device'], 'Device offline:' . $deviceId . '|' . date("Y-m-d H:i:s", $time));
            }
        }
    }

    /**
     * 登录发送标签超时
     *
     * @param array $loginInfo            
     */
    public static function loginTagsTimeout($login_info, $server)
    {
        if ($server->exist($login_info['fd'])) {
            $data = Byte::getInstance()->response(Error::sendLoginTagsTimeout)->pack();
            return $server->send($login_info['fd'], $data);
        } else {
            return false;
        }
    }

    /**
     * 关门上传标签超时
     *
     * @param array $transaction_info            
     * @param Object $server            
     */
    public static function closeTagsTimeout($transaction_info, $server)
    {
        if ($server->exist($transaction_info['fd'])) {
            $data = Byte::getInstance()->response(Error::sendCloseTagsTimeout)->pack();
            return $server->send($transaction_info['fd'], $data);
        } else {
            return false;
        }
    }
}
