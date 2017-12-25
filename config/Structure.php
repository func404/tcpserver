<?php
namespace config;

abstract class Structure implements \ArrayAccess
{

    public function __construct(array $attributes = [])
    {
        if ($attributes) {
            foreach ($attributes as $attribute => $type) {
                settype($this->$attribute, $type);
            }
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see ArrayAccess::offsetExists()
     */
    public function offsetExists($offset)
    {
        ;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see ArrayAccess::offsetGet()
     */
    public function offsetGet($offset)
    {
        ;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see ArrayAccess::offsetSet()
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
     * {@inheritdoc}
     *
     * @see ArrayAccess::offsetUnset()
     */
    public function offsetUnset($offset)
    {
        ;
    }

    /**
     * 禁止设置不在此类中的属性
     *
     * @param string $offset            
     * @param string $value            
     * @return boolean
     */
    public function __set($offset, $value)
    {
        if (! isset($this->$offset)) {
            return false;
        }
    }

    /**
     * 转换成数组
     *
     * @return array
     */
    public function toArray()
    {
        return (array) $this;
    }
}