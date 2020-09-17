<?php
namespace GG\Db\Model;

use GG\Db\Sql;
use GG\Db\GlobalDb;
use GG\Db\FarmDb;

class Base
{
    protected static $_forceReadOnMater = FALSE;

    protected $_table = NULL;
    protected $_dbClusterId = NULL;
    protected $_readOnMaster = FALSE;
    //Used with farm db
    protected $_objectId = NULL;

    protected $_eventHandlers = array();

    private $_dbInstance = NULL;
    protected $_sqlHelper = NULL;
    private $_lastSql;

    /**
     * 构造器
     *
     * @param string $table default NULL, 表名，为NULL则不能使用基类提供的数据库操作方法
     * @param string $clusterId default NULL, 数据库cluster id
     * @param string $objectId default NULL, 对象id，用于分库选取用，单库不需要设置此参数
     */
    function __construct ($table = NULL, $clusterId = NULL, $objectId = NULL)
    {
        $this->_table = $table;
        $this->_dbClusterId = $clusterId;
        $this->_objectId = $objectId;
        $this->_sqlHelper = Sql::getInstance();
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


    public function getLastId()
    {
        $db = $this->_getDbInstance();
        if (! $db) {
            return FALSE;
        }

        return $db->getLastId();
    }

    public function log($message, $level)
    {
        if (function_exists('log_message')) {
            log_message($message, $level);
        }
    }

    public function insert ($insArr, $returnLastId = FALSE)
    {
        if ($this->_table === NULL) {
            return FALSE;
        }

        $db = $this->_getDbInstance();
        if (! $db) {
            return FALSE;
        }

        $this->beforeInsert($insArr);

        $sql = 'INSERT ' . $this->_table() . $this->_sqlHelper->insert($insArr);
        $ret = $db->mod($sql);
        $this->_lastSql = $sql;
        if ($ret === FALSE) {
            $this->log("[$sql] " . $db->getWriteErrorInfo(), LOG_ERR);
            return FALSE;
        }

        $lastId = isset($insArr['id']) ? $insArr['id'] : 0;
        if ($returnLastId) {
            $lastId = $db->getLastId();
            if ($lastId == 0 && isset($insArr['id'])) {
                $lastId = $insArr['id'];
            }
        }

        $this->afterInsert($insArr, $lastId);

        return $returnLastId ? $lastId : $ret;
    }

    //批量替换，在性能要求较高的情况下使用，但不支持Model_Cacheable的自动清缓存
    public function mulReplace($fields, $rowsData, $resData)
    {
        if ($this->_table === NULL) {
            return FALSE;
        }

        $db = $this->_getDbInstance();
        if (! $db) {
            return FALSE;
        }

        $sql = 'INSERT ' . $this->_table() . $this->_sqlHelper->mulReplace($fields, $rowsData, $resData);

        $ret = $db->mod($sql);
        $this->_lastSql = $sql;

        if ($ret === FALSE) {
            $this->log("[$sql] " . $db->getWriteErrorInfo(), LOG_ERR);
            return FALSE;
        }

        return $ret;
    }

    public function replace ($insArr, $replaceArr = NULL)
    {
        if ($this->_table === NULL) {
            return FALSE;
        }

        $db = $this->_getDbInstance();
        if (! $db) {
            return FALSE;
        }

        $this->beforeReplace($insArr, $replaceArr);

        $sql = 'INSERT ' . $this->_table() . $this->_sqlHelper->replace($insArr, $replaceArr);

        $ret = $db->mod($sql);
        $this->_lastSql = $sql;

        if ($ret === FALSE) {
            $this->log("[$sql] " . $db->getWriteErrorInfo(), LOG_ERR);
            return FALSE;
        }

        $this->afterReplace($insArr, $replaceArr);

        return $ret;
    }

    public function update ($where, $uptArr)
    {
        if ($this->_table === NULL) {
            return FALSE;
        }

        $db = $this->_getDbInstance();
        if (! $db) {
            return FALSE;
        }

        $this->beforeUpdate($where, $uptArr);

        $sql = 'UPDATE ' . $this->_table() . $this->_sqlHelper->update($uptArr) . $this->_sqlHelper->where($where);
        $ret = $db->mod($sql);
        $this->_lastSql = $sql;

        if ($ret === FALSE) {
            $this->log("[$sql] " . $db->getWriteErrorInfo(), LOG_ERR);
            return FALSE;
        }

        $this->afterUpdate($where, $uptArr);

        return $ret;
    }

    public function delete ($where)
    {
        if ($this->_table === NULL) {
            return FALSE;
        }

        $db = $this->_getDbInstance();
        if (! $db) {
            return FALSE;
        }

        $this->beforeDelete($where);

        $sql = 'DELETE FROM ' . $this->_table() . $this->_sqlHelper->where($where);

        $ret = $db->mod($sql);
        $this->_lastSql = $sql;

        if ($ret === FALSE) {
            $this->log("[$sql] " . $db->getWriteErrorInfo(), LOG_ERR);
            return FALSE;
        }

        $this->afterDelete($where);

        return $ret;
    }

    public function addEventHandler($handlerObj)
    {
        $class = get_class($handlerObj);
        if ( ! isset($this->_eventHandlers[$class])) {
            $this->_eventHandlers[$class] = $handlerObj;
        }
    }

    protected function beforeInsert (&$data)
    {
        foreach ($this->_eventHandlers as $handler) {
            $handler->beforeInsert($this, $data);
        }
    }

    protected function afterInsert ($data, $lastId)
    {
        foreach ($this->_eventHandlers as $handler) {
            $handler->afterInsert($this, $data, $lastId);
        }
    }

    protected function beforeUpdate (&$where, &$data)
    {
        foreach ($this->_eventHandlers as $handler) {
            $handler->beforeUpdate($this, $where, $data);
        }
    }

    protected function afterUpdate ($where, $data)
    {
        foreach ($this->_eventHandlers as $handler) {
            $handler->afterUpdate($this, $where, $data);
        }
    }

    protected function beforeReplace (&$data, &$replace)
    {
        foreach ($this->_eventHandlers as $handler) {
            $handler->beforeReplace($this, $data, $replace);
        }
    }

    protected function afterReplace ($data, $replace)
    {
        foreach ($this->_eventHandlers as $handler) {
            $handler->afterReplace($this, $data, $replace);
        }
    }

    protected function beforeDelete (&$where)
    {
        foreach ($this->_eventHandlers as $handler) {
            $handler->beforeDelete($this, $where);
        }
    }

    protected function afterDelete ($where)
    {
        foreach ($this->_eventHandlers as $handler) {
            $handler->afterDelete($this, $where);
        }
    }

    public function select ($where = array(), $attrs = array())
    {
        if ($this->_table === NULL) {
            return FALSE;
        }

        $db = $this->_getDbInstance();
        if (! $db) {
            return FALSE;
        }

        if (is_callable(array(
            $this,
            'beforeSelect'
        ), TRUE)) {
            $this->beforeSelect($where, $attrs);
        }

        $selectFields = isset($attrs['select']) ? $attrs['select'] : '*';

        $sql = "SELECT {$selectFields} FROM "
            . $this->_table()
            . (!empty($attrs['force_index']) ? " FORCE INDEX({$attrs['force_index']})" : '')
            . $this->_sqlHelper->where($where, $attrs);
        $res = NULL;
        $this->_lastSql = $sql;
        if ($db->select($sql, $res) === FALSE) {
            $this->log("[$sql] " . $db->getReadErrorInfo(), LOG_ERR);
            return FALSE;
        }

        if (is_callable(array(
            $this,
            'afterSelect'
        ), TRUE)) {
            $this->afterSelect($res);
        }

        return $res;
    }

    public function selectOne ($where = array(), $attrs = array())
    {
        $attrs['limit'] = 1;
        $attrs['offset'] = 0;

        $res = $this->select($where, $attrs);
        if ($res === FALSE) {
            return FALSE;
        }
        if (empty($res)) {
            return NULL;
        }
        return $res[0];
    }

    public function selectCount ($where = array(), $attrs = array())
    {
        if (! isset($attrs['select'])) {
            $attrs['select'] = 'COUNT(0)';
        }
        $attrs['select'] .= ' AS `total`';

        $res = $this->selectOne($where, $attrs);
        if ($res === FALSE) {
            return FALSE;
        }
        return intval($res['total']);
    }

    public function batchSelect($where, $attrs, $options, $handler)
    {
        $pk = $options['order_key'] ?? 'id';
        if (is_null($attrs)) {
            $attrs = [];
        }
        if (is_null($options)) {
            $options = [];
        }
        $batch_count = $options['batch_count'] ?? 100;
        $attrs['limit'] = $batch_count;
        $attrs['order_by'] = $pk . ' ASC';
        $handler_type = $options['handler_type'] ?? 'rows';

        $origin_pk_cond = isset($where[$pk]) ? $where[$pk] : NULL;
        $origin_table = $this->_table;

        while (TRUE) {
            $rows = $this->select($where, $attrs);

            if (empty($rows)) {
                break;
            }

            if (!isset($rows[count($rows) - 1][$pk])) {
                throw new \Exception("batchSelect找不到{$pk}字段");
            }

            $result = TRUE;
            if ($handler_type === 'row') {
                foreach ($rows as $row) {
                    $result = call_user_func($handler, $row);
                    if ($result === FALSE) {
                        break;
                    }
                }
            } else {
                $result = call_user_func($handler, $rows);
            }

            if ($result === FALSE) {
                break;
            }

            $last_pk = $rows[count($rows) - 1][$pk];
            $where[$pk] = [ '>' => $last_pk];
        }
    }

    /**
     * getListData 返回记录行、总数、及分页HTML代码的数组
     *
     * @param mixed $where
     * @param mixed $attrs
     * @param mixed $data 如果传入该参数的话，则直接将返回数据设置在此变量中
     * @access public
     * @return array()
     */
    public function getListData($where, $attrs, &$data = NULL)
    {
        $items = $this->select($where, $attrs);

        $countAttrs = array();
        if (isset($attrs['group_by'])) {
            $countAttrs['select'] = 'COUNT(DISTINCT ' . $attrs['group_by'] . ')';
        }

        $total = $this->selectCount($where, $countAttrs);

        $pageSize = $attrs['limit'];
        $pagination = Util_Pagination::getHtml($total, $pageSize);

        if ( ! is_null($data)) {
            $data['items'] = $items;
            $data['totalCount'] = $total;
            $data['pagination'] = $pagination;
            return;
        }

        return array(
            'items' => $items,
            'totalCount' => $total,
            'pagination' => $pagination,
        );
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

    /**
     * Execute sql statement:
     * For select statement, return the rows;
     * For non-select statement, return rows affected;
     * When error, return false
     *
     * @param string $sql
     */
    public function execute ($sql)
    {

        $method = @strtoupper(explode(' ', trim($sql))[0]);

        $db = $this->_getDbInstance();
        if (! $db) {
            return FALSE;
        }

        if (in_array($method, array(
            'SELECT',
            'SHOW',
            'DESC'
        ))) {
            $res = NULL;
            if ($db->select($sql, $res) === FALSE) {
                $this->log("[$sql] " . $db->getReadErrorInfo(), LOG_ERR);
                return FALSE;
            }
            return $res;
        } else {
            $ret = $db->mod($sql, 'a');
            $this->_lastSql = $sql;
            if ($ret === FALSE) {
                $this->log("[$sql] " . $db->getWriteErrorInfo(), LOG_ERR);
                return FALSE;
            }
            return $ret;
        }
    }

    /**
     * Magic函数
     * 用于实现 get_by_xxx/getByXxx方法
     */
    public function __call ($name, $args)
    {
        if (strpos($name, 'get_by_') === 0) {
            $key = substr($name, 7);
            $value = $args[0];
            return $this->selectOne(array(
                $key => $value
            ));
        } else
            if (strpos($name, 'getBy') === 0) {
                $key = strtolower(substr($name, 5));
                if ($key) {
                    $where = array(
                        $key => $args[0]
                    );
                    return $this->selectOne($where);
                }
            } else
                if (strpos($name, 'before') === 0 || strpos($name, 'after') === 0) {
                    return TRUE;
                }
        trigger_error('Undefined method ' . $name . ' called!');
        return FALSE;
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

    private function _table()
    {
        $tables = $this->_table;
        if (is_string($this->_table)) {
            $tables = array($this->_table);
        }

        $arr = array();
        foreach ($tables as $table) {
            if (preg_match('@^\w+$@', $table)) {
                $arr[] = "`{$table}`";
            } else {
                $arr[] = $table;
            }
        }

        return implode(',', $arr);
    }

    public function getLastSql ()
    {
        return $this->_lastSql;
    }

    public function checkDbStatus()
    {
        $obj = $this->_getDbInstance();

        if ($obj && $obj->getDbWrite()) {

            return TRUE;
        }

        return FALSE;
    }

    protected function _getDbInstance ()
    {
        if ($this->_dbInstance) {
            return $this->_dbInstance;
        }

        if ($this->_dbClusterId !== NULL) {
            if ($this->_objectId !== NULL) {
                $this->_dbInstance = FarmDb::getInstanceByObjectId($this->_objectId, $this->_dbClusterId);
            } else {
                $this->_dbInstance = GlobalDb::getInstance($this->_dbClusterId);
            }
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
        $this->_sqlHelper = NULL;
    }
}
/* End of file <`2:filename`>.php */
