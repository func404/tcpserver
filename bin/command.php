<?php
namespace tcp;

include '../tcp/DB.php';
include '../tcp/Config.php';
include '../tcp/Cache.php';
$db = DB::getInstance();
$cache = Cache::getInstance();
$ch = Config::broadcastChannels['client'];
$commands = Config::orderMap;
if ($argc < 2) {
    fwrite(STDOUT, "Please choose command: \n");
    $i = 1;
    $tmp = [];
    foreach ($commands as $key => $value) {
        fwrite(STDOUT, "$i ): $key,$value\n");
        $tmp[$i] = $key;
        $i++;
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
$device_id = 1000000002;
$data=[];
switch ($do) {
    case 'SHOPPING':
        $data = doShopping();
        break;
    case 'BOOKED':
        break;
    case 'INVENTORY':
        break;
    case 'REFRESH':
        break;
    case 'STATUS':
        $data = doStatus();
        break;
    case 'CLOSE':
        break;
}
print_r($data);
function doShopping()
{
    $orderId = time();
$doorID=    DB::getInstance()->insert('wl_device_door_logs', [
        'login_id' => 9,
        'status'=>0,
        'action'=>'shopping',
        'device_id' => 1000000002,
        'open_time' => date("Y-m-d H:i:s")
    ], true);
    
    DB::getInstance()->insert('wl_device_orders', [
        'device_door_log_id' => $doorID,
        'order_id' => 1000000002,
        'created_time' => date("Y-m-d H:i:s")
    ], true);
    // {\"command\":\"SHOPPING\",\"data\":{\"device_id\":\"1000000002\",\"transaction_number\":1000000104}}"
    
    return [
        'command' => 'SHOPPING',
        'data' => [
            'device_id' => 1000000002,
            'transaction_number' => $doorID
        ]
    ];
}
function doStatus()
{
  
    return [
        'command' => 'STATUS',
        'data' => [
            'device_id' => 1000000002,
            'transaction_number' => $doorID
        ]
    ];
}
