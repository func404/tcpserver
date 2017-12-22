<?php
namespace tcp;

class Server
{

    public static function onConnect($server, $fd)
    {
        $connection = $server->connection_info($fd);
        if (! in_array($connection['remote_ip'], Config::ipAllow)) {
            /* ipAllow 表示本地请求，对本地请求不做登录超时限制限制 */
            Cache::getInstance()->hSet(Config::caches['connections'], $fd, json_encode(array_merge($connection, [
                'login_id' => 0,
                'device_id' => 0
            ])));
            
            $log =  date('Y-m-d H:i:s').':'.implode('|', $connection) . " is Connected [{$fd}]";
            echo $log . "\n";
            // 定时器，loginTimeout 后自动断开
            swoole_timer_after(Config::loginTimeout, [
                Timer::class,
                'onLoginTimeout'
            ], [
                'server' => $server,
                'fd' => $fd,
                'connection' => $connection
            ]);
        }
    }

    public static function onReceive($server, $fd, $from_id, $data)
    {
        $client = $server->connection_info($fd);
        if (in_array($client['remote_ip'], Config::ipAllow)) {
            $headers = json_decode($data, true);
            if (! $headers) {
                $server->close($fd);
            }
            $data = $headers['data'];
            $server->send($fd, Config::serverMap[$headers['command']]);
            call_user_func_array([
                new Work(),
                Config::serverMap[$headers['command']]
            ], [
                $server,
                $fd,
                $from_id,
                $data,
                $headers,
                $client
            ]);
            return false;
        } else {
            $bytes = (new Byte())->unpack($data);
            $headers = $bytes->headers;
            // 验证请求头
            foreach ($headers as $header) {
                if (strlen($header) == 0) {
                    $response = (new Byte())->setSn($headers['sn'] ++)
                        ->response(Error::packageStructureError)
                        ->pack();
                    Logger::getInstance()->write('PackageStructureError:' . Error::packageStructureError, 'error');
                    Logger::getInstance()->write('RequestFrom:' . $client['remote_ip'], 'error');
                    $server->send($fd, $response);
                    $server->close($fd);
                    return false;
                }
            }
            
            // 验证校验和
            if (! $headers['is_checked']) {
                $response = (new Byte())->setSn($headers['sn'] ++)
                    ->response(Error::packageCheckSumError)
                    ->pack();
                Logger::getInstance()->write('PackageCheckSumError:' . Error::packageCheckSumError, 'error');
                Logger::getInstance()->write('RequestFrom:' . $client['remote_ip'], 'error');
                $server->send($fd, $response);
                $server->close($fd);
                return false;
            }
        }
        
        // 验证命令
        if (in_array($headers['command'], array_keys(Config::serverMap))) {
            call_user_func_array([
                new Work(),
                Config::serverMap[$headers['command']]
            ], [
                $server,
                $fd,
                $from_id,
                $data,
                $headers,
                $client
            ]);
        } else {
            $response = (new Byte())->setSn($headers['sn'] ++)
                ->response(Error::invalidCommand)
                ->pack();
            Logger::getInstance()->write('InvalidCommand:' . Error::invalidCommand, 'error');
            Logger::getInstance()->write('Command:' . $headers['command'], 'error');
            Logger::getInstance()->write('RequestFrom:' . $client['remote_ip'], 'error');
            $server->send($fd, $response);
            $server->close($fd);
            return false;
        }
    }

    public static function onClose($server, $fd)
    {
        $fdinfo = $server->connection_info($fd);
        $c = Cache::getInstance()->hGet(Config::caches['connections'], $fd);
        if (! empty($c)) {
            
            $c = json_decode($c);
            if ($c->device_id) {
                Cache::getInstance()->hDel(Config::caches['clients'], $c->device_id);
                echo date('Y-m-d H:i:s').':'.$c->device_id  . " is disconnected [{$fd}]\n";
            }
        }
        Logger::getInstance()->write('Close:' . $fd, 'server');
        Cache::getInstance()->hDel(Config::caches['connections'], $fd);
        
    }

    public static function onStart($server)
    {
        $content = json_encode($server);
        Logger::getInstance()->write($content, 'server');
        $run_log = "
                     _       __    __    _____,
                    | |      \ \  / /   / ____|       
 __      __      __ | |       \ \/ /   | (___
 \ \    /  \    / / | |        \ \/     \___ \
  \ \  / /\ \  / /  | |        /\ \         ) |
   \ \/ /  \ \/ /   | |___/|  / /\ \    ____/ /
    \__/    \__/    |______| /_/  \_\  |_____/                     
                               
";
        // ,"onRequest":null,"onHandShake":null,"onMessage":null,"onOpen":null,"host":"0.0.0.0","port":9566,"type":1,"sock":3,"setting":{"worker_num":8,"daemonize"
        // :false,"max_request":10000,"dispatch_mode":2,"debug_mode":1}}],"master_pid":20427,"manager_pid":20428,"worker_id":0,"taskworker":false,"worker_pid":0}
        
        $run_log .= Logger::getInstance()->fetch(implode('|', [
            $server->master_pid,
            $server->manager_pid,
            $server->port
        ])) . "\n";
        Logger::getInstance()->write($run_log, 'server');
        echo "Server is started:[" . date("Y-m-d H:i:s") . "]\n";
        echo "host[{$server->host}],port[{$server->port}],process[{$server->setting['worker_num']}],max_request[{$server->setting['max_request']}]\n";
        echo "Pid[{$server->master_pid}] is stored in {$server->setting['pid_file']}\n";
        fwrite(STDOUT, "Process is started\n");
    }

    public static function onShutdown($server)
    {
        $content = 'Server has been shutdown [' . date("Y-m-d H:i:s") . "]\n";
        Logger::getInstance()->write($content, 'server');
        $pidFile = $server->setting['pid_file'];
        if (is_file($pidFile)) {
            unlink($pidFile);
        }
        echo 'Server has been shutdown [' . date("Y-m-d H:i:s") . "]\n";
    }

    public static function onWorkerStart($server, $worker_id)
    {
        ;
    }

    public static function onWorkerStop($server, $worker_id)
    {
        ;
    }

    public static function onTimer($server, $interval)
    {
        ;
    }

    public static function onPacket($server, $data, $client_info)
    {
        ;
    }

    public static function onBufferFull($server, $fd)
    {
        ;
    }

    public static function onBufferEmpty($server, $fd)
    {
        ;
    }

    public static function onTask($server, $task_id, $src_worker_id, $data)
    {
        ;
    }

    public static function onFinish($server, $task_id, $data)
    {
        ;
    }

    public static function onWorkerError($server, $worker_id, $worker_pid, $exit_code, $signal)
    {
        ;
    }

    public static function onPipeMessage($server, $from_worker_id, $message)
    {
        ;
    }

    public static function onManagerStart($server)
    {
        ;
    }

    public static function onManagerStop($server)
    {
        ;
    }
}

?>
