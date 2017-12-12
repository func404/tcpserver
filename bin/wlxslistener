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

define('DAEMONIZE', true);
define('PID_FILE', '/var/run/wlxs_listener.pid');

if (php_sapi_name() != "cli") {
    die("Only run in command line mode\n");
}

if (DAEMONIZE) {
    cli_set_process_title('wlxs_listener');
    $handle = fopen(PID_FILE, 'r');
    $pid  = trim(fgets($handle));
    fclose($handle);
    
    if ($argc < 2) {
        $action = 'start';
    } else {
        $action = $argv[1];
    }
    
    if ($action == 'stop') {
        if ($pid) {
            exec('ps p ' . $pid, $tmp);
            if (count($tmp) > 1) {
                $rst = posix_kill($pid, 9);
                fwrite(STDOUT, 'Process is killed ' . $pid . "\n");
            }else{
                fwrite(STDOUT, 'Pid is not exists: ' . $pid . "\n");
            }
            $handle = fopen(PID_FILE, 'w');
            fclose($handle);
            exit();
        } else {
            fwrite(STDOUT, "Process is not exists\n");
        }
    } else {
        if ($pid) {
            exec('ps p ' . $pid, $tmp);
            $next=0;
            if (count($tmp) > 1) {
                fwrite(STDOUT, "This Process is runing[{pid}],please input 1 [skip and exit] ,or 2 [kill and start again] ,default 1: ");
                $next = trim(fgets(STDIN));
            
                if ($next == 2) {
                    posix_kill($pid, 9);
                }else{
                    fwrite(STDOUT, 'Process is running ' . $pid . "[not restart!]\n");
                    exit();
                }
            }
        }
        // get input
    }
    
    $pid = pcntl_fork();
    if (- 1 === $pid) {
        throw new Exception('fork fail');
    } elseif ($pid > 0) {
        exit(0);
    }
    if (- 1 === posix_setsid()) {
        throw new Exception("setsid fail");
    }
    // Fork again avoid SVR4 system regain the control of terminal.
    $pid = pcntl_fork();
    if (- 1 === $pid) {
        throw new Exception("fork fail");
    } elseif (0 !== $pid) {
        exit(0);
    }
    $handle = fopen(PID_FILE, 'w');
    fwrite($handle, posix_getpid());
    fclose($handle);
    fwrite(STDOUT, 'Process is running ' . posix_getpid() . "\n");
}

$client = new \swoole_client(SWOOLE_TCP | SWOOLE_KEEP);
$client->connect(TCP_HOST, TCP_PORT, - 1);

$redis = new \Redis();
$redis->pconnect(REDIS_HOST, REDIS_PORT, 0);
$redis->auth(REDIS_AUTH);
$redis->setOption(\Redis::OPT_READ_TIMEOUT, - 1);

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

/**
 * 处理TCP发送的请求
 *
 * @param unknown $message            
 */
function dealServer($message)
{
    echo $message . "\n";
}
$redis->subscribe([
    'wlxs_clientChannel',
    'wlxs_serverChannel'
], function ($i, $channel, $message) use ($client) {
    switch ($channel) {
        case 'wlxs_clientChannel':
            dealClient($client, $message);
            break;
        case 'wlxs_serverChannel':
            dealServer($message);
            break;
        default:
            break;
    }
});
