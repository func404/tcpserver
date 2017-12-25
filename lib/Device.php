<?php
namespace tcp;

class Device implements \ArrayAccess
{

    public $login_id = 0;

    public $fd = 0;

    public $device_id = 0;

    public $connect_time = 0;

    public $login_time = 0;

    public $last_time = 0;

    public $tags = 0;

    public $tags_uploaded = false;

    public $weight = 0;

    public $current_transaction = 'waiting';

    public $current_data = '';

    public $status = 0;

    public $sn = 0;

    public function __construct()
    {
        $this->sn = $this->sn % 256;
    }

    /**
     *
     * @param
     *            $offset
     */
    public function offsetExists($offset)
    {
        ;
    }

    /**
     *
     * @param
     *            $offset
     */
    public function offsetGet($offset)
    {
        ;
    }

    /**
     *
     * @param
     *            $offset
     * @param
     *            $value
     */
    public function offsetSet($offset, $value)
    {
        if (! isset($this->$offset)) {
            return false;
        } else {
            $this->$offset = $value;
        }
    }

    /**
     *
     * @param
     *            $offset
     */
    public function offsetUnset($offset)
    {
        ;
    }

    /**
     * 禁止设置不在此类中的属性
     *
     * @param unknown $offset            
     * @param unknown $value            
     * @return boolean
     */
    public function __set($offset, $value)
    {
        if (! isset($this->$offset)) {
            return false;
        }
    }

    public function toArray()
    {
        return (array) $this;
    }
}
