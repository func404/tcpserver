<?php
namespace tcp;

/**
 * 配置参数文档
 * created on 2017-11-06
 *
 * last modify 2017-11-19
 *
 * @author duxin
 *        
 */
final class Config
{

    const timezone = 'Asia/Shanghai';

    const version = 'WLXS0.1';

    const processName = 'wlxs_tcpserver';

    const bytesMap = [
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

    const serverMap = [
        0x01 => 'login',
        0x07 => 'loginTags',
        0x02 => 'heartbeat',
        0x04 => 'closeDoor',
        0x05 => 'closeTags',
        0x06 => 'deviceStatus',
        0x08 => 'deliverRequest',
        0x0a => 'deliverData',
        0x0b => 'inventory',
        0x0c => 'inventorySummary',
        0x0d => 'inventoryTags',
        0x0e => 'refresh',
        0x0f => 'refreshSummary',
        0x10 => 'refreshTags',
        'SHOPPING' => 'shopping',
        'BOOKED' => 'openDoor',
        'INVENTORY' => 'inventory', // 0x0b
        'REFRESH' => 'refresh', // 0x0e
        'CLOSE' => 'close' /* 关闭客户端连接 */
    
    ];

    const clientResponse = [
        0x03 => 'login'
    ];

    const db = [
        'host' => '127.0.0.1',
        'port' => 3306,
        'user' => 'wlxs',
        'pass' => '!@#qweASD2017',
        'libr' => 'wlxs',
        'charset' => 'utf8'
    ];

    const redis = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'auth' => '!@#qweASD2017'
    ];

    const tcpServer = [
        'host' => '0.0.0.0',
        'port' => '9888'
    ];

    const tcpServerOpt = [
        'worker_num' => 8,
        'daemonize' => true,
        'max_request' => 10000,
        'dispatch_mode' => 2,
        'debug_mode' => 1,
        'log_file' => '/data/log/wlxs_tcp.log',
        'pid_file' => '/var/run/wlxs_tcp.pid'
    ];

    const ipAllow = [
        // '115.28.37.125',
        '118.190.205.103'
    ];

    /**
     * 登录超时时限 单位 ms
     *
     * @var integer
     */
    const loginTimeout = 1000000;

    /**
     * 检查区间
     */
    const checkInterval = 80000;

    /**
     * 心跳 30秒内没有心跳默认断开连接
     */
    const heartbeat = 3000;
    
    /**
     * 客户端 命令
     * @var array
     */
    const commands = [
        'shop',
        'inventory',
        'refresh',
        'store'
    ];

    // 广播部分{{{{{
    const broadcastChannels = [
        'client' => 'wlxs_clientChannel', // API发起
        'server' => 'wlxs_serverChannel', // TCP发起
        'device' => 'wlxs_deviceMonitor',
        'request' => 'wlxs_deviceRequest'
    ];

    const clientChannel = [
        'OPD' => 'openDoor',
        'NAT' => 'needAllTags'
    ];

    const serverChannel = [
        'CLD' => 'closeDoor',
        'CLT' => 'closeTags'
    ];

    // 广播部分}}}}
    
    // 缓存结构
    const clientStore = [
        
        'connections' => [
            'clientIp_connectTime' => FD
        ],
        
        'clients' => [
            'device_number' => [
                'login_fd' => FD,
                'login_id' => '',
                'logined' => 0,
                'login_time' => 23232323,
                'last_connect_time' => 232323,
                'sn' => 244 % 256,
                'login_ip' => '127.0.0.1'
            ]
        ]
    
    ];

    const client = [
        'host' => '118.190.205.103',
        'port' => 9888,
        'timeout' => 0.1,
        'flag' => 0
    ];

    const rest = [
        'base_url' => 'http://api.weilaixiansen.com/rest/'
    ];

    const caches = [
        'connections' => 'wlxs_connections',
        'clients' => 'wlxs_tcpclient',
        'tags' => 'wlxs_tmptags',
        'tags_count' => 'wlxs_tagscount',
        'inventory_count' => 'wlxs_inventory_count', //缓存盘存数量
        'inventory_tags' => 'wlxs_inventory_tags', //缓存盘存的标签
        'refresh_count' => 'wlxs_refresh_count', //缓存
        'refresh_tags' => 'wlxs_refresh_tags'
    ];

    const transaction = [
        'shop' => 'shopping',
        'putin' => 'putin',
        'inventory' => 'inventory',
        'refresh' => 'refresh',
        'book' => 'book'
    
    ];

    const order = [
        'created' => 0,
        'opendoor' => 1,
        'closedoor' => 2,
        'paying' => 3,
        'success' => 4
    ];

    /**
     * 上货操作
     *
     * @var array
     */
    const direct = [
        'more' => 1,
        'less' => 2,
        'equal' => 3
    ];

    const action = [
        'shopping' => 1
    ];
}
