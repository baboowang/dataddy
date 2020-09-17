<?php
namespace GG\Db;

use \PDO;

class Base
{
    private  $_dbRead;
    private  $_dbWrite;

    private $_dbName;
    private $_dbUser;
    private $_dbPwd;
    private $_readServers;
    private $_writeServers;
    private $_res;

    protected $_readOnMaster = FALSE;

    public $errorHandler = NULL;

    const ERR       = -1;
    const FETCH_ALL = 0;
    const FETCH_ONE = 1;

    const SERVICE_STATUS_CACHE_TIME = 10;

    protected function __construct($dbName, $dbUser, $dbPwd, $readServers, $writeServers)
    {
        $this->_dbName      = $dbName;
        $this->_dbUser      = $dbUser;
        $this->_dbPwd       = $dbPwd;
        $this->_readServers = $readServers;
        $this->_writeServers = $writeServers;

        if (empty($this->_readServers)) {
            $this->_readOnMaster = TRUE;
        }
    }

    public function setReadOnMaster($operate = TRUE)
    {
        $this->_readOnMaster = $operate;
    }

    protected function _log($message, $level)
    {
        if (function_exists('log_message')) {
            log_message($message, $level);
        }
    }

    protected function _error($message, $excpetion = NULL)
    {
        if (function_exists('log_message')) {
            log_message("error_report:$message", LOG_ERR);
        }

        if (function_exists('error_report')) {
            error_report($message);
        }

        if ($this->errorHandler && is_callable($this->errorHandler)) {
            call_user_func($this->errorHandler, $message, $excpetion);
        }
    }

    protected function _time($type, $useTime, $message)
    {
        if ($useTime > 1) {
            $this->_log("[$type] use {$useTime}s:{$message}", LOG_ERR);
        }
    }

    protected function _cacheSet($key, $val, $expireTime)
    {
        if (function_exists('xcache_set')) {

            return xcache_set($key, $val, $expireTime);
        }

        return FALSE;
    }

    protected function _cacheGet($key)
    {
        if (function_exists('xcache_get')) {
            return xcache_get($key);
        }

        return FALSE;
    }

    public function query($sql)
    {
        try {
            if ($this->_readOnMaster) {
                $db = &$this->getDbWrite();
            } else {
                $db = &$this->getDbRead();
            }

            $this->_log("Execute Sql: $sql", LOG_DEBUG);

            $startTime = microtime(TRUE);

            $this->_res = $db->query($sql);

            $useTime = microtime(TRUE) - $startTime;

            $this->_time('query', $useTime, $sql);

            if ($this->_res === FALSE) {
                //Log error here
                $this->_log('DB error:'.$sql, LOG_EMERG);

                return FALSE;
            }
        } catch (\Exception $e) {
            $this->_error('DB error:'.$e, $e);

            return FALSE;
        }
    }

    /**
     * 获取SQL查询结果
     *
     * @param string  $sql
     * @param array   $res Out parameter, array to be filled with fetched results
     * @param integer $fetchStyle 获取命名列或是数字索引列，默认为命令列
     * @param integer $fetchMode  获取全部或是一行，默认获取全部
     * @return boolean|integer false on failure, else return count of fetched rows
     */
    public function select($sql, &$res, $fetchStyle = PDO::FETCH_NAMED, $fetchMode = self::FETCH_ALL)
    {
        try {
            if ($this->_readOnMaster) {
                $db = &$this->getDbWrite();
            } else {
                $db = &$this->getDbRead();
            }

            $this->_log("Execute Sql: $sql", LOG_DEBUG);

            $startTime = microtime(TRUE);

            $this->_res = $db->query($sql);

            $useTime = microtime(TRUE) - $startTime;

            $this->_time('select', $useTime, $sql);

            if ($this->_res === FALSE) {
                //Log error here
                $this->_log('DB error:'.$sql, LOG_EMERG);

                return FALSE;
            }

            if ($fetchMode === self::FETCH_ALL) {
                $res = $this->_res->fetchAll($fetchStyle);
                return count($res);
            } else if ($fetchMode === self::FETCH_ONE) {
                $res = $this->_res->fetch($fetchStyle);
                $this->_res->closeCursor();
                return $res ? 1 : 0;
            } else {
                return FALSE;
            }
        } catch (\Exception $e) {
            $this->_error('DB error:'.$e, $e);

            return FALSE;
        }
    }

    /**
     *  获取查询结果下一行
     *
     *  @param array $res Out parameter, array to be filled with fetched results
     *  @param integer $fetchStyle same as select method
     *  @return boolean false on failure, true on success
     */
    public function fetchNext(&$res, $fetchStyle = PDO::FETCH_NAMED)
    {
        if (!empty($this->_res)) {
            try {
                $res = $this->_res->fetch($fetchStyle);
            } catch (\Exception $e) {
                $this->_error('DB query error:'.$e, $e);

                return FALSE;
            }
            return TRUE;
        }

        return FALSE;
    }

    /**
     * update/delete/insert/replace sql use this method
     *
     * @param string $sql sql语句
     * @param string $mode if is 'a', return affected rows count, else return boolean
     * @return boolean|integer
     */
    public function mod($sql, $mode = '')
    {
        try {
            $db  = &$this->getDbWrite();

            $this->_log("Execute Sql: $sql", LOG_DEBUG);

            $startTime = microtime(TRUE);

            $res =  $db->exec($sql);

            $useTime = microtime(TRUE) - $startTime;

            $this->_time('mod', $useTime, $sql);

            if ($res === FALSE) {
                $this->_log('DB mod error:'.$sql, LOG_EMERG);

                return FALSE;
            }

        } catch (\Exception $e) {
            $this->_error('DB mod error:'.$e, $e);

            return FALSE;
        }

        if ($mode == 'a') {
            return $res;
        }

        return TRUE;
    }

    public function & getDbWrite()
    {
        $badServerHosts = array();
        if ( ! $this->_dbWrite) {
            $this->_dbWrite =
                $this->_selectDB($this->_writeServers, $badServerHosts);
        }

        if ($this->_dbWrite) {
            return $this->_dbWrite;
        }

        //Log error here
        $this->_error(
            'DB Write:Connect to host(s) failed:' . implode(',', $badServerHosts)
        );

        return FALSE;
    }

    public function & getDbRead()
    {
        $badServerHosts = array();
        if ( ! $this->_dbRead) {
            $this->_dbRead =
                $this->_selectDB($this->_readServers, $badServerHosts);
        }

        if ($this->_dbRead) {
            return $this->_dbRead;
        }

        //Log error here
        $this->_error(
            'DB Read:Connect to host(s) failed:' . implode(',', $badServerHosts)
        );

        //使用写库
        $this->_dbRead = $this->getDbWrite();

        return $this->_dbRead;
    }

    private function _selectDB($servers, &$badServerHosts = array())
    {
        //Check if it's indexed array
        if ( ! isset($servers[0])) {
            $servers = array($servers);
        }

        $activeServers = array();
        $badServerHosts = array();

        foreach ($servers as &$server) {
            if ( ! isset($server['weight'])) {
                $server['weight'] = 1;
            }
            if ($this->_isServerOk($server)) {
                $activeServers[] = $server;
            } else {
                $this->_log('DB Cluster:Bad status:' . $server['host'], LOG_ERR);
            }
        }
        unset($server);

        if (empty($activeServers)) {
            //所有服务器的状态都为不可用时，则尝试连接所有
            $activeServers = $servers;
        }

        $weights = 0;
        foreach ($activeServers as $server) {
            $weights += $server['weight'];
        }

        $dbName = $this->_dbName;
        while ($activeServers) {
            $ratio = rand(1, $weights);
            $weightLine = 0;
            $selectIndex = -1;
            foreach ($activeServers as $index => $server) {
                $weightLine += $server['weight'];
                if ($ratio <= $weightLine) {
                    $selectIndex = $index;
                    break;
                }
            }
            if ($selectIndex == -1) {
                //主机都不可用，使用weight = 0的备机
                $selectIndex = array_rand($activeServers);
            }
            $server = $activeServers[$selectIndex];
            unset($activeServers[$selectIndex]);
            $this->_log("DB CLUSTER: Choose server {$server['host']}:{$server['port']}.", LOG_DEBUG);

            $sqltype = isset($server['type']) ? $server['type'] : 'mysql';
            $dsn = "{$sqltype}:host={$server['host']};port={$server['port']};dbname={$dbName}";
            $pdo = NULL;
            try {
                $startTime = microtime(TRUE);
                $options = array(
                    PDO::ATTR_EMULATE_PREPARES => TRUE,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8MB4\'',
                    PDO::ATTR_TIMEOUT => 10,
                );
                if ($sqltype == 'mysql') {
                    $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = TRUE;
                }
                $pdo = new PDO($dsn, $this->_dbUser, $this->_dbPwd, $options);
                $useTime = microtime(TRUE) - $startTime;
                $this->_time('connect', $useTime, $server['host'].'.'.$dbName);
            } catch (\Exception $e) {
                $this->_error('DB error:'.$e, $e);
                $pdo = NULL;
            }

            $this->_setServerStatus($server, !! $pdo);
            if ($pdo) {
                return $pdo;
            } else {
                $badServerHosts[] = $server['host'];
                $weights -= $server['weight'];
            }
        }

        return NULL;
    }

    protected function _isServerOk($server)
    {
        $key = "server_status_{$server['host']}_{$server['port']}";
        $status = $this->_cacheGet($key);
        if (is_numeric($status)) {
            return ! empty($status);
        }

        return TRUE;
    }

    protected function _setServerStatus($server, $status)
    {
        $key = "server_status_{$server['host']}_{$server['port']}";
        $status = $status ? '1' : '0';
        $cacheTime = 60; //1 min

        if ($this->_cacheGet($key) === $status) {

            return;
        }

        $this->_cacheSet($key, $status, self::SERVICE_STATUS_CACHE_TIME);
    }

    public function getDbName()
    {
        return $this->_dbName;
    }

    /**
     * 获取上次insert操作时得到的自增id
     *
     * @return integer
     */
    public function getLastId()
    {
        if ($this->_dbWrite) {
            return $this->_dbWrite->lastInsertId();
        }
        return 0;
    }

    /**
     * 获取sql读取错误信息
     *
     * @return string
     */
    public function getReadErrorInfo()
    {
        if (!$this->_readOnMaster) {
            $db = $this->_dbRead;
        } else {
            $db = $this->_dbWrite;
        }

        if (!empty($db)) {
            $err = $db->errorInfo();
            return $err[2];
        }

        return "Db Reader Not initiated\n";
    }

    /**
     * 获取sql写入错误信息
     *
     * @return string
     */
    public function getWriteErrorInfo()
    {
        if (!empty($this->_dbWrite)) {
            $err = $this->_dbWrite->errorInfo();
            return $err[2];
        }

        return "DB Writer not initiated\n";
    }

    /**
     * 判断上次错误是否由于重复key引起
     *
     * @return boolean
     */
    public function isDuplicate()
    {
        if (!empty($this->_dbWrite)) {
            $err = $this->_dbWrite->errorInfo();
            return $err[1] == 1062;
        }

        return FALSE;
    }

    public function close()
    {
        if ($this->_dbWrite) {
            $this->_dbWrite = NULL;
        }
        if ($this->_dbRead) {
            $this->_dbRead = NULL;
        }
    }
}
/* End of file <`2:filename`>.php */
