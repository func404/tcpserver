<?php
namespace tcp;

class Observer
{

    private static $instance;

    private $tcpClient;

    public static function getInstance()
    {
        if (! (self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function listener($instance, $channel, $message)
    {
        call_user_func([
            $this,
            $channel
        ], $message);
    }

    public function clientChannel($message)
    {
        $message = json_decode($message);
        call_user_func([
            $this,
            Config::$message->cmd
        ], $message->data);
    }

    public function serverChannel()
    {
        $message = json_decode($message);
        call_user_func([
            $this,
            Config::$message->cmd
        ], $message->data);
    }

    public function deviceMonitor()
    {
        ;
    }

    /**
     * 通知服务器开门
     * 
     * @param array $data            
     */
    public function openDoor($data)
    {
        $data = [
            'device_number' => $data['device_number'],
            'transaction_id' => $data['transaction_id'],
        ];
        Client::getInstance()->send($data);
        Client::getInstance()->recv();
    }

    public function closeDoor($data)
    {
        Api::getInstance()->pay($data['close_id']);
    }

    public function dealOrder($data)
    {
        ;
    }
}