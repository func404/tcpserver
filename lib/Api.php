<?php
namespace lib;

class Api
{

    private static $base_url = 'http://apis.weilaixiansen.com/rest';

    private static $token = 'ktusH3d7K5j7Z0hzhL9s7tfz3OIipwCmJMW0tjAZCT4RRrkUeZhfkDFFLW3IrAcphJTPFlod2gWiGVMi24mRqsmyq77eMaLDibbrIzWegldbryHxU5PGpHdQa2fAsTvf4uXviTOPtenNsKcqS+1gTgqUdt+d+YsWO0uPv6MOseU=';

    private static $instance;

    private static $rest;

    public function __construct(string $base_url = '', string $token = '')
    {
        if (! self::$instance) {
            self::$rest = RestfulClient::getInstance()->setRequestType('fsocket');
            if ($base_url) {
                self::$base_url = $base_url;
            }
            if ($token) {
                self::$token = $token;
            }
        }
    }

    public static function getInstance(string $base_url = '', string $token = '')
    {
        if (! (self::$instance instanceof self)) {
            self::$instance = new self($base_url, $token);
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
        return self::$rest->post([
            'token' => self::$token,
            'user_id' => $user_id
        ], self::$base_url . '/user/detail')->getResponseBody();
    }

    /**
     * 获取用户基本信息 [主表]
     *
     * @param int $user_id            
     */
    public function getUserBaseById($user_id)
    {
        return self::$rest->post([
            'token' => self::$token,
            'user_id' => $user_id
        ], self::$base_url . '/user/base')->getResponseBody();
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
        return self::$rest->post([
            'token' => self::$token,
            'type' => $type,
            'third_id' => $third_id
        ], self::$base_url . '/user/detail/third')->getResponseBody();
    }

    /**
     * 根据用户手机返回用户详细信息
     *
     * @param string $phone            
     */
    public function getUserByPhone($phone)
    {
        return self::$rest->post([
            'token' => self::$token,
            'phone' => $phone
        ], self::$base_url . '/user/detail/phone')->getResponseBody();
    }

    /**
     * 根据用户第三方类型和手机返回与类型匹配用户信息
     *
     * @param int $third_type            
     * @param string $phone            
     */
    public function getUserThirdInfoByPhone($third_type, $phone)
    {
        return self::$rest->post([
            'token' => self::$token,
            'phone' => $phone,
            'type' => $third_type
        ], self::$base_url . '/user/third/phone')->getResponseBody();
    }

    public function addUser($userInfo = [])
    {
        $post = array_merge($userInfo, [
            'token' => self::$token
        ]);
        return self::$rest->post($post, self::$base_url . '/user/add')->getResponseBody();
    }

    public function addThirdUser($type, $third_id, $phone = '')
    {
        return self::$rest->post([
            'token' => self::$token,
            'phone' => $phone,
            'type' => $third_type,
            'third_id' => $third_id
        ], self::$base_url . '/user/add/third')->getResponseBody();
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

    public function createOpenDoorOrder($device_id, $user_id, $pay_channel_id = 0)
    {
        return self::$rest->post([
            'token' => self::$token,
            'device_id' => $device_id,
            'user_id' => $user_id,
            'pay_channel_id' => $pay_channel_id
        ], self::$base_url . '/order/createandopendoor')->getResponseBody();
    }

    public function closeDoorAndPay($device_id, $transaction_number)
    {
        return self::$rest->post([
            'token' => self::$token,
            'device_id' => $device_id,
            'transaction_number' => $transaction_number
        ], self::$base_url . '/order/closedoorandpay')->getResponseBody();
    }

    public function createBookOrder($device_id, $user_id)
    {
        ;
    }

    public function getOrderList($user_id = 0, $device_id = 0, $status = 0, $date_from = 0, $date_to = '', $limit = 0, $offset = 0)
    {
        return self::$rest->post([
            'token' => self::$token,
            'order_id' => $order_id,
            'device_id' => $device_id,
            'status' => $status,
            'date_from' => $date_from,
            'date_to' => $date_to
        ], self::$base_url . '/order/list/user')->getResponseBody();
    }

    public function getOrderDetail($order_id)
    {
        return self::$rest->post([
            'order_id' => $order_id
        ], self::$base_url . '/order/detail')->getResponseBody();
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
        return self::$rest->post([
            'token' => self::$token,
            'device_id' => $device_id,
            'transaction_number' => $transaction_number
        ], self::$base_url . '/device/close')->getResponseBody();
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

    public function getDeviceInfoByDeviceId($device_id)
    {
        return self::$rest->post([
            'token' => self::$token,
            'device_id' => $device_id
        ], self::$base_url . '/device/detail')->getResponseBody();
    }

    public function getDeviceInfoByDeviceNo($device_number)
    {
        return self::$rest->post([
            'token' => self::$token,
            'device_number' => $device_number
        ], self::$base_url . '/device/detail/deviceno')->getResponseBody();
    }

    public function getDeviceStatusByDeviceId($device_id)
    {
        ;
    }

    public function getDeviceStatusByDeviceNumber($device_number)
    {
        ;
    }

    public function destoryDeviceByDeviceId($device_id)
    {
        ;
    }

    public function destoryDeviceByDeviceNumber($device_id)
    {
        ;
    }

    public function pauseDeviceByDeviceId($device_id)
    {
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

