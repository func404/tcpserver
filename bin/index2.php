<?php
include '../lib/DB.php';
include '../config/Config.php';
include '../lib/Cache.php';
$db = lib\DB::getInstance();
$cache = lib\Cache::getInstance();
$ch = config\Config::broadcastChannels['client'];
$commands = config\Config::orderMap;
$d=trim($_GET['d']);
$c=array_keys($commands);
    $j = 1;
echo "<pre>";
echo "<form>";
echo "<select name='d'>'";

echo "<option>-------请选择操作--------</option>";
foreach($commands as $key => $value){
echo "<option value={$j}>{$value}</option>";
$j++;
}
echo "</select>";
echo "<input type='submit' value='确定'/>";
echo "</form>";
echo "<br/>";
    echo  "Command: \n";
    $i = 1;
    $tmp = [];
    foreach ($commands as $key => $value) {
        echo "$i ): $key,$value\n";
        $tmp[$i] = $key;
        $i ++;
    }
   $do=$c[$d-1];

echo "\n\n当前命令：",$do,"\n";
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
        break;
    case 'STATUS':
        $data = doStatus();
        break;
    case 'CLOSE':
        break;
}

if(lib\Cache::getInstance()->publish($ch, json_encode($data))){
    echo "操作成功";
}else{
    echo "操作失败";
}

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
