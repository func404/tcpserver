<?php
namespace tcp;

class Work
{

    /**
     * 登录
     * 0x01
     * I1
     */
    public function login($server, $fd, $from_id, $data, $headers, $client)
    {
        $bytes = Byte::getInstance();
        $loginInfo = $bytes->unpack($data)->getLogin();
        // 记录请求日志
        Logger::getInstance()->write($client['remote_ip'] . '|' . $headers['command'] . '|' . $bytes->getRequestData(), 'monitor');
        $validate = true;
        foreach ($loginInfo as $v) {
            if (empty($v) && $v != 0) {
                $validate = false;
                break;
            }
        }
        if (! $validate) {
            $responseData = Byte::getInstance()->setSn($headers['sn'] ++)
                ->response(Error::invalidParameter)
                ->pack();
            
            // RESPONSE invalidParameter
            $responseRst = $server->send($fd, $responseData);
            $closeRst = $server->close($fd);
            Cache::getInstance()->publish(Config::broadcastChannels['device'], 'InvalidParameter:' . json_encode($loginInfo));
            return false;
        }
        $device = DB::getInstance()->getDeviceByNo($loginInfo['device_number']);
        
        if (empty($device)) {
            $responseData = Byte::getInstance()->setSn($headers['sn'] ++)
                ->response(Error::deviceUnlogined)
                ->pack();
            Logger::getInstance()->write('DeviceUnlogined:' . Error::deviceUnlogined, 'error');
            Logger::getInstance()->write('RequestFrom:' . $client['remote_ip'], 'error');
            Cache::getInstance()->publish(Config::broadcastChannels['device'], 'DeviceUnlogined:' . json_encode($loginInfo));
            
            // RESPONSE deviceUnlogined
            $responseRst = $server->send($fd, $responseData);
            $closeRst = $server->close($fd);
            return false;
        }
        
        if ($loginInfo['device_key'] != $device['device_key']) {
            $responseData = Byte::getInstance()->setSn($headers['sn'] ++)
                ->setCommand(0x01)
                ->response(Error::deviceKeyError)
                ->pack();
            Logger::getInstance()->write('DeviceKeyError:' . Error::deviceKeyError, 'error');
            Logger::getInstance()->write('RequestFrom:' . $client['remote_ip'] . ':' . $client['remote_port'], 'error');
            Cache::getInstance()->publish(Config::broadcastChannels['device'], 'DeviceKeyError:' . json_encode($loginInfo));
            
            // RESPONSE deviceKeyError
            $responseRst = $server->send($fd, $responseData);
            $closeRst = $server->close($fd);
            return false;
        }
        
        // 保存登录日志
        $loginId = DB::getInstance()->createLoginLog([
            'device_id' => $device['device_id'],
            'device_number' => $device['device_number'],
            'door_status' => $loginInfo['status'] & 1,
            'tags' => $loginInfo['tags'],
            'weight' => $loginInfo['weight'],
            'connect_time' => date("Y-m-d H:i:s", $client['connect_time']),
            'login_ip' => $client['remote_ip'],
            'login_time' => date("Y-m-d H:i:s")
        ]);
        
        /*
         * 缓存客户端信息
         */
        $clientCurrent = json_encode([
            'login_id' => $loginId,
            'fd' => $fd,
            'connect_time' => $client['connect_time'],
            'login_time' => $client['last_time'],
            'last_time' => $client['last_time'],
            'tags' => $loginInfo['tags'],
            'tags_uploaded' => ($loginInfo['tags'] == 0),
            'weight' => $loginInfo['weight'],
            'current_transaction' => 'waiting',
            'current_data' => '',
            'status' => $loginInfo['status'],
            'sn' => ++ $headers['sn']
        ]);
        
        if ($loginInfo['status'] % 0000011) {
            $clientCurrent['current_data'] = 'shopping';
            $clientCurrent['current_transaction'] = $data['transaction_number'];
        }
        
        Cache::getInstance()->hSet(Config::caches['clients'], $device['device_id'], $clientCurrent);
        
        /*
         * 更新连接数
         */
        $connection = Cache::getInstance()->hGet(Config::caches['connections'], $fd);
        if (empty($connection)) {
            $responseData = Byte::getInstance()->setSn($headers['sn'] ++)
                ->setCommand(0x01)
                ->response(Error::serverError)
                ->pack();
            Logger::getInstance()->write('serverError:' . Error::serverError . '  ' . __FILE__ . ' : ' . __LINE__, 'fatal');
            Cache::getInstance()->publish(Config::broadcastChannels['device'], 'serverError:' . json_encode($loginInfo));
            
            // RESPONSE serverError
            $responseRst = $server->send($fd, $responseData);
            $closeRst = $server->close($fd);
            return false;
        }
        Cache::getInstance()->hSet(Config::caches['connections'], $fd, json_encode(array_merge(json_decode($connection, true), [
            'login_id' => $loginId,
            'device_id' => $device['device_id']
        ])));
        
        Cache::getInstance()->publish(Config::broadcastChannels['device'], $clientCurrent);
        
        // 此处加入定时器
        swoole_timer_tick(Config::checkInterval, [
            Timer::class,
            'checkConnections'
        ], $server);
        
        // 响应客户端
        $isSend = $server->send($fd, Byte::getInstance()->setSn($headers['sn'])
            ->setCommand(0x01)
            ->response(0)
            ->pack());
        Logger::getInstance()->write($client['remote_ip'] . '|' . $headers['command'] . '|' . $bytes->getResponseData() . '|' . intval($isSend), 'response');
        return $isSend;
    }

    /**
     * 心跳
     * 0x02
     * I3
     */
    public function heartbeat($server, $fd, $from_id, $data, $headers, $client)
    {
        $c = json_decode(Cache::getInstance()->hGet(Config::caches['connections'], $fd));
        if ($c->device_id) {
            $device = Cache::getInstance()->hGet(Config::caches['clients'], $c->device_id);
            $device = json_decode($device, true);
            Cache::getInstance()->hSet(Config::caches['clients'], $c->device_id, json_encode(array_merge($device, [
                'last_time' => $client['last_time'],
                'sn' => $headers['sn'] ++
            ])));
            
            $bytes = Byte::getInstance();
            $responseData = $bytes->setSn($headers['sn'])
                ->setCommand(0x02)
                ->response()
                ->pack();
            
            // RESPONSE HEATBEAT
            $responseRst = $server->send($fd, $responseData);
            
            Logger::getInstance()->write($client['remote_ip'] . '|' . $headers['command'] . '|' . $bytes->getResponseData() . '|' . intval($responseRst), 'response');
            Logger::getInstance()->write(implode('|', $client), 'heartbeat');
            Cache::getInstance()->publish(Config::broadcastChannels['device'], 'HeatBeat:' . json_encode($client));
        } else {
            
            $responseData = $bytes->setSn($headers['sn'])
                ->setCommand(0x02)
                ->response()
                ->pack();
            
            // RESPONSE HEATBEAT
            $responseRst = $server->send($fd, $responseData);
            $closeRst = $server->close($fd);
            
            Logger::getInstance()->write('HeartBeatTimeout:' . Error::heartBeatTimeout, 'error');
            Logger::getInstance()->write('RequestFrom:' . $client['remote_ip'], 'error');
            Cache::getInstance()->publish(Config::broadcastChannels['device'], 'HeartBeatTimeout:' . json_encode($client));
        }
    }

    /**
     * 开门
     *
     * @param Sock $server            
     * @param \stdClass $device            
     * @param string $transactionsId            
     * @return boolean
     */
    public function openDoor($server, $device, $transactionsId)
    {
        $connection = Cache::getInstance()->hGet(Config::caches['connections'], $device->fd);
        $c = json_decode($connection);
        if (! $c->device_id) {
            return false;
        }
        $data = Byte::getInstance()->setSn($device->sn ++)
            ->setOpendoor($transactionsId)
            ->pack();
        $rst = $server->send($device->fd, $data);
        return true;
        // need log;
    }

    /**
     * 关门
     * 0x04
     * I5
     *
     * @param unknown $data            
     */
    public function closeDoor($server, $fd, $from_id, $data, $headers, $client)
    {
        $bytes = Byte::getInstance()->unpack($data);
        $closeInfo = $bytes->getClosedoor();
        // 记录请求日志
        Logger::getInstance()->write($client['remote_ip'] . '|' . $headers['command'] . '|' . $bytes->getRequestData(), 'monitor');
        
        $connection = Cache::getInstance()->hGet(Config::caches['connections'], $fd);
        $c = json_decode($connection);
        if (! $c->device_id) {
            return $server->send($fd, Byte::getInstance()->setSn($headers['sn'] ++)
                ->setCommand(0x04)
                ->response(Error::hasNotBeReady)
                ->pack());
        }
        Logger::getInstance()->write('HasNotBeReady:' . Error::hasNotBeReady, 'error');
        Logger::getInstance()->write('RequestFrom:' . $client['remote_ip'], 'error');
        $device = Cache::getInstance()->hGet(Config::caches['clients'], $c->device_id);
        $device = json_decode($device);
        $transactionNumber = $device->current_data;
        
        // 当前设备交易号 和 系统缓存的订单号不一致 取消判断
        // if ($transactionNumber != $closeInfo['transaction_number']) {
        // return $server->send($fd, Byte::getInstance()->setSn($headers['sn'] ++)
        // ->setCommand(0x04)
        // ->response(Error::invalidParameter)
        // ->pack());
        // }
        if ($closeInfo['different_count'] == 0) {
            $order = DB::getInstance()->update('wl_device_door_logs', [
                'status' => 1,
                'close_time' => date("Y-m-d H:i:s")
            ], [
                'id' => $transactionNumber
            ]);
            
            Cache::getInstance()->publish(Config::broadcastChannels['server'], json_encode([
                'command' => 'CLD',
                'data' => [
                    'transaction_number' => $transactionNumber
                ]
            ]));
            // 更新client
            $device->current_transaction = 'waiting';
            $device->current_data = '';
            $device->last_time = time();
            $device->status = 0;
            $device->sn ++;
            Cache::getInstance()->hSet(Config::caches['clients'], $c->device_id, json_encode($device));
        } else {
            // 缓存标签变化数量
            Cache::getInstance()->hSet(Config::caches['tags_count'], $c->device_id . '_' . $transactionNumber, $closeInfo['different_count']);
        }
        
        // 响应客户端
        $isSend = $server->send($fd, Byte::getInstance()->setSn($headers['sn'] ++)
            ->setCommand(0x04)
            ->response()
            ->pack());
        Logger::getInstance()->write($client['remote_ip'] . '|' . $headers['command'] . '|' . $bytes->getResponseData() . '|' . intval($isSend), 'response');
        return $isSend;
        // Api::getInstance()->pay($closeInfo['close_id']);
    }

    /**
     * 关门上传标签
     * 0x05
     * I6
     *
     * @param unknown $data            
     */
    public function closeTags($server, $fd, $from_id, $data, $headers, $client)
    {
        $connection = Cache::getInstance()->hGet(Config::caches['connections'], $fd);
        $c = json_decode($connection);
        if (! $c->device_id) {
            return $server->send($fd, Byte::getInstance()->setSn($headers['sn'] ++)
                ->setCommand(0x05)
                ->response(0xe7)
                ->pack());
        }
        $device = Cache::getInstance()->hGet(Config::caches['clients'], $c->device_id);
        $device = json_decode($device);
        $transactionNumber = $device->current_data;
        
        $moreValues = $lessValues = [];
        $bytes = Byte::getInstance()->unpack($data);
        $closeInfo = $bytes->getTransactionTags();
        // 记录请求日志
        Logger::getInstance()->write($client['remote_ip'] . '|' . $headers['command'] . '|' . $bytes->getRequestData(), 'monitor');
        
        if (! empty($closeInfo['more'])) {
            foreach ($closeInfo['more'] as $moreTag) {
                $moreValues[] = "('" . $moreTag . "', " . Config::action['shopping'] . "," . Config::direct['more'] . "," . $transactionNumber . ")";
            }
        }
        
        if (! empty($closeInfo['less'])) {
            foreach ($closeInfo['less'] as $lessTag) {
                $lessValues[] = "('" . $lessTag . "', " . Config::action['shopping'] . "," . Config::direct['less'] . "," . $transactionNumber . ")";
            }
        }
        
        $inserts = "Insert into `wl_device_tag_logs`(`tag`,`type`,`direct`,`device_door_log_id`) values";
        
        $allValues = array_merge($moreValues, $lessValues);
        
        if (! empty($allValues)) {
            $inserts .= implode(',', $allValues);
            DB::getInstance()->query($inserts);
            DB::getInstance()->getInsertId();
        }
        
        if ($closeInfo['is_last'] == 1) {
            
            $order = DB::getInstance()->update('wl_device_door_logs', [
                'status' => 1,
                'close_time' => date("Y-m-d H:i:s")
            ], [
                'id' => $transactionNumber
            ]);
            
            $diff = DB::getInstance()->fetchOne("select count(1) as 'diff' from wl_device_tag_logs where `device_door_log_id` = '" . $transactionNumber . "'");
            
            Cache::getInstance()->publish(Config::broadcastChannels['server'], json_encode([
                'command' => 'CLD',
                'data' => [
                    'transaction_number' => $transactionNumber
                ]
            ]));
            if ($diff != Cache::getInstance()->hGet(Config::caches['tags_count'], $c->device_id . '_' . $transactionNumber)) {
                Cache::getInstance()->hDel(Config::caches['tags_count'], $c->device_id . '_' . $transactionNumber);
                Logger::getInstance()->write('TagsNumberUnmatch:' . Error::tagsNumberUnmatch . '[' . $transactionNumber . ']', 'error');
                Logger::getInstance()->write('RequestFrom:' . $client['remote_ip'], 'error');
                return $server->send($fd, Byte::getInstance()->setSn($headers['sn'] ++)
                    ->setCommand(0x05)
                    ->response(Error::tagsNumberUnmatch)
                    ->pack());
            }
            Cache::getInstance()->hDel(Config::caches['tags_count'], $c->device_id . '_' . $transactionNumber);
            
            // 更新client
            $device->current_transaction = 'waiting';
            $device->current_data = '';
            $device->last_time = time();
            $device->status = 0;
            $device->sn ++;
            Cache::getInstance()->hSet(Config::caches['clients'], $c->device_id, json_encode($device));
        }
        // 响应客户端
        $isSend = $server->send($fd, Byte::getInstance()->setSn($headers['sn'] ++)
            ->setCommand(0x05)
            ->response()
            ->pack());
        Logger::getInstance()->write($client['remote_ip'] . '|' . $headers['command'] . '|' . $bytes->getResponseData() . '|' . intval($isSend), 'response');
    }

    /**
     * 设备状态
     * 0x06
     * I7
     */
    public function deviceStatus($server, $fd, $from_id, $data, $headers, $client)
    {
        $bytes = Byte::getInstance()->unpack($data);
        $status = $bytes->getDeviceStatus();
        // 记录请求日志
        Logger::getInstance()->write($client['remote_ip'] . '|' . $headers['command'] . '|' . $bytes->getRequestData(), 'monitor');
        
        $connection = Cache::getInstance()->hGet(Config::caches['connections'], $fd);
        $c = json_decode($connection);
        if (! $c->device_id) {
            return $server->send($fd, Byte::getInstance()->setSn($headers['sn'] ++)
                ->setCommand(0x06)
                ->response(Error::deviceUnlogined)
                ->pack());
        }
        $device = Cache::getInstance()->hGet(Config::caches['clients'], $c->device_id);
        $device = json_decode($device);
        $device->status = $status;
        $device->sn ++;
        Cache::getInstance()->hSet(Config::caches['clients'], $c->device_id, json_encode($device));
        $isSend = $server->send($fd, Byte::getInstance()->setSn($headers['sn'])
            ->setCommand(0x06)
            ->response(0)
            ->pack());
        Logger::getInstance()->write($client['remote_ip'] . '|' . $headers['command'] . '|' . $bytes->getResponseData() . '|' . intval($isSend), 'response');
    }

    /**
     * 服务器下发数据通知
     * 0x08
     * I8
     */
    public function deliverNotice()
    {
        ;
    }

    /**
     * 接收下发
     * 0x08
     * I8
     */
    public function deliverAnswer()
    {
        ;
    }

    /**
     * 服务器下发数据
     * 0x0a
     * I8
     */
    public function deliverData()
    {
        ;
    }

    /**
     * 开机上传所有标签
     * i2
     * 0x07
     *
     * @param unknown $data            
     */
    public function loginTags($server, $fd, $from_id, $data, $headers, $client)
    {
        $bytes = Byte::getInstance();
        $tagsData = $bytes->unpack($data)->getLoginTags();
        // 记录请求日志
        Logger::getInstance()->write($client['remote_ip'] . '|' . $headers['command'] . '|' . $bytes->getRequestData(), 'monitor');
        $connection = Cache::getInstance()->hGet(Config::caches['connections'], $fd);
        $c = json_decode($connection);
        if (! $c->device_id) {
            Logger::getInstance()->write('DeviceUnlogined:' . Error::deviceUnlogined, 'error');
            Logger::getInstance()->write('RequestFrom:' . $client['remote_ip'], 'error');
            return $server->send($fd, Byte::getInstance()->setSn($headers['sn'])
                ->setCommand(0x07)
                ->response(Error::deviceUnlogined)
                ->pack());
        }
        $device = Cache::getInstance()->hGet(Config::caches['clients'], $c->device_id);
        $device = json_decode($device, true);
        if ($tagsData['is_last'] && ! $device['tags_uploaded']) {
            $inserts = "insert into  `wl_device_tags` (`device_id`,`device_login_id`,`tag`,`created_time`) values ";
            $dt = date("Y-m-d H:i:s");
            $tags = Cache::getInstance()->hGet(Config::caches['tags'], $c->device_id . '_' . $device['login_id']);
            if ($tags) {
                $tags = array_unique(array_merge(json_decode($tags, true), $tagsData['tags']));
            } else {
                $tags = array_unique($tagsData['tags']);
            }
            Cache::getInstance()->hDel(Config::caches['tags'], $c->device_id . '_' . $device['login_id']);
            $totalTags = 0;
            if (! empty($tags)) {
                foreach ($tags as $tag) {
                    $totalTags ++;
                    $tagValues[] = "('" . $c->device_id . "', " . $device['login_id'] . ",'" . $tag . "','" . $dt . "')";
                }
            }
            $inserts .= implode(',', $tagValues);
            DB::getInstance()->query($inserts);
            Cache::getInstance()->hSet(Config::caches['clients'], $c->device_id, json_encode(array_merge($device, [
                'last_time' => $client['last_time'],
                'sn' => $headers['sn'] ++,
                'tags_uploaded' => true
            ])));
            
            if ($device['tags'] != $totalTags) {
                $data = Byte::getInstance()->setSn($headers['sn'] ++)
                    ->response(Error::sendLoginTagsTimeout)
                    ->pack();
                $server->send($fd, $data);
                return false;
            }
        } else {
            $tags = Cache::getInstance()->hGet(Config::caches['tags'], $c->device_id . '_' . $device['login_id']);
            if ($tags) {
                $tags = array_unique(array_merge(json_decode($tags, true), $tagsData['tags']));
            } else {
                $tags = array_unique($tagsData['tags']);
            }
            Cache::getInstance()->hSet(Config::caches['tags'], $c->device_id . '_' . $device['login_id'], json_encode($tags));
        }
        Cache::getInstance()->publish(Config::broadcastChannels['server'], json_encode([
            'command' => 'LOG',
            'data' => [
                'transaction_number' => $device['login_id']
            ]
        ]));
        $data = Byte::getInstance()->setSn($headers['sn'] ++)
            ->response(0)
            ->pack();
        $isSend = $server->send($fd, $data);
        Logger::getInstance()->write($client['remote_ip'] . '|' . $headers['command'] . '|' . $bytes->getResponseData() . '|' . intval($isSend), 'response');
    }

    /**
     * 发送盘存命令
     * 0x0b
     */
    public function inventory($server, $device, $transactionsId)
    {
        $connection = Cache::getInstance()->hGet(Config::caches['connections'], $device->fd);
        $c = json_decode($connection);
        if (! $c->device_id) {
            return false;
        }
        $data = Byte::getInstance()->setSn($device->sn ++)
            ->setInventory($transactionsId)
            ->pack();
        $rst = $server->send($device->fd, $data);
        return $rst;
        // need log;
    }

    /**
     * 接收盘存汇总
     * 0x0c
     */
    public function inventorySummary($server, $fd, $from_id, $data, $headers, $client)
    {
        $bytes = Byte::getInstance()->unpack($data);
        $inventoryInfo = $bytes->getInventorySummary();
        $connection = Cache::getInstance()->hGet(Config::caches['connections'], $fd);
        $connection = json_decode($connection);
        
        if (! $connection->device_id) {
            $responseData = Byte::getInstance()->setSn($headers['sn'] ++)
                ->setCommand(0x0c)
                ->response(Error::hasNotBeReady)
                ->pack();
            $responseRst = $server->send($fd, $responseData);
            return $responseRst;
        }
        
        $device = Cache::getInstance()->hGet(Config::caches['clients'], $connection->device_id);
        $device = json_decode($device);
        $transactionNumber = $device->current_data;
        
        DB::getInstance()->update('wl_device_inventory_logs', [
            'finish_time' => date("Y-m-d H:i:s"),
            'tags' => $inventoryInfo['count'],
            'weight' => $inventoryInfo['weight']
        ], [
            'inventory_id' => $transactionNumber
        ]);
        
        if ($inventoryInfo['count'] == 0) {
            // 发送结束标记
            Cache::getInstance()->publish(Config::broadcastChannels['server'], json_encode([
                'command' => 'Inventory',
                'data' => [
                    'transaction_number' => $transactionNumber
                ]
            ]));
            // 更新client
            $device->current_transaction = 'waiting';
            $device->current_data = '';
            $device->last_time = time();
            $device->status = 0;
            $device->sn ++;
            Cache::getInstance()->hSet(Config::caches['clients'], $connection->device_id, json_encode($device));
        } else {
            $device->last_time = time();
            Cache::getInstance()->hSet(Config::caches['clients'], $connection->device_id, json_encode($device));
            // 缓存标签变化数量
            Cache::getInstance()->hSet(Config::caches['inventory_count'], $connection->device_id . '_' . $transactionNumber, $inventoryInfo['count']);
        }
        
        // 响应客户端
        $responseData = $server->send($fd, Byte::getInstance()->setSn($headers['sn'] ++)
            ->setCommand(0x0c)
            ->response()
            ->pack());
        $responseRst = $server->send($fd, $responseData);
        return $responseRst;
    }

    /**
     * 接收盘存标签
     * 0x0d
     */
    public function inventoryTags($server, $fd, $from_id, $data, $headers, $client)
    {
        $bytes = Byte::getInstance()->unpack($data);
        $connection = Cache::getInstance()->hGet(Config::caches['connections'], $fd);
        $connection = json_decode($connection);
        
        if (! $connection->device_id) {
            $responseData = Byte::getInstance()->setSn($headers['sn'] ++)
                ->setCommand(0x0d)
                ->response(Error::hasNotBeReady)
                ->pack();
            $responseRst = $server->send($fd, $responseData);
            return $responseRst;
        }
        
        $inventoryTags = $bytes->getInventoryTags();
        
        $device = Cache::getInstance()->hGet(Config::caches['clients'], $connection->device_id);
        $device = json_decode($device);
        $transactionNumber = $device->current_data;
        
        $total = Cache::getInstance()->hSet(Config::caches['inventory_count'], $connection->device_id . '_' . $transactionNumber);
        
        if ($inventoryTags['is_last'] != 0) {
            $tags = Cache::getInstance()->hGet(Config::caches['inventory_tags'], $connection->device_id . '_' . $transactionNumber);
            if ($tags) {
                $tags = json_decode($tags, true);
            }
            $newTags = [
                'more' => array_merge($inventoryTags['more'], $tags['more']),
                'less' => array_merge($inventoryTags['less'], $tags['less']),
                'last' => array_merge($inventoryTags['last'], $tags['last'])
            ];
            Cache::getInstance()->hSet(Config::caches['inventory_tags'], $connection->device_id . '_' . $transactionNumber, json_encode($newTags));
            
            // 更新client
            $device->last_time = time();
            $device->sn ++;
            Cache::getInstance()->hSet(Config::caches['clients'], $connection->device_id, json_encode($device));
        } else {
            $tags = Cache::getInstance()->hGet(Config::caches['inventory_tags'], $connection->device_id . '_' . $transactionNumber);
            if ($tags) {
                $tags = json_decode($tags, true);
                $inventoryTags = [
                    'more' => array_merge($inventoryTags['more'], $tags['more']),
                    'less' => array_merge($inventoryTags['less'], $tags['less']),
                    'last' => array_merge($inventoryTags['last'], $tags['last'])
                ];
            }
            
            // 更新汇总表
            DB::getInstance()->update('wl_device_inventory_logs', [
                'more' => count($inventoryTags['more']),
                'less' => count($inventoryTags['less']),
                'last' => count($inventoryTags['last'])
            ], [
                'inventory_id' => $transactionNumber
            ]);
            
            // 插入标签
            $inserts = "insert into  `wl_device_inventory_tags` (`device_id`,`tag`,`inventory_id`,`status`) values ";
            
            $values = '';
            
            if (! empty($inventoryTags['last'])) {
                foreach ($inventoryTags['last'] as $tag) {
                    $values .= '(' . $connection->device_id . ',\'' . $tag . '\',\'' . $transactionNumber . '\',1),';
                }
                unset($tag);
            }
            if (! empty($inventoryTags['more'])) {
                foreach ($inventoryTags['more'] as $tag) {
                    $values .= '(' . $connection->device_id . ',\'' . $tag . '\',\'' . $transactionNumber . '\',2),';
                }
                unset($tag);
            }
            if (! empty($inventoryTags['less'])) {
                foreach ($inventoryTags['less'] as $tag) {
                    $values .= '(' . $connection->device_id . ',\'' . $tag . '\',\'' . $transactionNumber . '\',3),';
                }
                unset($tag);
            }
            $sql = $inserts . rtrim($values, ',');
            DB::getInstance()->query($sql);
            
            // 更新client
            $device->current_transaction = 'waiting';
            $device->current_data = '';
            $device->last_time = time();
            $device->status = 0;
            $device->sn ++;
            Cache::getInstance()->hSet(Config::caches['clients'], $connection->device_id, json_encode($device));
        }
        
        // 删除缓存
        Cache::getInstance()->hDel(Config::caches['inventory_tags'], $connection->device_id . '_' . $transactionNumber);
        Cache::getInstance()->hDel(Config::caches['inventory_count'], $connection->device_id . '_' . $transactionNumber);
        
        // 发送结束标记
        Cache::getInstance()->publish(Config::broadcastChannels['server'], json_encode([
            'command' => 'Inventory',
            'data' => [
                'transaction_number' => $transactionNumber
            ]
        ]));
        // 响应客户端
        $responseData = $server->send($fd, Byte::getInstance()->setSn($headers['sn'] ++)
            ->setCommand(0x0d)
            ->response()
            ->pack());
        $responseRst = $server->send($fd, $responseData);
        return $responseRst;
    }

    // /以下为服务器广播监听请求API请求
    public function shopping($server, $fd, $from_id, $data, $headers, $client)
    {
        // $data = [
        // 'device_id' => 1000100010,
        // 'order_id' => 'ORDER_ID'
        // ];
        $device = Cache::getInstance()->hGet(Config::caches['clients'], $data['device_id']);
        
        if (! $device) {
            // needlog
            return false;
        }
        $device = json_decode($device);
        
        if ($device->status != 0) {
            // needlog
            return false;
        }
        
        // 创建开门ID
        $doorLogId = $data['transaction_number'];
        
        // 对应开门ID和订单ID
        DB::getInstance()->update('wl_device_orders', [
            'status' => 1, // '00 开门失败 01 开门成功 10 关门失败 11 关门成功 '
            'udpate_time' => date("Y-m-d H:i:s")
        
        ], [
            'device_door_log_id' => $doorLogId
        ]);
        
        $device->current_data = $doorLogId;
        $device->current_transaction = 'shopping';
        Cache::getInstance()->hSet(Config::caches['clients'], $data['device_id'], json_encode($device));
        $rst = $this->opendoor($server, $device, $doorLogId);
        if (! $rst) {
            // need log
            // 订单对应表 开门失败
            DB::getInstance()->update('wl_device_orders', [
                'status' => 0
            ], [
                'device_door_log_id' => $doorLogId
            ]);
            return false;
        } else {
            // 记录开门成功
            DB::getInstance()->update('wl_device_door_logs', [
                'status' => 1,
                'open_time' => date("Y-m-d H:i:s")
            ], [
                'id' => $doorLogId
            ]);
            return true;
        }
    }

    /**
     * 上货操作
     *
     * @param Object $server            
     * @param int $fd            
     * @param int $from_id            
     * @param array $data
     *            数据
     * @param array $headers
     *            数据
     * @param array $client            
     * @return boolean
     */
    public function store($server, $fd, $from_id, $data, $headers, $client)
    {
        // $data = [
        // 'device_id' => 1000100010,
        // 'order_id' => 'ORDER_ID'
        // ];
        $device = Cache::getInstance()->hGet(Config::caches['clients'], $data['device_id']);
        /*
         * {
         * \"login_id\": 100000196,
         * \"fd\": 2,
         * \"connect_time\": 1511339615,
         * \"login_time\": 1511339624,
         * \"last_time\": 1511339625,
         * \"tags\": 14,
         * \"tags_uploaded\": true,
         * \"weight\": 0,
         * \"current_transaction\": \"waiting\",
         * \"current_data\": \"\",
         * \"status\": 0,
         * \"sn\": 2
         * }
         */
        if (! $device) {
            // needlog
            return false;
        }
        $device = json_decode($device);
        if ($device->current_transaction != 'waiting') {
            // needlog
            return false;
        }
        if ($device->status != 0) {
            // needlog
            return false;
        }
        
        // 创建开门ID
        $doorLogId = DB::getInstance()->insert('wl_device_door_logs', [
            'login_id' => $device->login_id,
            'device_id' => $data['device_id'],
            'status' => 0,
            'action' => 2
        
        ], true);
        
        // 对应开门ID和订单ID
        $mapId = DB::getInstance()->insert('wl_device_store', [
            'device_door_log_id' => $doorLogId,
            'device_id' => $data['device_id'],
            'order_id' => $data['order_id'],
            'status' => 1, // '00 开门失败 01 开门成功 10 关门失败 11 关门成功 '
            'created_time' => date("Y-m-d H:i:s"),
            'udpate_time' => date("Y-m-d H:i:s")
        
        ], true);
        
        $device->current_data = $doorLogId;
        $device->current_transaction = 'store';
        Cache::getInstance()->hSet(Config::caches['clients'], $data['device_id'], json_encode($device));
        $rst = $this->opendoor($server, $device, $doorLogId);
        if (! $rst) {
            // need log
            // 订单对应表 开门失败
            DB::getInstance()->update('wl_device_orders', [
                'status' => 0
            ], [
                'id' => $mapId
            ]);
            return false;
        } else {
            // 记录开门成功
            DB::getInstance()->update('wl_device_door_logs', [
                'status' => 1,
                'open_time' => date("Y-m-d H:i:s")
            ], [
                'id' => $doorLogId
            ]);
            return true;
        }
    }

    /**
     * 开门
     *
     * @param Sock $server            
     * @param \stdClass $device            
     * @param string $transactionsId            
     * @return boolean
     */
    public function refresh($server, $device, $transactionsId)
    {
        $connection = Cache::getInstance()->hGet(Config::caches['connections'], $device->fd);
        $c = json_decode($connection);
        if (! $c->device_id) {
            return false;
        }
        $data = Byte::getInstance()->setSn($device->sn ++)
            ->setRefresh($transactionsId)
            ->pack();
        $rst = $server->send($device->fd, $data);
        return $rst;
        // need log;
    }

    public function refreshSummary($server, $fd, $from_id, $data, $headers, $client)
    {
        $bytes = Byte::getInstance()->unpack($data);
        $refreshInfo = $bytes->getRefreshSummary();
        $connection = Cache::getInstance()->hGet(Config::caches['connections'], $fd);
        $connection = json_decode($connection);
        
        if (! $connection->device_id) {
            $responseData = Byte::getInstance()->setSn($headers['sn'] ++)
            ->setCommand(0x0f)
            ->response(Error::hasNotBeReady)
            ->pack();
            $responseRst = $server->send($fd, $responseData);
            return $responseRst;
        }
        
        $device = Cache::getInstance()->hGet(Config::caches['clients'], $connection->device_id);
        $device = json_decode($device);
        $transactionNumber = $device->current_data;
        
        DB::getInstance()->update('wl_device_refresh_logs', [
            'finish_time' => date("Y-m-d H:i:s"),
            'tags' => $refreshInfo['count'],
            'weight' => $refreshInfo['weight']
        ], [
            'refresh_id' => $transactionNumber
        ]);
        
        if ($refreshInfo['count'] == 0) {
            // 发送结束标记
            Cache::getInstance()->publish(Config::broadcastChannels['server'], json_encode([
                'command' => 'Refresh',
                'data' => [
                    'transaction_number' => $transactionNumber
                ]
            ]));
            // 更新client
            $device->current_transaction = 'waiting';
            $device->current_data = '';
            $device->last_time = time();
            $device->status = 0;
            $device->sn ++;
            Cache::getInstance()->hSet(Config::caches['clients'], $connection->device_id, json_encode($device));
        } else {
            $device->last_time = time();
            Cache::getInstance()->hSet(Config::caches['clients'], $connection->device_id, json_encode($device));
            // 缓存标签变化数量
            Cache::getInstance()->hSet(Config::caches['refresh_count'], $connection->device_id . '_' . $transactionNumber, $refreshInfo['count']);
        }
        
        // 响应客户端
        $responseData = $server->send($fd, Byte::getInstance()->setSn($headers['sn'] ++)
            ->setCommand(0x0f)
            ->response()
            ->pack());
        $responseRst = $server->send($fd, $responseData);
        return $responseRst;
    }
    
    /**
     * 接收盘存标签
     * 0x0d
     */
    public function refreshTags($server, $fd, $from_id, $data, $headers, $client)
    {
        $bytes = Byte::getInstance()->unpack($data);
        $connection = Cache::getInstance()->hGet(Config::caches['connections'], $fd);
        $connection = json_decode($connection);
        
        if (! $connection->device_id) {
            $responseData = Byte::getInstance()->setSn($headers['sn'] ++)
            ->setCommand(0x0d)
            ->response(Error::hasNotBeReady)
            ->pack();
            $responseRst = $server->send($fd, $responseData);
            return $responseRst;
        }
        
        $refreshTags = $bytes->getRefreshTags();
        
        $device = Cache::getInstance()->hGet(Config::caches['clients'], $connection->device_id);
        $device = json_decode($device);
        $transactionNumber = $device->current_data;
        
        $total = Cache::getInstance()->hSet(Config::caches['refresh_count'], $connection->device_id . '_' . $transactionNumber);
        
        if ($refreshTags['is_last'] != 0) {
            $tags = Cache::getInstance()->hGet(Config::caches['refresh_tags'], $connection->device_id . '_' . $transactionNumber);
            if ($tags) {
                $tags = json_decode($tags, true);
            }
            $newTags = [
                'more' => array_merge($refreshTags['more'], $tags['more']),
                'less' => array_merge($refreshTags['less'], $tags['less']),
            ];
            Cache::getInstance()->hSet(Config::caches['refresh_tags'], $connection->device_id . '_' . $transactionNumber, json_encode($newTags));
            
            // 更新client
            $device->last_time = time();
            $device->sn ++;
            Cache::getInstance()->hSet(Config::caches['clients'], $connection->device_id, json_encode($device));
        } else {
            $tags = Cache::getInstance()->hGet(Config::caches['refresh_tags'], $connection->device_id . '_' . $transactionNumber);
            if ($tags) {
                $tags = json_decode($tags, true);
                $refreshTags = [
                    'more' => array_merge($refreshTags['more'], $tags['more']),
                    'less' => array_merge($refreshTags['less'], $tags['less']),
                ];
            }
            
            // 更新汇总表
            DB::getInstance()->update('wl_device_refresh_logs', [
                'more' => count($refreshTags['more']),
                'less' => count($refreshTags['less'])
            ], [
                'refresh_id' => $transactionNumber
            ]);
            
            // 插入标签
            $inserts = "insert into  `wl_device_refresh_tags` (`device_id`,`tag`,`refresh_id`,`status`) values ";
            
            $values = '';
            
            if (! empty($refreshTags['more'])) {
                foreach ($refreshTags['more'] as $tag) {
                    $values .= '(' . $connection->device_id . ',\'' . $tag . '\',\'' . $transactionNumber . '\',2),';
                }
                unset($tag);
            }
            if (! empty($refreshTags['less'])) {
                foreach ($refreshTags['less'] as $tag) {
                    $values .= '(' . $connection->device_id . ',\'' . $tag . '\',\'' . $transactionNumber . '\',3),';
                }
                unset($tag);
            }
            $sql = $inserts . rtrim($values, ',');
            DB::getInstance()->query($sql);
            
            // 更新client
            $device->current_transaction = 'waiting';
            $device->current_data = '';
            $device->last_time = time();
            $device->status = 0;
            $device->sn ++;
            Cache::getInstance()->hSet(Config::caches['clients'], $connection->device_id, json_encode($device));
        }
        
        // 删除缓存
        Cache::getInstance()->hDel(Config::caches['refresh_tags'], $connection->device_id . '_' . $transactionNumber);
        Cache::getInstance()->hDel(Config::caches['refresh_count'], $connection->device_id . '_' . $transactionNumber);
        
        // 发送结束标记
        Cache::getInstance()->publish(Config::broadcastChannels['server'], json_encode([
            'command' => 'Refresh',
            'data' => [
                'transaction_number' => $transactionNumber
            ]
        ]));
        // 响应客户端
        $responseData = $server->send($fd, Byte::getInstance()->setSn($headers['sn'] ++)
            ->setCommand(0x10)
            ->response()
            ->pack());
        $responseRst = $server->send($fd, $responseData);
        return $responseRst;
    }

    /**
     *
     * @param resource $server            
     * @param int $fd            
     * @param string $data            
     * @return bool
     */
    private function send($server, $fd, $data)
    {
        return $server->send($fd, $data);
    }

    /**
     *
     * @param resource $server            
     * @param int $fd            
     * @param string $data            
     * @return bool
     */
    private function close($server, $fd, $data)
    {
        return $server->close($fd);
    }
}
