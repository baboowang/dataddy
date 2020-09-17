<?php
namespace GG\Db\Model;
use GG\Config;

class MongoBase
{
    protected static $_forceReadOnMater = FALSE;

    protected $_dbName = NULL;
    protected $_table = NULL;
    protected $_dbClusterId = NULL;
    protected $_readOnMaster = FALSE;
    //Used with farm db
    protected $_objectId = NULL;

    private $_dbInstance = NULL;

    /**
     * 构造器
     *
     * @param string $table default NULL, 表名，为NULL则不能使用基类提供的数据库操作方法
     * @param string $clusterId default NULL, 数据库cluster id
     */
    function __construct ($table = NULL, $clusterId = NULL)
    {
        $tokens = explode('.', $table);
        $this->_table = array_pop($tokens);
        if ($tokens) {
            $this->_dbName = $tokens[0];
        } else {
            $cfg = Config::get("db_singles.$clusterId");
            $this->_dbName = $cfg['db_name'];
        }
        $this->_dbClusterId = $clusterId;
    }

    public static function getInstance()
    {
        static $instance = NULL;

        if (is_null($instance)) {
            $instance = new static();
        }

        return $instance;
    }

    //设置所有的Model都强制读写主库
    public static function setForceReadOnMater ($bool = TRUE)
    {
        self::$_forceReadOnMater = $bool;
    }


    public function log($message, $level)
    {
        if (function_exists('log_message')) {
            log_message($message, $level);
        }
    }

    public function transFilter(Array $filter)
    {
        $this->_filter = $filter;
        if(isset($filter['_id'])) {
            $filter['_id'] = new \MongoDB\BSON\ObjectId($filter['_id']);
        }
        $map = [
            '>' => '$gt',
            '>=' => '$gte',
            '<'  => '$lt',
            '<=' => '$lte',
            '!=' => '$ne',
            '=' => '$eq'
        ];

        $_filter = [];
        foreach($filter as $field => &$item) {
            // field  create_at|date
            $exp = explode('|', $field);
            $format = $exp[1] ?? false;
            $formater = function($format, $val) {
                switch($format) {
                    case 'timestamp':
                        return new  \MongoDB\BSON\Timestamp(0, $val);
                        break;
                    case 'date':
                        return new  \MongoDB\BSON\UTCDateTime(strtotime($val) * 1000);
                        break;
                    case 'regex':
                        return new \MongoDB\BSON\Regex($val);
                        break;
                    default:
                        return $val;
                }
            };

            if(!is_array($item) && $format) {
                $_filter[$exp[0]] = $formater($format, $item);
                unset($filter[$field]);
                continue;
            }

            if(is_real_array($item)) {
                $item = ['$in' => $item];
                continue;
            }

            if(is_array($item) && !is_real_array($item)) {
                $tmp_item = [];
                foreach($item as $operator => $val) {
                    $tmp_item[($map[$operator] ?? false) ? $map[$operator] : $operator] = $format ? $formater($format, $val) : $val;
                }
                $item = $tmp_item;
                if($format) {
                    $real_field = $exp[0];
                    $_filter[$real_field] = $tmp_item;
                    unset($filter[$field]);
                }
            }

            unset($field, $item);
        }
        $filter = array_merge($filter, $_filter);

        return $filter;
    }

    function transOptions(Array $options)
    {
        $default = [
        ];
        $options += $default;
        $select = array_filter(array_map('trim', explode(',', $options['select'] ?? '')));
        unset($options['select']);
        foreach($select as $field) {
            $options['projection'][$field] = 1;
        }
        $order_by = array_values(array_filter(preg_split('@[ ,]@', $options['order_by'] ?? '')));
        unset($options['order_by']);
        $sort_case = [
            'desc' => -1,
            'asc' => 1,
        ];
        foreach($order_by as $index => $item) {
            if($index % 2 == 0) {
                $options['sort'][$item] = $sort_case[strtolower($order_by[$index + 1])] ?? 1;
            }
        }
        if(isset($options['limit'])) {
            $options['limit'] = (int)$options['limit'];
        }
        if(isset($options['offset'])) {
            $options['skip'] = (int)$options['offset'];
        }

        return $options;
    }


    public function select ($filter = array(), $options = array())
    {
        if ($this->_table === NULL) {
            return FALSE;
        }

        $options = [ 'find' => $this->_table ] + $options;

        $filter = $this->transFilter($filter);

        if ($filter) {
            $options['filter'] = $filter;
        }
        $options = $this->transOptions($options);

        $list = $this->executeReadCommand($options) ?: [];
        $list = array_map(function($item){$item['_id'] = (string)$item['_id']; return $item;}, $list);
        return $list;
    }

    public function selectOne ($filter = array(), $options = array())
    {
        $options['limit'] = 1;
        $res = $this->select($filter, $options);
        if ($res === FALSE) {
            return FALSE;
        }
        if (empty($res)) {
            return NULL;
        }
        return $res[0];
    }

    public function executeReadCommand($cmd_data)
    {
        $db = $this->_getDbInstance();
        if (! $db) {
            return FALSE;
        }

        $cmd = new \MongoDB\Driver\Command($cmd_data);

        return $db->executeReadCommand($cmd, $this->_dbName);
    }

    public function selectCount ($filter = array())
    {
        if ($this->_table === NULL) {
            return FALSE;
        }

        $db = $this->_getDbInstance();
        if (! $db) {
            return FALSE;
        }

        $filter = $this->transFilter($filter);

        $cmd = new \MongoDB\Driver\Command([
            'count' => $this->_table,
            'query' => $filter,
        ]);

        $res = $db->executeReadCommand($cmd, $this->_dbName);

        return $res && !empty($res[0]['ok']) ? $res[0]['n'] : FALSE;
    }

    function distinct($key, array $where)
    {
        $result = [];

        $where = $this->transFilter($where);

        $cmdArr = [
            'distinct' => $this->_table,
            'key' => $key,
            'query' => $where
        ];

        $db = $this->_getDbInstance();

        $cmd = new \MongoDB\Driver\Command($cmdArr);

        $arr = $db->executereadcommand($cmd, $this->_dbName);
        return $arr;
        if (!empty($arr)) {
            $result = $arr[0]->values;
        }
        return $result;
    }


    /**
     * find 主键查询
     *
     * @param mixed $primaryKeys 单个值或是数组值
     * @param string $primaryKeyName
     * @access public
     * @return mixed 主键传入单个值时，返回单行记录，多行值返回以主键值为Key的关联数组
     */
    public function find($primaryKeys, $primaryKeyName = 'id')
    {
        if (empty($primaryKeys)) {
            return array();
        }

        $needArray = is_array($primaryKeys);

        if ($needArray) {
            $primaryKeys = array_unique($primaryKeys);
        }

        $rows = $this->select(array(
            $primaryKeyName => $primaryKeys,
        ));

        if ( ! $needArray) {
            return $rows ? $rows[0] : array();
        }

        $ret = array();
        foreach ($rows as $row) {
            $ret[$row[$primaryKeyName]] = $row;
        }

        return $ret;
    }

    public function setReadOnMaster ($bool = TRUE)
    {
        $this->_readOnMaster = $bool;
        if ($this->_dbInstance) {
            $this->_dbInstance->setReadOnMaster($bool);
        }
    }

    public function getTable ()
    {
        return $this->_table;
    }

    public function table ($table = NULL)
    {
        if (empty($table)) {
            return $this->_table;
        }
        $this->_table = $table;
    }

    public function getDatabaseName()
    {
        $db = $this->_getDbInstance();
        if ($db) {
            return $db->getDbName();
        }

        return NULL;
    }

    protected function _getDbInstance ()
    {
        if ($this->_dbInstance) {
            return $this->_dbInstance;
        }

        if ($this->_dbClusterId !== NULL) {
            $this->_dbInstance = \GG\Db\MongoDb::getInstance($this->_dbClusterId);
            $this->_dbInstance->setReadOnMaster(self::$_forceReadOnMater || $this->_readOnMaster);
            return $this->_dbInstance;
        }

        return NULL;
    }

    public function __destruct ()
    {
        if ($this->_dbInstance) {
            $this->_dbInstance->close();
            $this->_dbInstance = NULL;
        }
    }
}
/* End of file <`2:filename`>.php */
