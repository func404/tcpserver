<?php
namespace tcp;

use lib\Device;

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
            $closeRst = $this->send($server, $fd, $responseData, true);
            return $closeRst;
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
            // RESPONSE deviceUnlogined
            $closeRst = $this->send($server, $fd, $responseData, true);
            return $closeRst;
        }

        if ($loginInfo['transaction_number']) { //如果流水号存在
            Cache::getInstance()->delete('');         
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
        
        $clientDevice = new Device();
        $clientDevice->login_id = $loginId;
        $clientDevice->device_id = $device['device_id'];
        $clientDevice->fd = $fd;
        $clientDevice->connect_time = $client['connect_time'];
        $clientDevice->last_time = $clientDevice->login_time = $client['last_time'];
        $clientDevice->tags = $loginInfo['tags'];
        $clientDevice->tags_uploaded = ($loginInfo['tags'] == 0);
        $clientDevice->weight = $loginInfo['weight'];
        $clientDevice->current_data = '';
        $clientDevice->current_transaction = 'waiting';
        $clientDevice->status = $loginInfo['status'];
        $clientDevice->sn = ++ $headers['sn'];
        /*
         * 缓存客户端信息
         */
        if ($loginInfo['transaction_number']) {
            $clientDevice->current_data = $loginInfo['transaction_number'];
            $clientDevice->current_transaction = 'shopping';
        }
        Cache::getInstance()->hSet(Config::caches['clients'], $device['device_id'], json_encode($clientDevice->toArray()));
        
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
     *
     * @param resource $server
     *            TCP server
     * @param int $fd            
     * @param unknown $from_id            
     * @param string $data            
     * @param array $headers            
     * @param array $client            
     * @return boolean
     */
    public function heartbeat($server, $fd, $from_id, $data, $headers, $client)
    {
        $device = $this->getDevice($server, $fd, $headers);
        $bytes = Byte::getInstance()->unpack($data);
        if (! $device) {
            $responseData = $bytes->setSn($headers['sn'] ++)
                ->response(Error::heartBeatTimeout)
                ->pack();
            $closeRst = $this->send($server, $fd, $responseData, true);
            Logger::getInstance()->write('HeartBeatTimeout:' . Error::heartBeatTimeout . '|' . 'RequestFrom:' . $client['remote_ip'] . ':' . $client['remote_port'], 'error');
            return $closeRst;
        } else {
            $responseData = $bytes->setSn($headers['sn'])
                ->response()
                ->pack();
            // RESPONSE HEATBEAT
            $responseRst = $this->send($server, $fd, $responseData);
            return $responseRst;
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
        return $rst;
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
        $device = $this->getDevice($server, $fd, $headers);
        $bytes = Byte::getInstance()->unpack($data);
        if (! $device) {
            $responseData = $bytes->hasNotBeReady($headers['sn'] ++)
                ->response(Error::hasNotBeReady)
                ->pack();
            $closeRst = $this->send($server, $fd, $responseData, true);
            Logger::getInstance()->write('hasNotBeReady:' . Error::hasNotBeReady . '|' . 'RequestFrom:' . $client['remote_ip'] . ':' . $client['remote_port'], 'error');
            return $closeRst;
        }
        
        $closeInfo = $bytes->getClosedoor();
        $transactionNumber = $device['current_data'];
        
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
            $this->freeDevice($device);
        } else {
            // 缓存标签变化数量
            Cache::getInstance()->hSet(Config::caches['transaction_count'], $device['device_id'] . '_' . $transactionNumber, $closeInfo['different_count']);
        }
        
        // 响应客户端
        $responseData = $bytes->setSn($headers['sn'] ++)
            ->response()
            ->pack();
        $responseRst = $this->send($server, $fd, $responseData);
        Logger::getInstance()->write($client['remote_ip'] . '|' . $headers['command'] . '|' . $bytes->getResponseData() . '|' . intval($responseRst), 'response');
        return $responseRst;
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
        $device = $this->getDevice($server, $fd, $headers);
        $bytes = Byte::getInstance()->unpack($data);
        if (! $device) {
            $responseData = $bytes->setSn($headers['sn'] ++)
                ->response(Error::hasNotBeReady)
                ->pack();
            $closeRst = $this->send($server, $fd, $responseData, true);
            Logger::getInstance()->write('hasNotBeReady:' . Error::hasNotBeReady . '|' . 'RequestFrom:' . $client['remote_ip'] . ':' . $client['remote_port'], 'error');
            return $closeRst;
        }
        
        $transactionNumber = $device->current_data;
        $moreValues = $lessValues = [];
        $closeTags = $bytes->getTransactionTags();
        
        if ($closeTags['is_last'] == 1) {
            // 关门
            DB::getInstance()->update('wl_device_door_logs', [
                'status' => 1,
                'close_time' => date("Y-m-d H:i:s")
            ], [
                'id' => $transactionNumber
            ]);
            
            // 检查缓存中的标签
            $tags = Cache::getInstance()->hGet(Config::caches['transaction_tags'], $device->device_id . '_' . $transactionNumber);
            if ($tags) {
                $tags = json_decode($tags, true);
                $closeTags['more'] = array_merge($closeTags['more'], $tags['more']);
                $closeTags['less'] = array_merge($closeTags['less'], $tags['less']);
            }
            
            // 排重
            $moreTags = array_unique($closeTags['more']);
            $lessTags = array_unique($closeTags['less']);
            
            $total = count($moreTags) + count($lessTags);
            
            if ($total == 0) {
                $responseData = $bytes->setSn($headers['sn'] ++)
                    ->response(Error::tagsNumberUnmatch)
                    ->pack();
                return $this->send($server, $fd, $responseData);
            }
            
            $inserts = "Insert into `wl_device_tag_logs`(`tag`,`type`,`direct`,`device_door_log_id`) values ";
            
            if (! empty($moreTags)) {
                foreach ($moreTags as $moreTag) {
                    $inserts .= "('" . $moreTag . "', " . Config::action['shopping'] . "," . Config::direct['more'] . "," . $transactionNumber . "),";
                }
            }
            if (! empty($lessTags)) {
                foreach ($lessTags as $lessTag) {
                    $lessValues[] = "('" . $lessTag . "', " . Config::action['shopping'] . "," . Config::direct['less'] . "," . $transactionNumber . "),";
                }
            }
            $sql = rtrim($inserts, ',');
            DB::getInstance()->query($sql);
            $rst = DB::getInstance()->getInsertId();
            
            // 清除缓存数据
            Cache::getInstance()->hDel(Config::caches['transaction_count'], $device->device_id . '_' . $transactionNumber);
            Cache::getInstance()->hDel(Config::caches['transaction_tags'], $device->device_id . '_' . $transactionNumber);
            
            // 释放设备
            $this->freeDevice($device);
            
            if ($total != Cache::getInstance()->hGet(Config::caches['transaction_count'], $device['device_id'] . '_' . $transactionNumber)) {
                $responseData = $bytes->setSn($headers['sn'] ++)
                    ->response(Error::tagsNumberUnmatch)
                    ->pack();
                return $this->send($server, $fd, $responseData);
            } else {
                $responseData = $bytes->setSn($headers['sn'] ++)
                    ->response()
                    ->pack();
                return $this->send($server, $fd, $responseData);
            }
        } else {
            // 检查缓存中的标签
            $tags = Cache::getInstance()->hGet(Config::caches['transaction_tags'], $device->device_id . '_' . $transactionNumber);
            if ($tags) {
                $tags = json_decode($tags, true);
                $closeTags['more'] = array_merge($closeTags['more'], $tags['more']);
                $closeTags['less'] = array_merge($closeTags['less'], $tags['less']);
            }
            // 添加缓存
            Cache::getInstance()->hSet(Config::caches['transaction_tags'], $device->device_id . '_' . $transactionNumber, json_encode([
                'more' => $closeTags['more'],
                'less' => $closeTags['less']
            ]));
            $responseData = $bytes->setSn($headers['sn'] ++)
                ->response()
                ->pack();
            return $this->send($server, $fd, $responseData);
        }
    }

    /**
     * 设备状态
     * 0x06
     * I7
     */
    public function deviceStatus($server, $fd, $from_id, $data, $headers, $client)
    {
        $bytes = Byte::getInstance()->unpack($data);
        $device = $this->getDevice($server, $fd, $headers);
        if (! $device) {
            $responseData = $bytes->setSn($headers['sn'] ++)
                ->response(Error::deviceUnlogined)
                ->pack();
            $closeRst = $this->send($server, $fd, $responseData, true);
            Logger::getInstance()->write('deviceUnlogined:' . Error::deviceUnlogined . '|' . 'RequestFrom:' . $client['remote_ip'] . ':' . $client['remote_port'], 'error');
            return $closeRst;
        }
        
        $status = $bytes->getDeviceStatus();
        // 修改设备状态
        $device->status = $status;
        Cache::getInstance()->hSet(Config::caches['clients'], $device->device_id, json_encode($device->toArray()));
        $responseData = $bytes->setSn($headers['sn'])
            ->response(0)
            ->pack();
        return $this->send($server, $fd, $responseData);
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
        $bytes = Byte::getInstance()->unpack($data);
        $device = $this->getDevice($server, $fd, $headers);
        if (! $device) {
            $responseData = $bytes->setSn($headers['sn'] ++)
                ->response(Error::deviceUnlogined)
                ->pack();
            $closeRst = $this->send($server, $fd, $responseData, true);
            Logger::getInstance()->write('deviceUnlogined:' . Error::deviceUnlogined . '|' . 'RequestFrom:' . $client['remote_ip'] . ':' . $client['remote_port'], 'error');
            return $closeRst;
        }
        $loginTags = $bytes->getLoginTags();
        $transactionNumber = $device->login_id;
        if ($loginTags['is_last'] == 1) {
            $tags = Cache::getInstance()->hGet(Config::caches['login_tags'], $device->device_id . '_' . $transactionNumber);
            if ($tags) {
                $tags = json_decode($tags, true);
                $loginTags['tags'] = array_merge($loginTags['tags'], $tags);
            }
            $tags = array_unique($loginTags['tags']);
            $total = count($tags);
            
            if ($total == 0) {
                $responseData = $bytes->setSn($headers['sn'] ++)
                    ->response(Error::tagsNumberUnmatch)
                    ->pack();
                return $this->send($server, $fd, $responseData);
            }
            $inserts = "Insert into  `wl_device_tags` (`device_id`,`device_login_id`,`tag`,`created_time`) values ";
            $dt = date("Y-m-d H:i:s");
            foreach ($tags as $tag) {
                $inserts .= "('" . $device->device_id . "', " . $device->login_id . ",'" . $tag . "','" . $dt . "'),";
            }
            
            $sql = rtrim($inserts, ',');
            DB::getInstance()->query($sql);
            $rst = DB::getInstance()->getInsertId();
            
            // 清除缓存数据
            Cache::getInstance()->hDel(Config::caches['login_count'], $device->device_id . '_' . $transactionNumber);
            Cache::getInstance()->hDel(Config::caches['login_tags'], $device->device_id . '_' . $transactionNumber);
            
            $device->tags_uploaded = true;
            Cache::getInstance()->hSet(Config::caches['clients'], $device->device_id, json_encode($device->toArray()));
            
            if ($total != Cache::getInstance()->hGet($device->tags, $device->device_id . '_' . $transactionNumber)) {
                $responseData = $bytes->setSn($headers['sn'] ++)
                    ->response(Error::tagsNumberUnmatch)
                    ->pack();
                return $this->send($server, $fd, $responseData);
            } else {
                $responseData = $bytes->setSn($headers['sn'] ++)
                    ->response()
                    ->pack();
                return $this->send($server, $fd, $responseData);
            }
        } else {
            // 检查缓存中的标签
            $tags = Cache::getInstance()->hGet(Config::caches['login_tags'], $device->device_id . '_' . $transactionNumber);
            if ($tags) {
                $tags = json_decode($tags, true);
                $loginTags['tags'] = array_merge($loginTags['tags'], $tags);
            }
            // 添加缓存
            Cache::getInstance()->hSet(Config::caches['login_tags'], $device->device_id . '_' . $transactionNumber, json_encode($loginTags['tags']));
            $responseData = $bytes->setSn($headers['sn'] ++)
                ->response()
                ->pack();
            return $this->send($server, $fd, $responseData);
        }
    }

    /**
     * 盘存
     *
     * @param Sock $server            
     * @param \stdClass $device            
     * @param string $transactionsId            
     * @return boolean
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
    }

    /**
     * 接收盘存汇总
     * 0x0c
     */
    public function inventorySummary($server, $fd, $from_id, $data, $headers, $client)
    {
        $bytes = Byte::getInstance()->unpack($data);
        $device = $this->getDevice($server, $fd, $headers);
        if (! $device) {
            $responseData = $bytes->setSn($headers['sn'] ++)
                ->response(Error::hasNotBeReady)
                ->pack();
            $closeRst = $this->send($server, $fd, $responseData, true);
            Logger::getInstance()->write('hasNotBeReady:' . Error::hasNotBeReady . '|' . 'RequestFrom:' . $client['remote_ip'] . ':' . $client['remote_port'], 'error');
            return $closeRst;
        }
        
        $transactionNumber = $device->current_data;
        $inventoryInfo = $bytes->getInventorySummary();
        
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
            $this->freeDevice($device);
        } else {
            // 缓存标签变化数量
            Cache::getInstance()->hSet(Config::caches['inventory_count'], $device->device_id . '_' . $transactionNumber, $inventoryInfo['count']);
        }
        
        // 响应客户端
        $responseData = $bytes->setSn($headers['sn'] ++)
            ->response()
            ->pack();
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
        $device = $this->getDevice($server, $fd, $headers);
        if (! $device) {
            $responseData = $bytes->setSn($headers['sn'] ++)
                ->response(Error::hasNotBeReady)
                ->pack();
            $closeRst = $this->send($server, $fd, $responseData, true);
            Logger::getInstance()->write('hasNotBeReady:' . Error::hasNotBeReady . '|' . 'RequestFrom:' . $client['remote_ip'] . ':' . $client['remote_port'], 'error');
            return $closeRst;
        }
        $transactionNumber = $device->current_data;
        $inventoryTags = $bytes->getInventoryTags();
        $total = Cache::getInstance()->hSet(Config::caches['inventory_count'], $device->device_id . '_' . $transactionNumber);
        
        if ($inventoryTags['is_last'] == 1) {
            $tags = Cache::getInstance()->hGet(Config::caches['inventory_tags'], $device->device_id . '_' . $transactionNumber);
            if ($tags) {
                $tags = json_decode($tags, true);
                $inventoryTags['more'] = array_merge($inventoryTags['more'], $tags['more']);
                $inventoryTags['less'] = array_merge($inventoryTags['less'], $tags['less']);
                $inventoryTags['last'] = array_merge($inventoryTags['last'], $tags['last']);
            }
            
            $moreTags = array_unique($inventoryTags['more']);
            $lessTags = array_unique($inventoryTags['less']);
            $lastTags = array_unique($inventoryTags['last']);
            // 更新汇总表
            DB::getInstance()->update('wl_device_inventory_logs', [
                'more' => count($moreTags),
                'less' => count($lessTags),
                'last' => count($lastTags)
            ], [
                'inventory_id' => $transactionNumber
            ]);
            
            // 插入标签
            $inserts = "insert into  `wl_device_inventory_tags` (`device_id`,`tag`,`inventory_id`,`status`) values ";
            if (! empty($lastTags)) {
                foreach ($lastTags as $tag) {
                    $inserts .= '(' . $device->device_id . ',\'' . $tag . '\',\'' . $transactionNumber . '\',1),';
                }
                unset($tag);
            }
            
            if (! empty($moreTags)) {
                foreach ($moreTags as $tag) {
                    $inserts .= '(' . $device->device_id . ',\'' . $tag . '\',\'' . $transactionNumber . '\',2),';
                }
                unset($tag);
            }
            if (! empty($lessTags)) {
                foreach ($lessTags as $tag) {
                    $inserts .= '(' . $device->device_id . ',\'' . $tag . '\',\'' . $transactionNumber . '\',3),';
                }
                unset($tag);
            }
            $sql = rtrim($inserts, ',');
            DB::getInstance()->query($sql);
            $this->freeDevice($device);
            // 删除缓存
            Cache::getInstance()->hDel(Config::caches['inventory_tags'], $device->device_id . '_' . $transactionNumber);
            Cache::getInstance()->hDel(Config::caches['inventory_count'], $device->device_id . '_' . $transactionNumber);
            
            // 发送结束标记
            Cache::getInstance()->publish(Config::broadcastChannels['server'], json_encode([
                'command' => 'Inventory',
                'data' => [
                    'transaction_number' => $transactionNumber
                ]
            ]));
        } else {
            $tags = Cache::getInstance()->hGet(Config::caches['inventory_tags'], $device->device_id . '_' . $transactionNumber);
            if ($tags) {
                $tags = json_decode($tags, true);
            }
            $newTags = [
                'more' => array_merge($inventoryTags['more'], $tags['more']),
                'less' => array_merge($inventoryTags['less'], $tags['less']),
                'last' => array_merge($inventoryTags['last'], $tags['last'])
            ];
            Cache::getInstance()->hSet(Config::caches['inventory_tags'], $device->device_id . '_' . $transactionNumber, json_encode($newTags));
        }
        
        // 响应客户端
        $responseData = $bytes->setSn($headers['sn'] ++)
            ->response()
            ->pack();
        $responseRst = $this->send($server, $fd, $data);
        return $responseRst;
    }

    /**
     * 刷新机柜梳理
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
    }

    public function refreshSummary($server, $fd, $from_id, $data, $headers, $client)
    {
        $bytes = Byte::getInstance()->unpack($data);
        $device = $this->getDevice($server, $fd, $headers);
        if (! $device) {
            $responseData = $bytes->setSn($headers['sn'] ++)
                ->response(Error::hasNotBeReady)
                ->pack();
            $closeRst = $this->send($server, $fd, $responseData, true);
            Logger::getInstance()->write('hasNotBeReady:' . Error::hasNotBeReady . '|' . 'RequestFrom:' . $client['remote_ip'] . ':' . $client['remote_port'], 'error');
            return $closeRst;
        }
        
        $transactionNumber = $device->current_data;
        $refreshInfo = $bytes->getInventorySummary();
        
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
            $this->freeDevice($device);
        } else {
            // 缓存标签变化数量
            Cache::getInstance()->hSet(Config::caches['refresh_count'], $device->device_id . '_' . $transactionNumber, $refreshInfo['count']);
        }
        // 响应客户端
        $responseData = $bytes->setSn($headers['sn'] ++)
            ->response()
            ->pack();
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
        $device = $this->getDevice($server, $fd, $headers);
        if (! $device) {
            $responseData = $bytes->setSn($headers['sn'] ++)
                ->response(Error::hasNotBeReady)
                ->pack();
            $closeRst = $this->send($server, $fd, $responseData, true);
            Logger::getInstance()->write('hasNotBeReady:' . Error::hasNotBeReady . '|' . 'RequestFrom:' . $client['remote_ip'] . ':' . $client['remote_port'], 'error');
            return $closeRst;
        }
        
        $transactionNumber = $device->current_data;
        $refreshTags = $bytes->getRefreshTags();
        $total = Cache::getInstance()->hSet(Config::caches['refresh_count'], $device->device_id . '_' . $transactionNumber);
        
        if ($refreshTags['is_last'] == 1) {
            $tags = Cache::getInstance()->hGet(Config::caches['refresh_tags'], $device->device_id . '_' . $transactionNumber);
            if ($tags) {
                $tags = json_decode($tags, true);
                $refreshTags['more'] = array_merge($refreshTags['more'], $tags['more']);
                $refreshTags['less'] = array_merge($refreshTags['less'], $tags['less']);
            }
            
            $moreTags = array_unique($refreshTags['more']);
            $lessTags = array_unique($refreshTags['less']);
            // 更新汇总表
            DB::getInstance()->update('wl_device_refresh_logs', [
                'more' => count($refreshTags['more']),
                'less' => count($refreshTags['less'])
            ], [
                'refresh_id' => $transactionNumber
            ]);
            
            // 插入标签
            $inserts = "insert into  `wl_device_refresh_tags` (`device_id`,`tag`,`refresh_id`,`status`) values ";
            if (! empty($moreTags)) {
                foreach ($moreTags as $tag) {
                    $inserts .= '(' . $device->device_id . ',\'' . $tag . '\',\'' . $transactionNumber . '\',2),';
                }
                unset($tag);
            }
            if (! empty($lessTags)) {
                foreach ($lessTags as $tag) {
                    $inserts .= '(' . $device->device_id . ',\'' . $tag . '\',\'' . $transactionNumber . '\',3),';
                }
                unset($tag);
            }
            $sql = rtrim($inserts, ',');
            DB::getInstance()->query($sql);
            
            // 释放设备
            $this->freeDevice($device);
            // 删除缓存
            Cache::getInstance()->hDel(Config::caches['refresh_tags'], $device->device_id . '_' . $transactionNumber);
            Cache::getInstance()->hDel(Config::caches['refresh_count'], $device->device_id . '_' . $transactionNumber);
            
            // 发送结束标记
            Cache::getInstance()->publish(Config::broadcastChannels['server'], json_encode([
                'command' => 'Refresh',
                'data' => [
                    'transaction_number' => $transactionNumber
                ]
            ]));
        } else {
            $tags = Cache::getInstance()->hGet(Config::caches['refresh_tags'], $device->device_id . '_' . $transactionNumber);
            if ($tags) {
                $tags = json_decode($tags, true);
            }
            $newTags = [
                'more' => array_merge($refreshTags['more'], $tags['more']),
                'less' => array_merge($refreshTags['less'], $tags['less'])
            ];
            Cache::getInstance()->hSet(Config::caches['inventory_tags'], $device->device_id . '_' . $transactionNumber, json_encode($newTags));
        }
        
        // 响应客户端
        $responseData = $bytes->setSn($headers['sn'] ++)
            ->response()
            ->pack();
        $responseRst = $this->send($server, $fd, $data);
        return $responseRst;
    }

    /**
     *
     * @param resource $server            
     * @param int $fd            
     * @param int $from_id            
     * @param array $data            
     * @param array $headers            
     * @param array $client            
     * @return boolean
     */
    public function orderShopping($server, $fd, $from_id, $data, $headers, $client)
    {
        $device = Cache::getInstance()->hGet(Config::caches['clients'], $data['device_id']);
        if (! $device) {
            return false;
        }
        $device = json_decode($device);
        if ($device->status != 0) {
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
        
        $rst = $this->openDoor($server, $device, $doorLogId);
        if (! $rst) {
            DB::getInstance()->update('wl_device_orders', [
                'status' => 0
            ], [
                'device_door_log_id' => $doorLogId
            ]);
            return false;
        } else {
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
    public function orderStore($server, $fd, $from_id, $data, $headers, $client)
    {
        $device = Cache::getInstance()->hGet(Config::caches['clients'], $data['device_id']);
        if (! $device) {
            return false;
        }
        $device = json_decode($device);
        if ($device->status != 0) {
            return false;
        }
        
        // 创建开门ID
        $doorLogId = $data['transaction_number'];
        // 对应开门ID和备货ID
        DB::getInstance()->update('wl_device_stores', [
            'status' => 1, // '00 开门失败 01 开门成功 10 关门失败 11 关门成功 '
            'udpate_time' => date("Y-m-d H:i:s")
        ], [
            'device_door_log_id' => $doorLogId
        ]);
        $device->current_data = $doorLogId;
        $device->current_transaction = 'store';
        Cache::getInstance()->hSet(Config::caches['clients'], $data['device_id'], json_encode($device));
        
        $rst = $this->opendoor($server, $device, $doorLogId);
        if (! $rst) {
            DB::getInstance()->update('wl_device_orders', [
                'status' => 0
            ], [
                'device_door_log_id' => $doorLogId
            ]);
            return false;
        } else {
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
     * 盘存操作
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
    public function orderInventory($server, $fd, $from_id, $data, $headers, $client)
    {
        $device = Cache::getInstance()->hGet(Config::caches['clients'], $data['device_id']);
        if (! $device) {
            return false;
        }
        $device = json_decode($device);
        if ($device->status != 0) {
            return false;
        }
        $inventoryId = $data['transaction_number'];
        
        DB::getInstance()->update('wl_device_inventory_logs', [
            'udpate_time' => date("Y-m-d H:i:s")
        ], [
            'inventory_id' => $inventoryId
        ]);
        $device->current_data = $inventoryId;
        $device->current_transaction = 'inventory';
        Cache::getInstance()->hSet(Config::caches['clients'], $data['device_id'], json_encode($device));
        $rst = $this->inventory($server, $device, $inventoryId);
        return $rst;
    }

    /**
     * 刷新设备缓存操作
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
    public function orderRefresh($server, $fd, $from_id, $data, $headers, $client)
    {
        $device = Cache::getInstance()->hGet(Config::caches['clients'], $data['device_id']);
        if (! $device) {
            return false;
        }
        $device = json_decode($device);
        if ($device->status != 0) {
            return false;
        }
        $refreshId = $data['transaction_number'];
        
        DB::getInstance()->update('wl_device_refresh_logs', [
            'udpate_time' => date("Y-m-d H:i:s")
        ], [
            'inventory_id' => $refreshId
        ]);
        $device->current_data = $refreshId;
        $device->current_transaction = 'refresh';
        
        Cache::getInstance()->hSet(Config::caches['clients'], $data['device_id'], json_encode($device));
        $rst = $this->inventory($server, $device, $refreshId);
        return $rst;
    }

    /**
     * 关闭客户端
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
    public function orderClose($server, $fd, $from_id, $data, $headers, $client)
    {
        $device = Cache::getInstance()->hGet(Config::caches['clients'], $data['device_id']);
        if (! $device) {
            return false;
        } else {
            return $server->close($device->fd);
        }
    }

    /**
     * 查看服务器运行状态
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
    public function orderStatus($server, $fd, $from_id, $data, $headers, $client)
    {
        $serverInfo = [
            'setting' => $server->setting,
            'master_pid' => $server->master_pid,
            'connections' => $server->connections
        ];
        error_log(var_export($serverInfo, 1), 3, '/tmp/serverInfo');
    }

    /**
     * 获取设备详情
     *
     * @param resource $server            
     * @param int $fd            
     * @param array $headers            
     * @return boolean|\tcp\Device|mixed
     */
    private function getDevice($server, $fd, $headers)
    {
        $connection = json_decode(Cache::getInstance()->hGet(Config::caches['connections'], $fd));
        if (! $connection) {
            return false;
        }
        if (! $connection->device_id) {
            return false;
        }
        
        $device = Cache::getInstance()->hGet(Config::caches['clients'], $connection->device_id);
        $device = json_decode($device, true);
        Cache::getInstance()->hSet(Config::caches['clients'], $connection->device_id, json_encode(array_merge($device, [
            'last_time' => time(),
            'sn' => $headers['sn'] ++
        ])));
        $clientDevice = new Device();
        foreach ($device as $key => $value) {
            $clientDevice[$key] = $value;
        }
        return $clientDevice;
    }

    /**
     * 释放 服务器
     *
     * @param array $device            
     * @return boolean
     */
    private function freeDevice($device)
    {
        $device->current_transaction = 'waiting';
        $device->current_data = '';
        Cache::getInstance()->hSet(Config::caches['clients'], $device->device_id, json_encode($device->toArray()));
        return true;
    }

    /**
     *
     * @param resource $server            
     * @param int $fd            
     * @param string $data            
     * @return bool
     */
    private function send($server, $fd, $data, $close = false)
    {
        $rst = $server->send($fd, $data);
        if ($close) {
            return $server->close($fd);
        }
        return $rst;
    }
}
