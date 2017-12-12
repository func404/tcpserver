<?php
namespace tcp;

class Message
{

    private static $instance;

    public  $message = [
        'cmd' => 'CMD',
        'data' => [
            'p1' => 'p1'
        ]
    ];

    public function __construct()
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

    public function __invoke()
    {
        ;
    }
}