<?php
namespace lib;

class Logger
{

    private static $instance;

    private static $type = 3;

    private static $destination = '/data/log/wlxs';

    private static $extra_headers = [];

    public function __construct($configs = [])
    {
        ;
    }

    public static function getInstance()
    {
        if (! (self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 记录 文本日志
     *
     * @param mixed $resourse            
     * @param string $action            
     * @return boolean
     */
    public function write($resourse, $action)
    {
        $content = $this->fetch($resourse);
        $dt = date('Y-m-d H:i:s');
        $d = date('Y-m-d');
        
        if (! is_dir(self::$destination)) {
            if (! mkdir(self::$destination)) {
                return false;
            }
        }
        error_log($dt . ':' . $content . "\n", self::$type, self::$destination . '/' . $action . '_' . $d . '.log');
    }

    /**
     * 记录 二进制日志
     *
     * @param mixed $resourse            
     * @param string $action            
     * @return boolean
     */
    public function writeBin($binData, $action)
    {
        $content = $this->fetchBin($binData);
        
        $dt = date('Y-m-d H:i:s');
        $d = date('Y-m-d');
        
        if (! is_dir(self::$destination)) {
            if (! mkdir(self::$destination)) {
                return false;
            }
        }
        
        error_log($dt . ':' . $content . "\n", self::$type, self::$destination . '/' . $action . '_' . $d . '.log');
    }

    /**
     * 获取日志内容
     *
     * @param mixed $resourse            
     * @return string
     */
    public function fetch($resourse)
    {
        if (is_scalar($resourse)) {
            $content = $resourse;
        } else {
            $content = var_export($resourse, true);
        }
        return $content;
    }

    /**
     * 获取二进制日志内容
     *
     * @param mixed $resourse            
     * @return string
     */
    public function fetchBin($binData)
    {
        $length = strlen($binData);
        
        if ($length == 0) {
            return false;
        }
        
        $data = unpack('C' . $length, $binData);
        $content = implode(',', $data);
        return $content;
    }
}
