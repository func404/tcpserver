<?php
namespace tcp;

use lib\RestClient;

class Api
{

    private static $router = [];

    private static $config = [
        'base_url' => 'http://api.weilaixiansen.com/rest/',
        'public_key' => '',
        'private_key' => '',
        'access_token' => ''
    
    ];

    private static $instance;

    private static $rest;

    public function __construct($config = [], $router = [])
    {
        if (! self::$instance) {
            self::$router = array_merge(self::$router, $router);
            self::$config = array_merge(self::$config, Config::rest, $config);
            self::$rest = RestClient::getInstance(self::$config);
        }
    }

    public static function getInstance($config = [], $router = [])
    {
        if (! (self::$instance instanceof self)) {
            self::$instance = new self($config, $router);
        }
        return self::$instance;
    }

    /**
     * 根据用户ID获取用户信息
     *
     * @param int $user_id
     *            用户信息
     * @return array {
     *         Baseinfo{}
     *         thirds[{'weixin'},{alipay}]
     *         }
     *        
     */
    public function getUserById($user_id)
    {
        ;
    }

    /**
     * 获取用户基本信息 [主表]
     *
     * @param int $user_id            
     */
    public function getUserBaseById($user_id)
    {
        ;
    }

    /**
     * 更加第三方用户ID获取用户信息
     *
     * @param int $type
     *            第三方用户类型
     * @param int $third_id
     *            第三方用户ID
     */
    public function getUserByThirdId($type, $third_id)
    {
        ;
    }

    /**
     * 根据用户手机返回用户详细信息
     *
     * @param string $phone            
     */
    public function getUserByPhone($phone)
    {
        ;
    }

    /**
     * 根据用户第三方类型和手机返回与类型匹配用户信息
     *
     * @param unknown $third_type            
     * @param unknown $phone            
     */
    public function getUserThirdInfoByPhone($third_type, $phone)
    {
        ;
    }

    public function addUser($userInfo = [])
    {
        ;
    }

    public function addThirdUser($type, $third_id)
    {
        ;
    }

    public function addThirdUserByPhone($type, $third_id, $phone)
    {
        ;
    }

    public function changePassword()
    {
        ;
    }

    public function changeUserPhone($user_id, $phone)
    {
        ;
    }

    public function sendSms($type, $message, $template = 0)
    {
        ;
    }

    public function createOpenDoorOrder($device_id, $user_id)
    {
        ;
    }

    public function createBookOrder($device_id, $user_id)
    {
        ;
    }

    public function getOrderList($user_id = 0, $device_id = 0, $status = 0, $date_from = 0, $date_to = '', $limit = 0, $offset = 0)
    {
        ;
    }

    public function getOrderDetail($order_id)
    {
        ;
    }

    public function getBookOrderList($user_id = 0, $device_id = 0, $status = 0, $date_from = 0, $date_to = '', $limit = 0, $offset = 0)
    {
        ;
    }

    public function getBookOrderDetail($order_id)
    {
        ;
    }

    public function deviceOpenDoor($device_id, $more = [])
    {
        ;
    }

    public function deviceCloseDoor($device_id, $transaction_number)
    {
        ;
    }

    /**
     *
     * @param array $arr            
     */
    public function addDevice($arr = [])
    {
        $arr = [
            ''
        ];
    }

    public function getDeviceStatusByDeviceId($device_id)
    {
        ;
    }

    public function getDeviceStatusByDeviceNumber($device_number)
    {
        ;
    }
    
    public function destoryDeviceByDeviceId($device_id){
        ;
    }
    
    
    public function destoryDeviceByDeviceNumber($device_id){
        ;
    }
    
    public function pauseDeviceByDeviceId($device_id){
        ;
    }

    public function addUserScore($user_id, $score = 0)
    {
        ;
    }

    public function reduceUserScore($user_id, $score = 0)
    {
        ;
    }

    public function createCards($type, $amount, $length = 4, $avalidate_from = 0, $avalidate_to = 0)
    {
        ;
    }

    public function consumeCard($user_id, $card_type, $item_id)
    {
        ;
    }

    public function getCardsByItemId($item_id)
    {
        ;
    }

    public function destroyCardsByItemId($item_id)
    {
        ;
    }

    public function getUserConsumeDCardsByItemID($user_id, $item_id)
    {
        ;
    }

    public function getPaymentChannels($type = 0, $available = fasle)
    {
        ;
    }

    public function payByOrderId($order_id)
    {
        ;
    }

    public function getOrderAmountByOrderId($order_id)
    {
        ;
    }

    public function getOrderDiscountByOrderId($order_id)
    {
        ;
    }

    public function payForOrderByDiscount($order_id, $discount = 0.00)
    {
        ;
    }

    public function getOrderDetailByOrderId($order_id)
    {
        ;
    }

    public function refundByOrderId($order_id, $amount)
    {
        ;
    }

    public function getGoodsForBook($device_id = 0, $date_from = 0, $date_to)
    {
        ;
    }

    public function getActives($type, $area_id = 0, $device_id)
    {
        ;
    }

    public function getShareLink()
    {
        ;
    }

    public function recharge($user_id, $amount, $buyer_id)
    {
        ;
    }

    public function getUserBenefitById($user_id)
    {
        ;
    }
}

