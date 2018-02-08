<?php
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

    public function setFlags($flags = '00')
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
    public function unpack($decArr)
    {
    #    $return = [];
        $length = count($decArr);
        $return['header'] = $this->header = hexdec(dechex($decArr[1]) . dechex($decArr[2]));
        
        // $return['length'] = $this->length = $this->bigInt2bytes(array_slice($decArr, 2, 2));
        $return['length'] = $this->length = hexdec(dechex($decArr[3]) . dechex($decArr[4]));
        
        $return['command'] = $this->command = $decArr[5];
        
        $return['sn'] = $this->sn = $decArr[6];
        
        $return['flags'] = $this->flags = $decArr[7] . $decArr[8];
        
        $return['checksum'] = $this->checksum = $decArr[$length];
        
        $return['is_checked'] = $this->isChecked;
        $this->load = [];
        $checksum = 0;
        $this->load = [];
        $length =  count($decArr);
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
        ! empty($more) ? $moreStr = '+'.implode(',+', $more) : '';
        ! empty($less) ? $lessStr = '-'. implode(',-', $less) : '';
        
        if (! empty($more) || ! empty($less)) {
            $tagsStr = implode(',', [
                $moreStr,
                $lessStr
            ]);
        }
        $tagsStr =  rtrim(ltrim($tagsStr,','),',');

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
    public function getNeedAllTags()
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
    public function getAllTagsCount()
    {
        return (array) [
            'transaction_number' => $this->bytes2str(array_slice($this->load, 0, 10)),
            'status' => $this->bigBytes2int(array_slice($this->load, 10, 1)),
            'count' => $this->bigBytes2int(array_slice($this->load, 11, 2)),
            'status' => $this->bigBytes2int(array_slice($this->load, 13, 4))
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
    public function getAllTgas()
    {
        $current = $more = $less = [];
        
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
                    $current[] = $now[1];
                }
            }
        }
        return (array) [
            'more' => $more,
            'less' => $less,
            'current' => $current,
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
}
$b=Byte::getInstance();


$loginData ='ff,ff,0,6e,1,4a,0,0,30,30,30,30,30,30,30,32,30,30,30,30,30,30,30,31,30,30,30,30,30,30,30,31,30,31,61,64,35,35,35,64,31,35,65,34,63,66,31,33,34,66,39,65,36,63,35,35,30,62,37,39,35,32,32,63,36,31,66,32,63,31,64,61,61,61,64,36,34,34,38,34,61,35,38,31,38,37,62,34,39,64,61,34,66,63,30,65,0,0,cc,0,0,0,0,0,0,0,0,0,0,0,0,0,0,b3';
$loginTags[]= 'ff,ff,1,c9,7,4b,0,0,1,c1,46,46,46,46,31,30,33,43,2c,46,46,46,46,31,30,33,39,2c,46,46,46,46,31,30,31,44,2c,46,46,46,46,31,30,38,46,2c,46,46,46,46,31,30,39,41,2c,46,46,46,46,31,30,37,44,2c,42,42,42,42,30,30,33,32,2c,46,46,46,46,31,30,33,34,2c,46,46,46,46,31,30,38,33,2c,46,46,46,46,31,30,34,30,2c,42,42,42,42,30,30,32,33,2c,46,46,46,46,31,30,32,32,2c,46,46,46,46,31,30,37,32,2c,46,46,46,46,31,30,42,31,2c,46,46,46,46,31,30,38,35,2c,46,46,46,46,31,30,30,37,2c,42,42,42,42,30,30,32,46,2c,46,46,46,46,30,30,38,43,2c,46,46,46,46,30,30,38,34,2c,46,46,46,46,31,30,31,46,2c,46,46,46,46,31,30,36,31,2c,46,46,46,46,31,30,37,46,2c,46,46,46,46,30,30,37,46,2c,46,46,46,46,31,30,36,34,2c,46,46,46,46,31,30,36,32,2c,46,46,46,46,31,30,38,37,2c,46,46,46,46,30,30,42,32,2c,46,46,46,46,31,30,33,31,2c,42,42,42,42,30,30,32,32,2c,46,46,46,46,31,30,33,44,2c,46,46,46,46,31,30,31,41,2c,46,46,46,46,31,30,32,37,2c,46,46,46,46,31,30,30,36,2c,46,46,46,46,31,30,41,31,2c,46,46,46,46,30,30,42,33,2c,46,46,46,46,31,30,36,30,2c,46,46,46,46,31,30,36,42,2c,46,46,46,46,31,30,35,42,2c,46,46,46,46,31,30,38,39,2c,46,46,46,46,30,30,38,41,2c,46,46,46,46,31,30,38,31,2c,46,46,46,46,31,30,35,36,2c,42,42,42,42,30,30,33,34,2c,46,46,46,46,31,30,30,39,2c,46,46,46,46,31,30,32,34,2c,46,46,46,46,30,30,38,31,2c,46,46,46,46,31,30,32,38,2c,46,46,46,46,31,30,42,33,2c,46,46,46,46,31,30,39,34,2c,46,46,46,46,30,30,38,35,0,2b';
$loginTags[]= 'ff,ff,1,c9,7,4c,0,0,1,c1,46,46,46,46,31,30,38,34,2c,46,46,46,46,30,30,38,39,2c,46,46,46,46,30,30,38,33,2c,46,46,46,46,30,30,38,45,2c,42,42,42,42,30,30,31,45,2c,42,42,42,42,30,30,32,43,2c,46,46,46,46,31,30,33,46,2c,46,46,46,46,31,30,32,30,2c,42,42,42,42,30,30,33,30,2c,46,46,46,46,31,30,41,44,2c,46,46,46,46,31,30,31,36,2c,46,46,46,46,31,30,32,39,2c,46,46,46,46,31,30,37,34,2c,46,46,46,46,31,30,33,41,2c,46,46,46,46,31,30,36,41,2c,46,46,46,46,31,30,41,37,2c,46,46,46,46,31,30,34,31,2c,46,46,46,46,31,30,37,41,2c,46,46,46,46,31,30,37,33,2c,46,46,46,46,31,30,41,33,2c,46,46,46,46,31,30,34,35,2c,46,46,46,46,30,30,38,37,2c,46,46,46,46,31,30,36,44,2c,46,46,46,46,30,30,38,46,2c,46,46,46,46,31,30,38,42,2c,46,46,46,46,31,30,35,43,2c,46,46,46,46,31,30,39,32,2c,46,46,46,46,31,30,36,39,2c,46,46,46,46,31,30,33,37,2c,46,46,46,46,31,30,36,46,2c,46,46,46,46,31,30,39,39,2c,46,46,46,46,30,30,38,32,2c,46,46,46,46,31,30,34,39,2c,46,46,46,46,31,30,32,33,2c,46,46,46,46,31,30,30,31,2c,46,46,46,46,31,30,37,36,2c,46,46,46,46,31,30,32,43,2c,46,46,46,46,31,30,41,42,2c,46,46,46,46,31,30,34,32,2c,46,46,46,46,31,30,35,32,2c,46,46,46,46,31,30,41,41,2c,46,46,46,46,31,30,35,45,2c,46,46,46,46,31,30,32,46,2c,46,46,46,46,31,30,38,32,2c,46,46,46,46,30,30,38,30,2c,46,46,46,46,31,30,36,35,2c,46,46,46,46,31,30,38,41,2c,42,42,42,42,30,30,32,45,2c,46,46,46,46,31,30,39,30,2c,46,46,46,46,31,30,41,46,0,c4';
$loginTags[]='ff,ff,1,c9,7,4d,0,0,1,c1,46,46,46,46,31,30,39,42,2c,46,46,46,46,31,30,33,36,2c,42,42,42,42,30,30,32,44,2c,46,46,46,46,31,30,34,36,2c,46,46,46,46,31,30,38,44,2c,46,46,46,46,31,30,30,32,2c,46,46,46,46,31,30,37,42,2c,46,46,46,46,31,30,35,37,2c,46,46,46,46,31,30,31,45,2c,46,46,46,46,30,30,41,44,2c,42,42,42,42,30,30,31,44,2c,46,46,46,46,31,30,34,38,2c,46,46,46,46,30,30,41,37,2c,46,46,46,46,31,30,41,38,2c,46,46,46,46,31,30,31,43,2c,46,46,46,46,31,30,31,38,2c,46,46,46,46,31,30,32,35,2c,46,46,46,46,31,30,37,38,2c,46,46,46,46,31,30,36,36,2c,46,46,46,46,30,30,41,39,2c,46,46,46,46,31,30,41,36,2c,46,46,46,46,31,30,31,37,2c,46,46,46,46,30,30,41,32,2c,42,42,42,42,30,30,31,43,2c,46,46,46,46,31,30,39,31,2c,46,46,46,46,31,30,31,39,2c,42,42,42,42,30,30,32,30,2c,46,46,46,46,31,30,41,39,2c,46,46,46,46,31,30,36,43,2c,46,46,46,46,31,30,30,35,2c,46,46,46,46,30,30,39,45,2c,46,46,46,46,31,30,37,30,2c,46,46,46,46,31,30,41,43,2c,46,46,46,46,31,30,34,41,2c,46,46,46,46,31,30,32,36,2c,46,46,46,46,31,30,33,42,2c,46,46,46,46,30,30,38,36,2c,46,46,46,46,31,30,31,32,2c,46,46,46,46,31,30,31,31,2c,46,46,46,46,31,30,38,36,2c,46,46,46,46,31,30,35,44,2c,46,46,46,46,31,30,36,38,2c,46,46,46,46,31,30,31,34,2c,46,46,46,46,31,30,33,38,2c,46,46,46,46,30,30,42,37,2c,46,46,46,46,31,30,34,43,2c,46,46,46,46,31,30,35,31,2c,46,46,46,46,31,30,41,32,2c,46,46,46,46,31,30,31,33,2c,42,42,42,42,30,30,33,31,0,77';
$loginTags[]= 'ff,ff,1,c9,7,4e,0,0,1,c1,46,46,46,46,31,30,36,45,2c,46,46,46,46,31,30,37,45,2c,46,46,46,46,30,30,41,42,2c,46,46,46,46,31,30,34,42,2c,46,46,46,46,30,30,41,35,2c,46,46,46,46,31,30,38,38,2c,46,46,46,46,31,30,37,31,2c,46,46,46,46,31,30,35,30,2c,46,46,46,46,31,30,34,46,2c,46,46,46,46,31,30,41,45,2c,46,46,46,46,31,30,41,34,2c,46,46,46,46,31,30,35,41,2c,46,46,46,46,31,30,32,45,2c,46,46,46,46,30,30,41,30,2c,46,46,46,46,31,30,42,32,2c,46,46,46,46,31,30,42,30,2c,46,46,46,46,31,30,37,35,2c,46,46,46,46,31,30,34,44,2c,46,46,46,46,31,30,36,37,2c,46,46,46,46,31,30,30,38,2c,46,46,46,46,31,30,37,39,2c,46,46,46,46,31,30,39,36,2c,46,46,46,46,31,30,38,45,2c,46,46,46,46,31,30,33,32,2c,46,46,46,46,31,30,34,37,2c,46,46,46,46,31,30,42,34,2c,46,46,46,46,31,30,31,30,2c,46,46,46,46,31,30,31,35,2c,46,46,46,46,31,30,31,42,2c,46,46,46,46,31,30,35,46,2c,46,46,46,46,31,30,33,35,2c,46,46,46,46,31,30,39,35,2c,46,46,46,46,31,30,39,33,2c,46,46,46,46,31,30,35,39,2c,46,46,46,46,31,30,41,30,2c,46,46,46,46,31,30,39,46,2c,46,46,46,46,30,30,42,38,2c,46,46,46,46,31,30,39,44,2c,46,46,46,46,31,30,36,33,2c,46,46,46,46,31,30,30,42,2c,46,46,46,46,31,30,32,42,2c,46,46,46,46,31,30,39,45,2c,46,46,46,46,31,30,35,38,2c,46,46,46,46,31,30,37,43,2c,46,46,46,46,31,30,30,45,2c,46,46,46,46,31,30,39,37,2c,46,46,46,46,31,30,32,44,2c,46,46,46,46,31,30,38,43,2c,46,46,46,46,31,30,33,30,2c,46,46,46,46,31,30,37,37,0,49';
$loginTags[]='ff,ff,0,2b,7,4f,0,0,0,23,46,46,46,46,31,30,38,30,2c,46,46,46,46,31,30,32,41,2c,46,46,46,46,31,30,39,38,2c,46,46,46,46,31,30,41,35,1,cf';
$loginData='ff,ff,0,6e,1,0,0,0,30,30,30,30,30,30,30,32,30,30,30,30,30,30,30,31,30,30,30,30,30,30,30,31,30,31,61,64,35,35,35,64,31,35,65,34,63,66,31,33,34,66,39,65,36,63,35,35,30,62,37,39,35,32,32,63,36,31,66,32,63,31,64,61,61,61,64,36,34,34,38,34,61,35,38,31,38,37,62,34,39,64,61,34,66,63,30,65,0,0,9a,0,0,0,0,0,0,0,0,0,0,0,0,0,0,37';
$loginData = 'ff,ff,0,6e,1,4,0,0,30,30,30,30,30,30,30,32,30,30,30,30,30,30,30,31,30,30,30,30,30,30,30,31,63,64,36,38,38,34,36,39,62,34,32,34,34,36,65,39,39,61,36,31,33,30,63,39,35,35,30,63,39,65,65,37,36,31,66,32,63,31,64,61,61,61,64,36,34,34,38,34,61,35,38,31,38,37,62,34,39,64,61,34,66,63,30,65,0,0,c,0,0,0,0,0,0,0,0,0,0,0,0,0,0,6a';
$loginData='ff,ff,0,6e,1,13,0,0,30,30,30,30,30,30,30,32,30,30,30,30,30,30,30,31,30,30,30,30,30,30,30,31,63,64,36,38,38,34,36,39,62,34,32,34,34,36,65,39,39,61,36,31,33,30,63,39,35,35,30,63,39,65,65,37,36,31,66,32,63,31,64,61,61,61,64,36,34,34,38,34,61,35,38,31,38,37,62,34,39,64,61,34,66,63,30,65,0,0,14,0,0,0,0,0,0,0,0,0,0,0,0,0,0,81';
$loginData='ff,ff,0,6e,1,8b,0,0,30,30,30,30,30,30,30,32,30,30,30,30,30,30,30,31,30,30,30,30,30,30,30,31,30,32,61,64,35,35,35,64,31,35,65,34,63,66,31,33,34,66,39,65,36,63,35,35,30,62,37,39,35,32,32,63,36,31,66,32,63,31,64,61,61,61,64,36,34,34,38,34,61,35,38,31,38,37,62,34,39,64,61,34,66,63,30,65,0,0,3b,0,0,0,0,0,0,0,0,0,0,0,0,0,0,64';
$loginData = 'ff,ff,0,6e,1,78,0,0,30,30,30,30,30,30,30,32,30,30,30,30,30,30,30,31,30,30,30,30,30,30,30,31,30,32,61,64,35,35,35,64,31,35,65,34,63,66,31,33,34,66,39,65,36,63,35,35,30,62,37,39,35,32,32,63,36,31,66,32,63,31,64,61,61,61,64,36,34,34,38,34,61,35,38,31,38,37,62,34,39,64,61,34,66,63,30,65,0,0,55,0,0,0,0,0,0,0,0,0,0,0,0,0,0,6b';
$a = explode(',',$loginData);
$i=1;
foreach($a as $k=>$v){
$c[$i++]=hexdec($v);
}

$rst =  $b->unpack($c)->getLogin();
print_r($b->headers);
#print_r(count($c));
#echo "\n";
print_r($rst);
exit;
$tags =[];
$loginTags=[];
$loginTags[] = 'ff,ff,0,73,7,5,0,0,0,6b,46,46,46,46,31,30,33,37,2c,46,46,46,46,31,30,32,46,2c,46,46,46,46,31,30,33,30,2c,46,46,46,46,31,30,32,45,2c,46,46,46,46,31,30,32,41,2c,46,46,46,46,31,30,33,35,2c,46,46,46,46,31,30,33,36,2c,46,46,46,46,31,30,33,38,2c,46,46,46,46,31,30,32,44,2c,46,46,46,46,31,30,33,32,2c,46,46,46,46,31,30,32,43,2c,46,46,46,46,31,30,33,31,1,9a';
$loginTags=[];
$loginTags[]='ff,ff,0,bb,7,6,0,0,0,b3,42,42,42,42,30,39,37,39,2c,42,42,42,42,31,30,38,34,2c,42,42,42,42,31,32,38,32,2c,42,42,42,42,31,32,33,33,2c,42,42,42,42,31,31,38,33,2c,42,42,42,42,31,32,34,30,2c,42,42,42,42,30,39,35,32,2c,42,42,42,42,31,32,35,36,2c,42,42,42,42,31,32,36,35,2c,42,42,42,42,31,32,38,35,2c,42,42,42,42,31,35,30,35,2c,42,42,42,42,31,34,36,37,2c,42,42,42,42,30,39,34,39,2c,42,42,42,42,31,31,31,39,2c,42,42,42,42,31,30,38,36,2c,42,42,42,42,31,32,39,34,2c,42,42,42,42,31,32,34,37,2c,42,42,42,42,31,32,34,33,2c,42,42,42,42,31,33,33,32,2c,42,42,42,42,31,33,32,39,1,7a';
$loginTags=[];
$loginTags[]="ff,ff,0,bb,7,3b,0,0,0,b3,42,42,42,42,31,31,38,33,2c,42,42,42,42,31,30,38,34,2c,42,42,42,42,31,34,36,37,2c,42,42,42,42,31,32,38,32,2c,42,42,42,42,31,31,31,39,2c,42,42,42,42,31,35,30,35,2c,42,42,42,42,30,39,34,39,2c,42,42,42,42,31,32,35,36,2c,42,42,42,42,31,32,36,35,2c,42,42,42,42,31,32,34,30,2c,42,42,42,42,30,39,37,39,2c,42,42,42,42,30,39,35,32,2c,42,42,42,42,31,32,33,33,2c,42,42,42,42,31,32,38,35,2c,42,42,42,42,31,30,38,36,2c,42,42,42,42,31,32,34,33,2c,42,42,42,42,31,32,39,34,2c,42,42,42,42,31,32,34,37,2c,42,42,42,42,31,33,33,32,2c,42,42,42,42,31,33,32,39,1,af";
$loginTags=[];
$loginTags[]="ff,ff,0,5,2,5f,0,0,66,ff,ff,0,5,2,5f,0,0,66";
$loginTags[]="ff,ff,0,5,2,a7,0,0,ae,ff,ff,0,5,2,a7,0,0,ae";
foreach($loginTags  as $lg){
    $aa = explode(',',$lg);
    $ii=1;
    foreach($aa as $k=>$v){
         $cc[$ii++]=hexdec($v);
    }
    $ii=1;
    $rstt =  $b->unpack($cc)->getheartbeat();
print_r($b->headers);
print_r(count($cc));
echo "\n";
print_r($rstt);
    $tags = array_merge($tags,$rstt['tags']);
}
//print_r($tags);
