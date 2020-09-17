<?php
namespace GG\Db;

Class Redis
{
    public static $PERSISTENT_CONNECT = TRUE;
    public static $TIMEOUT = 5;

    protected $_client = NULL;

    protected static $_instances = array();

    private function __construct()
    {
        $this->_client = new \Redis();
    }

    public function __call ($name, $arguments)
    {
        if ( ! $this->_client) {
            log_message('Redis client invalid!', LOG_ERR);
            return FALSE;
        }

        if ( ! method_exists($this->_client, $name)) {
            trigger_error("The method \"$name\" not exists for Redis object.", E_USER_ERROR);
            return FALSE;
        }

        if (empty($arguments)) {
            $arguments = array();
        }

        try {
            $start_time = microtime(TRUE);

            $ret = call_user_func_array(array($this->_client, $name), $arguments);

            $use_time = microtime(TRUE) - $start_time;

            #
        } catch (Exception $e) {
            $ret = FALSE;

            log_message("Redis exception:" . $e, LOG_ERR);
        }

        if ($ret === FALSE && in_array($name, array('open', 'connect', 'popen', 'pconnect'))) {

			log_message("REDIS connect error:{$arguments[0]}:{$arguments[1]}", LOG_ERR);
        }

        return $ret;
    }

    public function __destruct()
    {
        if ($this->_client) {
            @$this->_client->close();
        }
    }

    public static function getInstance($clusterId = 'default')
    {
        $config = \GG\Config::get("redis_single.{$clusterId}");

        if (empty($config)) {
            trigger_error("Config error:no redis cluster config $clusterId", E_USER_ERROR);
            return NULL;
        }

        list($map, $db) = explode('.', $config);

        if (isset(self::$_instances[$clusterId])) {
            $client = self::$_instances[$clusterId];
            $client->select($db);
            return $client;
        }

        $physicalConfig = \GG\Config::get("redis_physical.{$map}");
        if (empty($physicalConfig)) {
            trigger_error("Config error:no redis physical config $map", E_USER_ERROR);
            return NULL;
        }

        $host = $physicalConfig['host'];
        $port = $physicalConfig['port'];
        $pswd = $physicalConfig['pswd'] ?? '';

        $client = new self();

        $connectRet = TRUE;
        if (self::$PERSISTENT_CONNECT) {
            $connectRet = $client->pconnect($host, $port, self::$TIMEOUT);
        } else {
            $connectRet = $client->connect($host, $port, self::$TIMEOUT);
        }

        if ( ! $connectRet) {
            return NULL;
            //throw error?
        }

        if ($pswd) {
           $client->auth($pswd);
        }

        self::$_instances[$clusterId] = $client;

        $client->select($db);

        return self::$_instances[$clusterId];
    }
}
