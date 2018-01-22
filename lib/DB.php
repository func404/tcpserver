<?php
namespace lib;

use config\Config;

class DB
{

    private static $instance;

    private $datetime;

    private static $link;

    public function __construct()
    {
        self::$link = mysqli_connect(Config::db['host'], Config::db['user'], Config::db['pass'], Config::db['libr'], Config::db['port']);
        mysqli_query(self::$link, 'set names ' . Config::db['charset']);
        $this->datetime = date("Y-m-d H:i:s");
    }

    public static function getInstance()
    {
        if (! (self::$instance instanceof self) or ! self::$link or ! self::$link->get_connection_stats() or ! self::$link->ping()) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function checkDeivceKey($deviceNo, $device)
    {
        return true;
    }

    public function getDeviceByNo($deviceNo)
    {
        return $this->fetchRow("select * from `wl_devices` where device_number = '" . $deviceNo . "'");
    }

    /**
     *
     * @param array $data
     *            [
     *            'device_id' => 100000001,
     *            'device_number' => 'acd2323aaaad223232232334',
     *            'login_ip' => CLIENT['remote_ip'],
     *            'tags' => 0,
     *            'weight' =>0,
     *            'connect_time' => date("Y-m-d H:i:s", CLIENT['connect_time']),
     *            'login_time' => $this->datetime
     *            ];
     */
    public function createLoginLog($data = [])
    {
        return $this->insert('wl_device_login_logs', $data, true);
    }

    public function query($sql)
    {
        // return mysqli_query(self::$link, $sql) or (error_log(mysqli_error()."\n",3,'/tmp/mysql_error'));
        return self::$link->query($sql); // or (error_log(mysqli_error()."\n",3,'/tmp/mysql_error'));
    }

    public function getInsertId()
    {
        return mysqli_insert_id(self::$link);
    }

    public function fetchOne($sql)
    {
        $collect = $this->query($sql);
        if ($collect) {
            $rst = mysqli_fetch_array($collect);
            if ($rst) {
                mysqli_free_result($collect);
                return $rst[0];
            }
        } else {
            return false;
        }
    }

    public function fetchRow($sql, $type = "assoc")
    {
        $collect = $this->query($sql);
        if (! in_array($type, array(
            "assoc",
            'array',
            "row"
        ))) {
            die("mysqli_query error");
        }
        $funcname = "mysqli_fetch_" . $type;
        return $funcname($collect);
    }

    public function fetchAll($sql, $type = "assoc")
    {
        $collect = $this->query($sql);
        
        if (! $collect) {
            return false;
        }
        if (! in_array($type, array(
            "assoc",
            'array',
            "row"
        ))) {
            return false;
        }
        $funcname = "mysqli_fetch_" . $type;
        
        $rst = [];
        
        while ($row = $funcname($collect)) {
            $rst[] = $row;
        }
        
        return $rst;
    }

    /**
     * 数据插入
     *
     * @param array $fields
     *            插入的字段和值
     * @return state
     */
    public function insert($table, $fields, $insertid = false, $onDuplicate = null)
    {
        $columns = '';
        $columns1 = '';
        $values = '';
        if (is_array($fields)) {
            foreach ($fields as $k => $v) {
                $columns .= '`' . $k . '`,';
                $values .= '\'' . $v . '\',';
            }
            $columns = substr($columns, 0, - 1);
            $values = substr($values, 0, - 1);
        } else {
            return false;
        }
        $query = 'INSERT INTO ' . $table . '(' . $columns . ' ) VALUES ( ' . $values . ' ) ';
        if (is_array($onDuplicate)) {
            foreach ($onDuplicate as $k1 => $v1) {
                $columns1 .= '`' . $k1 . '`=\'' . $v1 . '\',';
            }
            $onDuplicate = substr($columns1, 0, - 1);
        }
        if (! empty($onDuplicate)) {
            $query .= 'ON DUPLICATE KEY UPDATE ' . $onDuplicate;
        }
        $result = $this->query($query);
        if ($insertid) {
            return $this->getInsertId();
        }
        return $result;
    }

    public function deleteOne($table, $where)
    {
        if (is_array($where)) {
            foreach ($where as $key => $val) {
                $condition = $key . '=' . $val;
            }
        } else {
            $condition = $where;
        }
        $sql = "delete from $table where $condition";
        $this->query($sql);
        // 返回受影响的行数
        return mysqli_affected_rows(self::$link);
    }

    public function deleteAll($table, $where)
    {
        if (is_array($where)) {
            foreach ($where as $key => $val) {
                if (is_array($val)) {
                    $condition = $key . ' in (' . implode(',', $val) . ')';
                } else {
                    $condition = $key . '=' . $val;
                }
            }
        } else {
            $condition = $where;
        }
        $sql = "delete from $table where $condition";
        $this->query($sql);
        // 返回受影响的行数
        return mysqli_affected_rows(self::$link);
    }

    /**
     * 数据更新操作
     *
     * @param mix $fields
     *            更新字段，可以为数组
     * @param mix $where
     *            更新条件，可以为数组
     * @return int 受影响的行数
     */
    public function update($table, $fields, $where)
    {
        $condition = '';
        $clums = '';
        if (is_array($fields)) {
            foreach ($fields as $k => $v) {
                $clums .= $k . '=\'' . $v . '\',';
            }
            $fields = substr($clums, 0, - 1);
        }
        
        if (is_array($where)) {
            foreach ($where as $k => $v) {
                $condition .= $k . '=\'' . $v . '\' and ';
            }
            $where = substr($condition, 0, - 4);
        }
        $sql = 'UPDATE ' . $table . ' SET ' . $fields . ' WHERE ' . $where;
        $this->query($sql);
        // 返回受影响的行数
        return mysqli_affected_rows(self::$link);
    }
}
