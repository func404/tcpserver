<?php
namespace tcp;

/**
 *
 * 未来鲜森通信打包解包 方式
 * 2017-11-05
 *
 * @todo 实现基本消息打包 解包
 * @author duxin
 *        
 */
class Byte
{

    /**
     * 接口对应关系
     *
     * @var array
     */
    private $map = [
        0x01 => 'getLogin',
        0x02 => 'getHeartbeat',
        0x03 => 'setOpendoor',
        0x04 => 'getClosedoor',
        0x05 => 'getTransactionTags',
        0x06 => 'getDeviceStatus',
        0x07 => 'getLoginTags',
        0x08 => 'setDeliverRequest',
        0x09 => 'getDeliverResponse',
        0x0a => 'setDeliverData',
        0x0b => 'setNeedAllTags',
        0x0c => 'getAllTagsCount',
        0x0d => 'getAllTags'
    ];

    public $header = 0XFFFF;

    public $length = 0X00;

    public $command = 0X00;

    public $sn = 0X00;

    public $flags = 0X0000;

    public $checksum = 0X00;

    public $isChecked = false;

    public $load = [];

    public $headers = [];

    public $requestData = '';

    public $responseData = '';

    private static $instance;

    public static function getInstance()
    {
        if (! (self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getHeader()
    {
        return $this->header;
    }

    public function getLength()
    {
        return $this->length;
    }

    public function getCommand()
    {
        return $this->command;
    }

    public function getSn()
    {
        return $this->sn;
    }

    public function getFlags()
    {
        return $this->flags;
    }

    public function getChecksum()
    {
        return $this->checksum;
    }

    public function setHeader($header = 0XFFFF)
    {
        $this->header = $header;
        return $this;
    }

    public function setLength($length)
    {
        $this->length = $length;
        return $this;
    }

    public function setCommand($command)
    {
        $this->command = $command;
        return $this;
    }

    public function setSn($sn)
    {
        $this->sn = $sn % 256;
        return $this;
    }

    public function setFlags($flags = 0)
    {
        $this->flags = $flags;
        return $this;
    }

    public function setChecksum($checksum)
    {
        $this->checkSum = $checksum;
        return $this;
    }

    /**
     * 解析二进制信息 并赋值load
     *
     * TARGET 末端函数
     *
     * @param string $string            
     * @return array 基本信息
     *         [
     *         'header' => '0xffff',
     *         'length' => 0x0001,
     *         'sn'=>0xff,
     *         'flags'=>'00',
     *         'checksum'=>02f
     *         'is_checked' => true //校验结果
     *         ]
     */
    public function unpack($string)
    {
        $return = [];
        $length = strlen($string);
        $decArr = unpack('C' . $length, $string);
        
        $this->requestData = implode(',', $decArr);
        
        $return['header'] = $this->header = hexdec(dechex($decArr[1]) . dechex($decArr[2]));
        
        $return['length'] = $this->length = hexdec(dechex($decArr[3]) . dechex($decArr[4]));
        
        $return['command'] = $this->command = $decArr[5];
        
        $return['sn'] = $this->sn = $decArr[6];
        
        $return['flags'] = $this->flags = hexdec(dechex($decArr[7]) . dechex($decArr[8]));
        
        $return['checksum'] = $this->checksum = $decArr[$length];
        
        $return['is_checked'] = $this->isChecked;
        $this->load = [];
        $checksum = 0;
        $this->load = [];
        for ($i = 1; $i < $length; $i ++) {
            if ($i > 8) {
                $this->load[] = $decArr[$i];
            }
            if ($i > 2) {
                $checksum += $decArr[$i];
            }
        }
        $checksum = $checksum % 256;
        
        if ($checksum == $this->checksum) {
            $return['is_checked'] = $this->isChecked = true;
        }
        
        $this->headers = $return;
        return $this;
    }

    /**
     * 打包信息
     *
     * TARGET 末端函数
     *
     * @return string 二进制信息
     * @example (new Byte())->setSn(123)->pack();
     *          (new Byte())->setLogin([])->pack();
     */
    public function pack()
    {
        $header = $this->bigInt2bytes($this->header);
        $this->length = count($this->load) + 5;
        $length = $this->bigInt2bytes($this->length);
        $command = $this->bigInt2bytes($this->command, 1);
        $sn = $this->bigInt2bytes($this->sn, 1);
        $flags = $this->bigInt2bytes($this->flags);
        $load = $this->load;
        
        $arr = array_merge($header, $length, $command, $sn, $flags, $load);
        
        $sum = 0;
        $str = '';
        foreach ($arr as $a) {
            $sum += $a;
            $str .= chr($a);
        }
        $this->checksum = ($sum - 255 - 255) % 256;
        $checksum = $this->bigInt2bytes($this->checksum, 1);
        
        foreach ($checksum as $ck) {
            $str .= chr($ck);
        }
        
        $this->responseData = implode(',', $arr);
        
        return pack('a' . strlen($str), $str);
    }

    public function isChecked()
    {
        return $this->isChecked;
    }

    public function load($data)
    {
        $this->load = $this->str2bytes($data);
    }

    /**
     * 整型数转化成数组 低字节在前 高字节在后
     *
     * TARGET 转换
     *
     * @param number $val            
     * @param number $bytes
     *            字节数 默认为 2个字节，可为1个字节
     * @return boolean[]
     */
    public function smallInt2bytes($val, $bytes = 2)
    {
        $bytesArr = [];
        for ($i = 0; $i < $bytes; $i ++) {
            $bytesArr[$i] = ($val >> ($i * 8) & 0xff);
        }
        return array_values($bytesArr);
    }

    /**
     * 整型数转化成数组
     * 高字节在前低字节在后
     *
     * TARGET 转换
     *
     * @param number $val            
     * @param number $bytes
     *            字节数 默认为 2个字节，可为1个字节
     * @return boolean[]
     */
    public function bigInt2bytes($val, $bytes = 2)
    {
        $bytesArr = [];
        for ($i = $bytes - 1; $i >= 0; $i --) {
            $bytesArr[$i] = ($val >> ($i * 8) & 0xff);
        }
        return array_values($bytesArr);
    }

    /**
     * 数组转化成整型数 低字节在前 高字节在后
     *
     * TARGET 转换
     *
     * @param array $bytesArr            
     * @return number
     */
    public function smallBytes2int($bytesArr = [])
    {
        $val = 0;
        $bytes = count($bytesArr);
        
        for ($i = 0; $i < $bytes; $i ++) {
            $val += ($bytesArr[$i] << ($i * 8));
        }
        
        return $val;
    }

    /**
     * 数组转化成整型数 高字节在前 低字节在后
     *
     * TARGET 转换
     *
     * @param array $bytesArr            
     * @return number
     */
    public function bigBytes2int($bytesArr = [])
    {
        $val = 0;
        $bytes = count($bytesArr);
        for ($i = 0; $i < $bytes; $i ++) {
            $val += ($bytesArr[$i] << (($bytes - $i - 1) * 8));
        }
        return $val;
    }

    /**
     * 将字符串转化为字节数组
     *
     * TARGET 转换
     *
     * @param string $str            
     * @return array
     */
    public function str2bytes($str = '')
    {
        settype($str, 'string');
        $len = strlen($str);
        $bytes = [];
        for ($i = 0; $i < $len; $i ++) {
            if (ord($str[$i]) >= 128) {
                $byte = ord($str[$i]) - 256;
            } else {
                $byte = ord($str[$i]);
            }
            $bytes[] = $byte;
        }
        return (array) $bytes;
    }

    /**
     * 将字节数组转化为字符串
     *
     * TARGET 转换
     *
     * @param array $str            
     * @return string
     */
    public function bytes2str($bytes = [])
    {
        $str = '';
        foreach ($bytes as $byte) {
            $str .= chr($byte);
        }
        return $str;
    }

    /**
     * 创建登录load
     *
     * TARGET Client_To_Server
     *
     * @param array $arr
     *            [
     *            'protocol_version' => '00000002',
     *            'hardware_version' => 'h0000001',
     *            'softeare_version' => 's0000001',
     *            'device_number' => '01ad555d15e4cf134f9e6c550b79522c',
     *            'device_key' => '61f2c1daaad64484a58187b49da4fc0e',
     *            'status' => bindec(00000010), // Bit7：0-关门 1-开门 Bit6:0-无重力传感器 1-
     *            'tags_amount' => 0x010a, // 柜子标签总数，高字节在前
     *            'weight' => 0x0000ff00,
     *            'transaction_number' => 'L0000000001'
     *            ];
     *            
     *            
     * @return \tcp\Byte
     */
    public function setLogin($loadArr)
    {
        $this->load = array_merge($this->str2bytes($loadArr['protocol_version']), $this->str2bytes($loadArr['hardware_version']), $this->str2bytes($loadArr['softeare_version']), $this->str2bytes($loadArr['device_number']), $this->str2bytes($loadArr['device_key']), $this->bigInt2bytes($loadArr['status'], 1), $this->bigInt2bytes($loadArr['tags_amount']), $this->bigInt2bytes($loadArr['weight'], 4), $this->str2bytes($loadArr['transaction_number']));
        
        return $this->setCommand(0x01);
    }

    /**
     * 上传标签
     *
     * TARGET Client_To_Server
     *
     * @param array $tags
     *            标签列表集合
     * @param number $is_last
     *            是否结束
     * @return \tcp\Byte
     */
    public function setLoginTags($tags = [], $is_last = 1)
    {
        $tagsStr = implode(',', $tags);
        $this->load = array_merge($this->bigInt2bytes(strlen($tagsStr)), $this->str2bytes($tagsStr), $this->bigInt2bytes($is_last, 1));
        
        return $this->setCommand(0x07);
    }

    /**
     * 设置心跳包 load 默认为空
     *
     * TARGET Client_To_Server
     *
     * @return \tcp\Byte
     */
    public function setHeartbeat()
    {
        return $this->setCommand(0x02);
    }

    /**
     * 开门指令
     *
     * TARGET Server_To_Client
     *
     * @param string $transaction_number            
     * @return \tcp\Byte
     */
    public function setOpendoor($transaction_number = '')
    {
        $this->load = $this->str2bytes($transaction_number);
        return $this->setCommand(0x03);
    }

    /**
     * 关门发送交易汇总
     *
     * TARGET Client_To_Server
     *
     * @param array $transaction
     *            [
     *            'status' => 0, //开关门状态 0 关门 1 开门
     *            'different_count' => 0x00f0,
     *            'weight' => 0x0010f001,
     *            'transaction_number' => 'T123456789'
     *            ];
     * @return \tcp\Byte
     */
    public function setClosedoor($transaction = [])
    {
        $this->load = array_merge($this->bigInt2bytes($transaction['status'], 1), $this->bigInt2bytes($transaction['different_count'], 2), $this->bigInt2bytes($transaction['weight'], 4), $this->str2bytes($transaction['transaction_number']));
        return $this->setCommand(0x04);
    }

    /**
     * 关门上传标签打包
     *
     * TARGET Client_To_Server
     *
     * @param array $more
     *            增加的标签
     * @param array $less
     *            减少的标签
     * @param number $is_last
     *            是否结束
     * @return \tcp\Byte
     */
    public function setTransactionTags($more = [], $less = [], $is_last = 1)
    {
        $tagsStr = $lessStr = $moreStr = '';
        ! empty($more) ? $moreStr = '+' . implode(',+', $more) : '';
        ! empty($less) ? $lessStr = '-' . implode(',-', $less) : '';
        
        if (! empty($more) || ! empty($less)) {
            $tagsStr = implode(',', [
                $moreStr,
                $lessStr
            ]);
        }
        $tagsStr = rtrim(ltrim($tagsStr, ','), ',');
        
        $this->load = array_merge($this->bigInt2bytes(strlen($tagsStr), 2), $this->str2bytes($tagsStr), $this->bigInt2bytes($is_last, 1));
        
        return $this->setCommand(0x05);
    }

    /**
     * 发送设备状态
     *
     * TARGET Client_To_Server
     *
     * @param number $status
     *            00000111
     *            bit7 门开关状态（0-关 1-开）
     *            bit6 灯开关状态（0-关 1-开）
     *            Bit5 RFID扫描状态（0-关 1-开）
     *            
     * @return \tcp\Byte
     */
    public function setDeviceStatus($status = 0)
    {
        $this->load = $this->bigInt2bytes($status, 1);
        return $this->setCommand(0x06);
    }

    /**
     * 给客户端发送下发大数据通知请求
     *
     * TARGET Server_To_client
     *
     * @return \tcp\Byte
     */
    public function setDeliverRequest()
    {
        return $this->setCommand(0x08);
    }

    /**
     * 客户端收到下发通知后，告知服务器可以下发
     *
     * TARGET Client_To_Server
     *
     * @param number $size
     *            分片大小固定为128个字节，（最后一个包 可以小于128个字节）
     * @return \tcp\Byte
     */
    public function setDeliverResponse($size = 128)
    {
        $this->load = $this->bigInt2bytes($size, 2);
        return $this->setCommand(0x09);
    }

    /**
     * 下发数据
     *
     * TARGET Server_To_Cient
     *
     * @param array $data
     *            [
     *            'number' => 0x0001, // 当前数据分片序号 从 1 开始
     *            'total' => 0x0f01, //数据分配总数
     *            'content' => 'xxxxxxxxxxx', // 发送的分片数据
     *            ];
     * @return \tcp\Byte
     */
    public function setDeliverData($data)
    {
        $this->load = array_merge($this->bigInt2bytes($data['number'], 2), $this->bigInt2bytes($data['total'], 2), $this->bigInt2bytes(strlen($data['content']), 2), $this->str2bytes($data['content']));
        return $this->setCommand(0x0a);
    }

    /**
     * 服务器主动发起盘存请求
     *
     * TARGET Server_To_Client
     *
     * @param string $transaction_number
     *            盘存号
     * @return \tcp\Byte
     */
    public function setNeedAllTags($transaction_number = '')
    {
        $this->load = $this->str2bytes($transaction_number);
        return $this->setCommand(0x0b);
    }

    /**
     * 客户端发送盘存汇总
     *
     * TARGET Client_To_Server
     *
     * @param array $device
     *            [
     *            'transaction_number' => 'P0001001001',
     *            'status' => 1, // 0-关门 1-开门
     *            'count' => 0x0010, // 总数
     *            'weight' => 0x0010f0f0
     *            ];
     * @return \tcp\Byte
     */
    public function setAllTagsCount($device)
    {
        $this->load = array_merge($this->str2bytes($device['transaction_number']), $this->bigInt2bytes($device['status'], 1), $this->bigInt2bytes($device['count'], 2), $this->bigInt2bytes($device['weight'], 4));
        
        return $this->setCommand(0x0c);
    }

    /**
     *
     * 客户端发送标签集合给服务端
     *
     * TARGET Client_To_Server
     *
     * @param string $transaction_number
     *            业务流水号
     * @param array $more
     *            增加的标签
     * @param array $less
     *            减少的标签
     * @param array $current
     *            当前的标签
     * @param number $is_last
     *            是否结束
     * @return \tcp\Byte
     */
    public function setAllTgas($transaction_number = '', $more = [], $less = [], $current = [], $is_last = 1)
    {
        $tagsStr = $currentStr = $lessStr = $moreStr = '';
        empty($more) ? $moreStr = implode(',+', $more) : '';
        empty($less) ? $lessStr = implode(',+', $less) : '';
        empty($current) ? $currentStr = implode(',=', $current) : '';
        
        if (! empty($more) || ! empty($less) || ! empty($$current)) {
            $tagsStr = implode(',', [
                $moreStr,
                $lessStr,
                $currentStr
            ]);
        }
        $this->load = array_merge($this->str2bytes($transaction_number), $this->bigInt2bytes(strlen($tagsStr), 2), $this->str2bytes($tagsStr), $this->bigInt2bytes($is_last, 1));
        
        return $this->setCommand(0x0d);
    }

    /**
     * 盘存指令
     *
     * TARGET Server_To_Client
     *
     * @param string $transaction_number            
     * @return \tcp\Byte
     */
    public function setInventory($transaction_number = '')
    {
        $this->load = $this->str2bytes($transaction_number);
        return $this->setCommand(0x0b);
    }

    /**
     * 盘存汇总
     *
     * TARGET Client_To_Server
     *
     * @param array $transaction
     *            [
     *            'status' => 0, //开关门状态 0 关门 1 开门
     *            'different_count' => 0x00f0,
     *            'weight' => 0x0010f001,
     *            'transaction_number' => 'T123456789'
     *            ];
     * @return \tcp\Byte
     */
    public function setInventorySummary($transaction = [])
    {
        $this->load = array_merge($this->bigInt2bytes($transaction['status'], 1), $this->bigInt2bytes($transaction['different_count'], 2), $this->bigInt2bytes($transaction['weight'], 4), $this->str2bytes($transaction['transaction_number']));
        return $this->setCommand(0x0c);
    }

    /**
     * 盘存标签
     *
     * TARGET Client_To_Server
     *
     * @param array $more
     *            增加的标签
     * @param array $less
     *            减少的标签
     * @param number $is_last
     *            是否结束
     * @return \tcp\Byte
     */
    public function setInventoryTags($more = [], $less = [], $is_last = 1)
    {
        $tagsStr = $lessStr = $moreStr = '';
        ! empty($more) ? $moreStr = '+' . implode(',+', $more) : '';
        ! empty($less) ? $lessStr = '-' . implode(',-', $less) : '';
        
        if (! empty($more) || ! empty($less)) {
            $tagsStr = implode(',', [
                $moreStr,
                $lessStr
            ]);
        }
        $tagsStr = rtrim(ltrim($tagsStr, ','), ',');
        
        $this->load = array_merge($this->bigInt2bytes(strlen($tagsStr), 2), $this->str2bytes($tagsStr), $this->bigInt2bytes($is_last, 1));
        
        return $this->setCommand(0x0d);
    }

    /**
     * 刷新客户端指令
     *
     * TARGET Server_To_Client
     *
     * @param string $transaction_number            
     * @return \tcp\Byte
     */
    public function setRefresh($transaction_number = '')
    {
        $this->load = $this->str2bytes($transaction_number);
        return $this->setCommand(0x0e);
    }

    /**
     * 刷新汇总
     *
     * TARGET Client_To_Server
     *
     * @param array $transaction
     *            [
     *            'status' => 0, //开关门状态 0 关门 1 开门
     *            'different_count' => 0x00f0,
     *            'weight' => 0x0010f001,
     *            'transaction_number' => 'T123456789'
     *            ];
     * @return \tcp\Byte
     */
    public function setRefreshSummary($transaction = [])
    {
        $this->load = array_merge($this->bigInt2bytes($transaction['status'], 1), $this->bigInt2bytes($transaction['different_count'], 2), $this->bigInt2bytes($transaction['weight'], 4), $this->str2bytes($transaction['transaction_number']));
        return $this->setCommand(0x0f);
    }

    /**
     * 刷新变化情况
     *
     * TARGET Client_To_Server
     *
     * @param array $more
     *            增加的标签
     * @param array $less
     *            减少的标签
     * @param number $is_last
     *            是否结束
     * @return \tcp\Byte
     */
    public function setRefreshTags($more = [], $less = [], $is_last = 1)
    {
        $tagsStr = $lessStr = $moreStr = '';
        ! empty($more) ? $moreStr = '+' . implode(',+', $more) : '';
        ! empty($less) ? $lessStr = '-' . implode(',-', $less) : '';
        
        if (! empty($more) || ! empty($less)) {
            $tagsStr = implode(',', [
                $moreStr,
                $lessStr
            ]);
        }
        $tagsStr = rtrim(ltrim($tagsStr, ','), ',');
        
        $this->load = array_merge($this->bigInt2bytes(strlen($tagsStr), 2), $this->str2bytes($tagsStr), $this->bigInt2bytes($is_last, 1));
        
        return $this->setCommand(0x10);
    }

    /**
     * 客户端发给服务端的 登录信息
     *
     * @return array [
     *         'protocol_version' => '00000002',
     *         'hardware_version' => 'h0000001',
     *         'softeare_version' => 's0000001',
     *         'device_number' => '01ad555d15e4cf134f9e6c550b79522c',
     *         'device_key' => '61f2c1daaad64484a58187b49da4fc0e',
     *         'status' => bindec(00000010), // Bit7：0-关门 1-开门 Bit6:0-无重力传感器 1-
     *         'tags_amount' => 0x010a, // 柜子标签总数，高字节在前
     *         'weight' => 0x0000ff00,
     *         'transaction_number' => 'L0000000001'
     *         ];
     */
    public function getLogin()
    {
        $arr = [
            'protocol_version' => $this->bytes2str(array_slice($this->load, 0, 8)),
            'hardware_version' => $this->bytes2str(array_slice($this->load, 8, 8)),
            'softeare_version' => $this->bytes2str(array_slice($this->load, 16, 8)),
            'device_number' => $this->bytes2str(array_slice($this->load, 24, 32)),
            'device_key' => $this->bytes2str(array_slice($this->load, 56, 32)),
            'status' => $this->bigBytes2int(array_slice($this->load, 88, 1)),
            'tags' => $this->bigBytes2int(array_slice($this->load, 89, 2)),
            'weight' => $this->bigBytes2int(array_slice($this->load, 91, 4)),
            'transaction_number' => $this->bytes2str(array_slice($this->load, 95, 10))
        ];
        
        return (array) $arr;
    }

    /**
     *
     * 客户端发给 服务器端的 标签集合
     *
     * @return array [
     *         'tags'=>['11','22'] //标签列表
     *         'is_last'=>1 //是否结束
     *         ];
     *        
     */
    public function getLoginTags()
    {
        $length = $this->bigBytes2int(array_slice($this->load, 0, 2));
        $tags = $this->bytes2str(array_slice($this->load, 2, $length));
        $isLast = $this->bigBytes2int(array_slice($this->load, $length + 2, 1));
        $tagsArr = [];
        if (strlen($tags) > 0) {
            $tagsArr = explode(',', $tags);
        }
        return (array) [
            'tags' => $tagsArr,
            'is_last' => $isLast
        ];
    }

    /**
     * 客户端发给服务器端的心跳包
     *
     * @return NULL
     */
    public function getHeartbeat()
    {
        return null;
    }

    /**
     * 服务端发给客户端的开门指令
     *
     * @return array 交易号
     *         [
     *         'transaction_number'=>'O000111234'
     *         ]
     */
    public function getOpendoor()
    {
        return [
            'transaction_number' => $this->bytes2str(array_slice($this->load, 0, 10))
        ];
    }

    /**
     * 客户端发给服务器端关门消息
     *
     * @return array [
     *         'status' => 0, //开关门状态 0 关门 1 开门
     *         'different_count' => 0x00f0,
     *         'weight' => 0x0010f001,
     *         'transaction_number' => 'T123456789'
     *         ];
     */
    public function getClosedoor()
    {
        return [
            'status' => $this->bigBytes2int(array_slice($this->load, 0, 1)),
            'different_count' => $this->bigBytes2int(array_slice($this->load, 1, 2)),
            'weight' => $this->bigBytes2int(array_slice($this->load, 3, 4)),
            'transaction_number' => $this->bytes2str(array_slice($this->load, 7, 10))
        ];
    }

    /**
     * 客户端发给服务器端的交易标签集合
     *
     * @return array [
     *         'more' => $more, / /增加的标签数组
     *         'less' => $less, //减少的标签数组
     *         'is_last' => $isLast //是否完成
     *         ];
     */
    public function getTransactionTags()
    {
        $more = $less = [];
        
        $length = $this->bigBytes2int(array_slice($this->load, 0, 2));
        $tags = $this->bytes2str(array_slice($this->load, 2, $length));
        $isLast = $this->bigBytes2int(array_slice($this->load, $length + 2, 1));
        
        if (strlen($tags) > 0) {
            $tagsArr = [];
            $tagsArr = explode(',', $tags);
            
            $reduce = $add = [];
            foreach ($tagsArr as $tag) {
                if (preg_match('/^-([a-zA-Z0-9_-]+)/', $tag, $reduce)) {
                    $less[] = $reduce[1];
                } else if (preg_match('/^\+([a-zA-Z0-9_-]+)/', $tag, $add)) {
                    $more[] = $add[1];
                }
            }
        }
        return (array) [
            'more' => $more,
            'less' => $less,
            'is_last' => $isLast
        ];
    }

    /**
     * 设备状态
     *
     * @return array 设备状态
     *         [
     *         'status' => 0 //设备状态
     *         ];
     */
    public function getDeviceStatus()
    {
        return (array) [
            'status' => $this->bigBytes2int(array_slice($this->load, 0, 1))
        ];
    }

    /**
     * 服务器端发给客户端的下发请求
     *
     * @return NULL
     */
    public function getDeliverRequest()
    {
        return null;
    }

    /**
     * 客户端发给服务器端的 确认可以下发指令，
     *
     * @return array 字节大小
     *         [
     *         'size' =>128 //字节大小
     *         ];
     */
    public function getDeliverResponse()
    {
        return (array) [
            'size' => $this->bigBytes2int(array_slice($this->load, 0, 2))
        ];
    }

    /**
     * 客户端发给服务器端的 确认可以下发指令，
     *
     * @param array $data
     *            [
     *            'number' => 0x0001, // 当前数据分片序号 从 1 开始
     *            'total' => 0x0f01, //数据分配总数
     *            'content' => 'xxxxxxxxxxx', // 发送的分片数据
     *            ];
     */
    public function getDeliverData()
    {
        return (array) [
            'number' => $this->bigBytes2int(array_slice($this->load, 0, 2)),
            'total' => $this->bigBytes2int(array_slice($this->load, 2, 2)),
            'content' => $this->bytes2str(array_slice($this->load, 4))
        ];
    }

    /**
     * 服务器端发给客户端的 请求盘存请求
     *
     * @return array 业务流水号
     *         [
     *         'transaction_number' => 'P123456789';
     *         ];
     */
    public function getInventory()
    {
        return (array) [
            'transaction_number' => $this->bytes2str(array_slice($this->load, 0, 10))
        ];
    }

    /**
     * 客户端发送给服务器端的 盘存汇总
     *
     * @return array 盘存汇总
     *         [
     *         'transaction_number' => 'P0001001001',
     *         'status' => 1, // 0-关门 1-开门
     *         'count' => 0x0010, // 总数
     *         'weight' => 0x0010f0f0
     *         ];
     */
    public function getInventorySummary()
    {
        return (array) [
            'status' => $this->bigBytes2int(array_slice($this->load, 0, 1)),
            'count' => $this->bigBytes2int(array_slice($this->load, 1, 2)),
            'weight' => $this->bigBytes2int(array_slice($this->load, 3, 4)),
            'transaction_number' => $this->bytes2str(array_slice($this->load,7,10))];
    }

    /**
     * 客户端发给服务器端的盘存标签集合
     *
     * @return array 盘存结果
     *         [
     *         'more' => $more, //增加的标签
     *         'less' => $less, //减少的标签
     *         'current' => $current, //当前的标签
     *         'is_last' => $isLast //是否结束
     *         ];
     */
    public function getInventoryTags()
    {
        $last = $more = $less = [];
        
        $length = $this->bigBytes2int(array_slice($this->load, 0, 2));
        $tags = $this->bytes2str(array_slice($this->load, 2, $length));
        $isLast = $this->bigBytes2int(array_slice($this->load, $length + 2, 1));
        
        if (strlen($tags) > 0) {
            $tagsArr = [];
            $tagsArr = explode(',', $tags);
            
            $reduce = $add = $now = [];
            foreach ($tagsArr as $tag) {
                if (preg_match('/^-([a-zA-Z0-9_-]+)/', $tag, $reduce)) {
                    $less[] = $reduce[1];
                } else if (preg_match('/^\+([a-zA-Z0-9_-]+)/', $tag, $add)) {
                    $more[] = $add[1];
                }
                if (preg_match('/^=([a-zA-Z0-9_-]+)/', $tag, $now)) {
                    $last[] = $now[1];
                }
            }
        }
        return (array) [
            'more' => $more,
            'less' => $less,
            'last' => $last,
            'is_last' => $isLast
        ];
    }

    /**
     * 服务器端发给客户端的 请求刷新盘存
     *
     * @return array 业务流水号
     *         [
     *         'transaction_number' => 'P123456789';
     *         ];
     */
    public function getRefresh()
    {
        return (array) [
            'transaction_number' => $this->bytes2str(array_slice($this->load, 0, 10))
        ];
    }

    /**
     * 客户端发给服务器 盘存汇总
     *
     * @return array 盘存汇总
     *         [
     *         'transaction_number' => 'P0001001001',
     *         'status' => 1, // 0-关门 1-开门
     *         'count' => 0x0010, // 总数
     *         'weight' => 0x0010f0f0
     *         ];
     */
    public function getRefreshSummary()
    {
        return [
            'status' => $this->bigBytes2int(array_slice($this->load, 0, 1)),
            'different_count' => $this->bigBytes2int(array_slice($this->load, 1, 2)),
            'weight' => $this->bigBytes2int(array_slice($this->load, 3, 4)),
            'transaction_number' => $this->bytes2str(array_slice($this->load, 7, 10))
        ];
    }

    /**
     * 客户端发给服务器端的盘存标签集合
     *
     * @return array 盘存结果
     *         [
     *         'more' => $more, //增加的标签
     *         'less' => $less, //减少的标签
     *         'current' => $current, //当前的标签
     *         'is_last' => $isLast //是否结束
     *         ];
     */
    public function getRefreshTags()
    {
        $more = $less = [];
        
        $length = $this->bigBytes2int(array_slice($this->load, 0, 2));
        $tags = $this->bytes2str(array_slice($this->load, 2, $length));
        $isLast = $this->bigBytes2int(array_slice($this->load, $length + 2, 1));
        
        if (strlen($tags) > 0) {
            $tagsArr = [];
            $tagsArr = explode(',', $tags);
            
            $reduce = $add = [];
            foreach ($tagsArr as $tag) {
                if (preg_match('/^-([a-zA-Z0-9_-]+)/', $tag, $reduce)) {
                    $less[] = $reduce[1];
                } else if (preg_match('/^\+([a-zA-Z0-9_-]+)/', $tag, $add)) {
                    $more[] = $add[1];
                }
            }
        }
        return (array) [
            'more' => $more,
            'less' => $less,
            'is_last' => $isLast
        ];
    }

    /**
     * 处理请求响应
     *
     * @param number $code            
     * @return \tcp\Byte
     */
    public function response($code = 0)
    {
        $this->load = $this->bigInt2bytes($code, 1);
        $this->flags = 0x0001;
        return $this;
    }

    /**
     * 处理响应
     *
     * @param string $data            
     * @return boolean
     */
    public function unpackResponse($data)
    {
        $return = [];
        $length = strlen($data);
        $decArr = unpack('C' . $length, $data);
        
        return ($decArr[9] == 0);
    }

    /**
     * 请求的原始数据
     *
     * @return string
     */
    public function getRequestData()
    {
        return $this->requestData;
    }

    /**
     * 返回的原始数据
     *
     * @return string
     */
    public function getResponseData()
    {
        return $this->responseData;
    }
}
