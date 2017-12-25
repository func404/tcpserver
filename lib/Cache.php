<?php
namespace lib;

use config\Config;

/**
 * redis 操作类
 * 2017-11-06
 */
class Cache extends \Redis
{

    private static $instance;

    public function __construct()
    {
        if (! self::$instance) {
            self::$instance = parent::pconnect(Config::redis['host'], Config::redis['port'], 1);
            $this->auth(Config::redis['auth']);
        }
    }

    public static function getInstance()
    {
        if (! (self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}