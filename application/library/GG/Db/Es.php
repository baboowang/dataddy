<?php
namespace GG\Db;

use \GG\Config as Config;
use \Elasticsearch\ClientBuilder;

class Es {

    protected static $_instances;

    private $_services;
    private $_client = NULL;

    public $errorHandler = NULL;

    protected function __construct($services)
    {
        $this->_services = $services;
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

    public function select($sql, &$rows)
    {
        $client = $this->getDb();

        $t = $client->transport;
        $promise =  $t->performRequest(
            'POST', #$endpoint->getMethod(),
            '/_xpack/sql', #$endpoint->getURI(),
             [ 'format' => 'json'  ],#$endpoint->getParams(),
             [ 'query' => $sql ], #$endpoint->getBody(),
            [] #$endpoint->getOptions()
        );

        try {
            $res = $t->resultOrFuture($promise, []);
        } catch (\Exception $e) {
            $this->_error('query error', $e);
            return FALSE;
        }

        if (empty($res) || empty($res['rows'])) {
            return 0;
        }

        $rows = [];
        foreach ($res['rows'] as $row) {
            $new_row = [];
            foreach ($row as $i => $v) {
                $new_row[$res['columns'][$i]['name']] = $v;
            }
            $rows[] = $new_row;
        }

        return count($rows);
    }

    private function getDb()
    {
        if ($this->_client) {
            return $this->_client;
        }

        $services = $this->_services;
        if (!is_array($services) || !isset($services[0])) {
            $services = [ $services ];
        }
        foreach ($services as &$service) {
            if (isset($service['password']) && !isset($service['pass'])) {
                $service['pass'] = $service['password'];
                unset($sevice['password']);
            }
            unset($service);
        }
        $this->_client = ClientBuilder::create()->setHosts($services)->build();

        return $this->_client;
    }

    public function getDbName()
    {
        return '';
    }

    public function close()
    {
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

        if (empty(self::$_instances[$clusterId])) {
            $phyConfig = Config::get('db_physical');
            $config = $phyConfig[$dbGlobals[$clusterId]['map']];
            $db = new self(
                $config['write']
            );
            self::$_instances[$clusterId] = $db;
        }

        return self::$_instances[$clusterId];
    }
}
/* End of file <`2:filename`>.php */
