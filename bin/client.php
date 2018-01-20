#!/usr/bin/php
<?php
/**
 * 连接服务器处理广播中的请求
 *
 * @author duxin
 */
set_time_limit(0);

define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_AUTH', '!@#qweASD2017');

define('TCP_HOST', '118.190.205.103');
define('TCP_PORT', 9888);

#define('DAEMONIZE', true);
define('DAEMONIZE', false);
define('PID_FILE', '/var/run/wlxs_listener.pid');
define('PID_NAME', 'listener');
if (php_sapi_name() != "cli") {
    die("Only run in command line mode\n");
}

if (DAEMONIZE) {
    include '../lib/Daemon.php';
    lib\Daemon::run(PID_FILE, PID_NAME)->init($argc,$argv);
}
$redis = new \Redis();
$redis->pconnect(REDIS_HOST, REDIS_PORT, 0);
$redis->auth(REDIS_AUTH);
$redis->setOption(\Redis::OPT_READ_TIMEOUT, - 1);
$redis->subscribe([
    'wlxs_clientChannel',
    'wlxs_serverChannel'
], function ($i, $channel, $message) {
    switch ($channel) {
        case 'wlxs_clientChannel':
           $rst= Client::getInstance()->send($message);
           var_dump($rst);
            break;
        case 'wlxs_serverChannel':
            dealServer($message);
            break;
        default:
            break;
    }
});

/**
 * 处理TCP发送的请求
 *
 * @param sting $message            
 */
function dealServer($message)
{
    error_log($message . "\n", 3, "/tmp/dx1222.log");
    echo $message . "\n";
}

class Client
{

    private static $client;
    private static $instance;

    public static function  getInstance($reconnect = false)
    {
         
        if (! self::$client or $reconnect) {
            self::$client = new \swoole_client(SWOOLE_TCP | SWOOLE_KEEP);
            self::$client->connect(TCP_HOST, TCP_PORT, - 1);
        }
    }

    public function send($message)
    {
        if (self::$client->send($message)) {
            return self::$client->recv();
        } else {
            if(self::getInstance(true)->send($message)){
                return self::$client->recv();
            }else {
                return false;
            }
        }
    }
}

/**
 * 处理客户端。API发来的请求
 *
 * @param Swoole $client            
 * @param String $message            
 */
function dealClient($client, $message)
{
    $client->send($message);
    $rst = $client->recv();
}
