#!/usr/bin/php
<?php
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__DIR__));

spl_autoload_register(function ($class_name) {
    if (preg_match('/^(tcp|lib)/', $class_name)) {
        if ($class_name) {
            [
                $ns,
                $class
            ] = explode('\\', $class_name);
            include_once $ns . DIRECTORY_SEPARATOR . $class . '.php';
        }
    }
});

function start()
{
    if(file_exists(tcp\Config::tcpServerOpt['pid_file'])){
        $handle = fopen(tcp\Config::tcpServerOpt['pid_file'], 'r');
        $pid  = trim(fgets($handle));
        fclose($handle);
        if ($pid) {
            exec('ps p ' . $pid, $tmp);
            $next=0;
            if (count($tmp) > 1) {
                fwrite(STDOUT, "This Process is runing[{$pid}],please input 1 [skip and exit] ,or 2 [kill and start again] ,default 1: ");
                $next = trim(fgets(STDIN));
                if ($next == 2) {
                    stop();
                    fwrite(STDOUT, "Process is stopping \n");
                    sleep(5);
                }else{
                    fwrite(STDOUT, 'Process is running ' . $pid . "[not restart!]\n");
                    exit();
                }
            }
        }
    }
    $config = tcp\Config::tcpServer;
    $server = new \swoole_server($config['host'], $config['port']);
    
    \swoole_set_process_name(tcp\Config::processName);
    $opt = tcp\Config::tcpServerOpt;
    $server->set($opt);
    
    $server->on('Start', [
        'tcp\Server',
        'onStart'
    ]);
    $server->on('Connect', [
        'tcp\Server',
        'onConnect'
    ]);
    $server->on('Receive', [
        'tcp\Server',
        'onReceive'
    ]);
    $server->on('Close', [
        'tcp\Server',
        'onClose'
    ]);
    
    $server->on('Shutdown', [
        'tcp\Server',
        'onShutdown'
    ]);
    
    fwrite(STDOUT, "Process is started \n");
    $server->start();
}

function stop($options = [])
{
    $pidFile = tcp\Config::tcpServerOpt['pid_file'];
    
    if (! file_exists($pidFile)) {
        echo "Pid file :{$pidFile} not exist \n";
        return;
    }
    $pid = file_get_contents($pidFile);
    if (! \swoole_process::kill($pid)) {
        echo "Pid :{$pid} not exist \n";
        return;
    }
    // 等待两秒
    $time = time();
    while (true) {
        usleep(1000);
        if (\swoole_process::kill($pid)) {
            echo "Server stop at " . date("y-m-d h:i:s") . "\n";
            if (is_file($pidFile)) {
                unlink($pidFile);
            }
            break;
        } else {
            if (time() - $time > 2) {
                echo "stop server fail.try --force again \n";
                break;
            }
        }
    }
}

function reload($options = [])
{
    $pidFile = tcp\Config::tcpServerOpt['pid_file'];
    $sig = SIGHUP;
    if (! file_exists($pidFile)) {
        echo "Pid file :{$pidFile} not exist \n";
        return;
    }
    $pid = file_get_contents($pidFile);
    opCacheClear();
    if (! \swoole_process::kill($pid, 0)) {
        echo "Pid :{$pid} not exist \n";
        return;
    }
    \swoole_process::kill($pid, $sig);
    echo "Server reload at " . date("y-m-d h:i:s") . "\n";
}

function opCacheClear()
{
    if (function_exists('apc_clear_cache')) {
        apc_clear_cache();
    }
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
}
date_default_timezone_set(tcp\Config::timezone);
$helper = <<<EOT
\033[10;36mstart\033[0m  Start Server!
\033[10;36mstop\033[0m    Stop Server!
\033[10;36mreload\033[0m    Reload Server!
status checkStatus[unsupported]!
EOT;

if ($argc != 2) {
    echo $helper."\n";
    return false;
}

switch ($argv[1]) {
    case "start":
        start();
        break;
    case 'stop':
        stop();
        break;
    case 'reload':
        reload();
        break;
    default:
        echo $helper."\n";
        exit();
}
