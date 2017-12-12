<?php
/**
 * tcp client 类
 * 2017-11-06
 * @author DuXin
 */
namespace tcp;

class Client
{

    private static $instance;

    public function __construct($config = null)
    {
        if (! self::$instance) {
            self::$instance = new \swoole_client(SWOOLE_TCP);
            $config = array_merge(Config::client, $config);
            self::$instance->connect($config['host'], $config['port'], $config['timeout'], $config['flag']);
        }
    }

    public static function getInstance($config = null)
    {
        if (! (self::$instance instanceof self)) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }
    
}