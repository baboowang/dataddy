<?php
namespace GG\Db;

use \GG\Config as Config;

class MongoDb {

    protected static $_instances;

    private  $_dbRead;
    private  $_dbWrite;

    private $_dbName;
    private $_readServers;
    private $_writeServers;

    protected $_readOnMaster = TRUE;

    public $errorHandler = NULL;

    protected function __construct($dbName, $readServers, $writeServers)
    {
        $this->_dbName      = $dbName;
        $this->_readServers = $readServers;
        $this->_writeServers = $writeServers;

        if (empty($this->_readServers)) {
            $this->_readOnMaster = TRUE;
        }
    }

    public function executeReadCommand($command, $db_name = NULL)
    {
        try {
            if ($this->_readOnMaster) {
                $db = &$this->getDbWrite();
            } else {
                $db = &$this->getDbRead();
            }

            if (!$db) {
                return FALSE;
            }

            //$this->_log("Execute Sql: $sql", LOG_DEBUG);

            $startTime = microtime(TRUE);
            $cursor = $db->executeReadCommand($db_name ?: $this->_dbName, $command);
            $cursor->setTypeMap(['root' => 'array', 'document' => 'array', 'array' => 'array']);
            $res = $cursor->toArray();
            $useTime = microtime(TRUE) - $startTime;

            $this->_time('executeReadCommand', $useTime, $command);

            return $res;
        } catch (\Exception $e) {
            $this->_error('DB error:'.$e, $e);

            return FALSE;
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
            //    $this->_log("[$type] use {$useTime}s:{$message}", LOG_ERR);
            }
    }

    public function & getDbWrite()
    {
        if ( ! $this->_dbWrite) {
            $this->_dbWrite =
                $this->getDb($this->_writeServers);
        }

        if ($this->_dbWrite) {
            return $this->_dbWrite;
        }


        return FALSE;
    }

    public function & getDbRead()
    {
        if ( ! $this->_dbRead) {
            $this->_dbRead =
                $this->getDb($this->_readServers);
        }

        if ($this->_dbRead) {
            return $this->_dbRead;
        }

        //使用写库
        $this->_dbRead = $this->getDbWrite();

        return $this->_dbRead;
    }

    private function getDb($servers)
    {
        $dbName = $this->_dbName;
        $dsn = $servers;

        $manager = NULL;
        try {
            $manager = new \MongoDB\Driver\Manager($dsn);
        } catch (\Exception $e) {
            $this->_error('DB error:'.$e, $e);
        }
        return $manager;
    }

    public function getDbName()
    {
        return $this->_dbName;
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

    /**
     * 获取global db 对象
     *
     * @param integer $clusterId
     * @param boolean $singleton 是否使用单例模式
     * @return object Db_GlobalDb
     */
    public static function & getInstance($clusterId)
    {
        $dbGlobals = Config::get('db_singles');

        if (empty($dbGlobals[$clusterId])) {
            return FALSE;
        }

        $dbName = $dbGlobals[$clusterId]['db_name'];
        if (empty(self::$_instances[$clusterId])) {
            $phyConfig = Config::get('db_physical');
            $config = $phyConfig[$dbGlobals[$clusterId]['map']];
            if (!isset($config['read'])) {
                $config['read'] = $config['write'];
            }
            $db = new self(
                $dbName,
                $config['read'],
                $config['write']
            );
            self::$_instances[$clusterId] = $db;
        }

        return self::$_instances[$clusterId];
    }
}
/* End of file <`2:filename`>.php */
