<?php
namespace tcp;

class Start
{

    private $server;

    private $config = [];

    private $opt = [];

    private static $instance;

    /**
     * 自动加载类
     */
    public function loadClass()
    {
        set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);
        spl_autoload_register(function ($class_name) {
            if (preg_match('/^(tcp)/', $class_name)) {
                if ($class_name) {
                    [
                        $ns,
                        $class
                    ] = explode('\\', $class_name);
                    include_once $class . '.php';
                }
            }
        });
    }

    public function run()
    {
        date_default_timezone_set(Config::timezone);
        $this->config = array_merge($this->config, Config::tcpServer);
        $this->server = new \swoole_server($this->config['host'], $this->config['port']);
        
        $this->opt = array_merge($this->opt, Config::tcpServerOpt);
        $this->server->set($this->opt);
        
        $this->server->on('Start', [
            'tcp\Server',
            'onStart'
        ]);
        $this->server->on('Connect', [
            'tcp\Server',
            'onConnect'
        ]);
        $this->server->on('Receive', [
            'tcp\Server',
            'onReceive'
        ]);
        $this->server->on('Close', [
            'tcp\Server',
            'onClose'
        ]);
        $this->server->start();
    }
}

$s = new Start();
$s->loadClass();
date_default_timezone_set(Config::timezone);
$s->run();