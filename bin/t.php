<?php
include '../lib/DB.php';
include '../config/Config.php';
include '../lib/Cache.php';
$db = lib\DB::getInstance();
$cache = lib\Cache::getInstance();
$ch = config\Config::broadcastChannels['client'];
$commands = config\Config::orderMap;
if ($argc < 2) {
    fwrite(STDOUT, "Please choose command: \n");
    $i = 1;
    $tmp = [];
    foreach ($commands as $key => $value) {
        fwrite(STDOUT, "$i ): $key,$value\n");
        $tmp[$i] = $key;
        $i ++;
    }
    $j = trim(fgets(STDIN));
    $do = strtoupper($tmp[$j]);
} else {
    $do = strtoupper($argv[1]);
}

if (! array_key_exists($do, $commands)) {
    fwrite(STDOUT, "Command: ");
    $i = 1;
    $tmp = [];
    foreach ($commands as $key => $value) {
        fwrite(STDOUT, "$i ): $key,$value");
        $tmp[$i] = $key;
    }
}

// 'SHOPPING' => 'orderShopping',
// 'BOOKED' => 'openDoor',
// 'INVENTORY' => 'orderInventory', // 0x0b
// 'REFRESH' => 'orderRefresh', // 0x0e
// 'STATUS' => 'orderStatus', // 0x0e
// 'CLOSE' => 'orderClose' /* 关闭客户端连接 */
$device_id = 1000000004;
$data = [];
switch ($do) {
    case 'SHOPPING':
        $data = doShopping();
        break;
    case 'BOOKED':
        break;
    case 'INVENTORY':
        $data = doInventory();
        break;
    case 'REFRESH':
        $data = doRefresh();
        break;
    case 'STATUS':
        $data = doStatus();
        break;
    case 'CLOSE':
        $data = doClose();
        break;
}

$rst = lib\Cache::getInstance()->publish($ch, json_encode($data));
var_dump($rst);
var_dump($ch);

function doShopping()
{
    $orderId = time();
    $doorID = lib\DB::getInstance()->insert('wl_device_door_logs', [
        'login_id' => 9,
        'status' => 0,
        'action' => 'shopping',
        'device_id' => 1000000004,
        'open_time' => date("Y-m-d H:i:s")
    ], true);
    
    lib\DB::getInstance()->insert('wl_device_orders', [
        'device_door_log_id' => $doorID,
        'order_id' => 1000000004,
        'created_time' => date("Y-m-d H:i:s")
    ], true);
    // {\"command\":\"SHOPPING\",\"data\":{\"device_id\":\"1000000004\",\"transaction_number\":1000000104}}"
    
    return [
        'command' => 'SHOPPING',
        'data' => [
            'device_id' => 1000000004,
            'transaction_number' => $doorID
        ]
    ];
}

function doInventory()
{
   $inventoryId = lib\DB::getInstance()->insert('wl_device_inventory_logs',[
       'device_id'=>1000000004,
       'created_time'=>date("Y-m-d H:i:s"),
    ],true);

    return [
        'command' => 'INVENTORY',
        'data' => [
            'device_id' => 1000000004,
            'transaction_number' => $inventoryId
        ]
    ];
   
}

 function doRefresh()
{
    $refreshId =lib\DB::getInstance()->insert('wl_device_refresh_logs',[
       'device_id'=>1000000004,
       'created_time'=>date("Y-m-d H:i:s"),
    ],true);


    return [
        'command' => 'REFRESH',
        'data' => [
            'device_id' => 1000000004,
            'transaction_number' => $refreshId
        ]
    ];
   

}

function doStatus()
{
    return [
        'command' => 'STATUS',
        'data' => []
    ];
}
function doClose()
{
    return [
        'command' => 'CLOSE',
        'data' => ['device_id'=>1000000005]
    ];
}
