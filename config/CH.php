<?php
namespace config;

use lib\Cache;

/**
 * 通知渠道
 *
 * @author duxin
 *        
 */
final class CH
{

    const SHOPPING = 'WLXS_SHOPPING';

    const STORE = 'WLXS_STORE';

    const INVERTORY = 'WLXS_INVENTORY';

    const REFRESH = 'WLXS_REFRESH';

    const STATUS = 'WLXS_STATUS';

    const CLOSE = 'WLXS_CLOSE';

    public static function pub($ch, $message)
    {
        return Cache::getInstance()->publish($ch, $message);
    }

    public static function pubShopping($transaction_number)
    {
        $ch = self::SHOPPING;
        return self::pub($ch, $transaction_number);
    }

    public static function pubStore($transaction_number)
    {
        $ch = self::STORE;
        return self::pub($ch, $transaction_number);
    }

    public static function pubInventory($transaction_number)
    {
        $ch = self::INVERTORY;
        return self::pub($ch, $transaction_number);
    }

    public static function pubRefresh($transaction_number)
    {
        $ch = self::REFRESH;
        return self::pub($ch, $transaction_number);
    }

    public static function pubStatus($transaction_number)
    {
        $ch = self::STATUS;
        return self::pub($ch, $transaction_number);
    }

    public static function pubClose($transaction_number)
    {
        $ch = self::CLOSE;
        return self::pub($ch, $transaction_number);
    }
}

?>