<?php
namespace GG\Db;

class Sql
{
    const RAW_STR_PREFIX = '&/';
    const RAW_STR_NO_ESCAPE_PREFIX = '&/!';

    const LOGIC = '__logic';

    private $rawStrPrefixLength;

    private function __construct()
    {
        $this->rawStrPrefixLength = strlen(self::RAW_STR_PREFIX);
        $this->rawStrNoEscapePrefixLength = strlen(self::RAW_STR_NO_ESCAPE_PREFIX);
    }

    public static function getInstance()
    {
        static $instance = NULL;
        if ($instance === NULL) {
            $instance = new self();
        }
        return $instance;
    }

    /**
     * SQL 原始字符包装，如 CURRENT_TIMESTAMP, field + 1
     */
    public static function rawValue($val, $escapeIt = TRUE)
    {
        return
        ($escapeIt ? self::RAW_STR_PREFIX : self::RAW_STR_NO_ESCAPE_PREFIX) . $val;
    }

    public static function rawName($name, $escapeIt = TRUE)
    {
        return
        ($escapeIt ? self::RAW_STR_PREFIX : self::RAW_STR_NO_ESCAPE_PREFIX) . $name;
    }

    /**
     * @example
     * 单行数据
     * insert(array(
     *   'key1' => 'val1',
     *   'key2' => '&/CURRENT_TIMESTAMP',
     * ));
     *
     * output : (`key1`,`key2`) VALUES ('val1',CURRENT_TIMESTAMP)
     *
     * 多行数据
     * insert(array(
     *    'key1', 'key2',
     * ), array(
     *      array('val11', 'val12'),
     *      array('val21', 'val22')
     * ));
     * output: (`key1`,`key2`) VALUES ('val11','val12'),('val21','val22')
     *
     * @param array
     */
    public function insert($row, $rowsData = NULL)
    {
        if ($rowsData) {
            $keys = $row;
        } else {
            $keys = array_keys($row);
            $rowsData = array(array_values($row));
        }

        $keySql = '(' . implode(',', array_map(array($this, '_escapeName'), $keys)) . ')';

        $valSqls = array();
        foreach ($rowsData as $data) {
            $valSqls[] =
            '(' . implode(',', array_map(array($this, '_escapeValue'), $data)) . ')';
        }
        $valSql = implode(',', $valSqls);

        return " $keySql VALUES $valSql";
    }

    public function mulInsert($fields, $rows)
    {
        return $this->insert($fields, $rows);
    }

    /**
     * @example
     * update(array(
     *   'key1' => 'value1',
     *   'key2' => '&/CURRENT_TIMESTAMP',
     * ));
     * output: " (`key1`,`key2`) VALUES ('value1',CURRENT_TIMESTAMP)"
     *
     * @param array $data
     * @return string
     */
    public function update($data)
    {
        $sql = '';
        foreach ($data as $name => $val) {
            $name = $this->_escapeName($name);
            $val = $this->_escapeValue($val);
            $sql .= "$name=$val,";
        }
        return ' SET ' . trim($sql, ',');
    }

    /**
     * @example
     * replace(array(
     *    'key1' => 'value1',
     *    'key2' => '&/CURRENT_TIMESTAMP',
     * ), array(
     *    'key1' => '&/key1 + 1'
     * ));
     *
     * output: " (`key1`,`key2`) VALUES ('value1',CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE `key1`=key1 + 1"
     *
     * @param array $insData  same as method insert parameter
     * @param array $resData  replace data
     * @return string
     */
    public function replace($insData, $resData = NULL)
    {
        if ($resData === NULL) {
            $resData = $insData;
        }

        $sql = $this->insert($insData);

        if (empty($resData)) {
            return $sql;
        }

        $sql .= ' ON DUPLICATE KEY UPDATE ';
        $sql .= preg_replace('@^\s*SET\s*@', '', $this->update($resData));

        return $sql;
    }

    public function mulReplace($fields, $rowsData, $resData)
    {
        $sql = $this->insert($fields, $rowsData);

        if (empty($resData)) {

            return $sql;
        }

        $sql .= ' ON DUPLICATE KEY UPDATE ';
        $sql .= preg_replace('@^\s*SET\s*@', '', $this->update($resData));

        return $sql;
    }

    /**
     * @example
     *
     * example 1.
     * where(array(
     *   'key1' => 'value1',
     *   'key2' => NULL,
     *   'key3' => array('!=' => 'value3'),
     *   'key4' => array('value4_1', 'value4_2')
     * ));
     *
     * output : WHERE `key1`='value1' AND `key2` is NULL AND `key3` != 'value3' AND (`key4` = 'value4_1' OR `key4` = 'value4_2')
     *
     * example 2.
     * where(array(
     *    array('key1' => array('like' => '%value1%')),
     *    array(
     *          'key2' => 3,
     *          'key3' => 4,
     *    )
     * ), array(
     *   'order_by' => 'id DESC',
     *   'offset' => 10,
     *   'limit' => 20,
     * ));
     *
     * output: WHERE (`key1` like '%value1%') OR (`key2`='3' AND `key3`='4') ORDER BY id DESC LIMIT 10, 20
     *
     * @param array $where  条件数组,默认是AND关系,数字索引数组(非关系数组)表示OR关系
     * @param array $attrs 可设置的值:order_by,group_by,limit,offset
     * @return string
     */
    public function where($where, $attrs = array())
    {
        $sql = '';
        if (!empty($where)) {
            $whereSql = $this->_where($where);
            if ( $whereSql) {
                $sql .= ' WHERE ' . $whereSql;
            }
        }
        if ($attrs) {
            if (isset($attrs['group_by'])) {
                $sql .= ' GROUP BY ' . $attrs['group_by'];
            }

            if (isset($attrs['having'])) {
                $sql .= ' HAVING ' . $attrs['having'];
            }

            if (isset($attrs['order_by'])) {
                $sql .= ' ORDER BY ' . $attrs['order_by'];
            }

            if (!empty($attrs['offset']) || !empty($attrs['limit'])) {
                $sql .= ' LIMIT ';
                if (isset($attrs['offset'])) {
                    $sql .= $attrs['offset'] . ',';
                }

                if (isset($attrs['limit'])) {
                    $sql .= $attrs['limit'];
                }
            }

            if (!empty($attrs['for_update'])) {
                $sql .= ' FOR UPDATE';
            }
        }

        return $sql;
    }

    private function _where($where)
    {
        if (empty($where) || ! is_array($where)) {

            return '';
        }

        $logic = '';

        if (isset($where[self::LOGIC])) {
            $logic = $where[self::LOGIC];
            unset($where[self::LOGIC]);
        }

        $isArray = self::_isArray($where);
        if ($isArray) {
            $conds = array_map(array($this, '_where'), $where);
            $conds = array_map(array($this, '_wrapWithBrackets'), array_filter($conds));
            if ( ! $logic) {
                $logic = 'OR';
            }
            $sql = implode(" $logic ", $conds);

            return $sql;
        }

        $conds = array();
        foreach ($where as $key => $val) {
            if (is_numeric($key) && is_array($val)) {
                $conds[] = '(' . $this->_where($val) . ')';
                continue;
            }
            $conds[] = $this->_cond($key, $val);
        }
        if ( ! $logic) {
            $logic = 'AND';
        }
        $sql = implode(" $logic ", array_filter($conds));

        return $sql;
    }

    private function _cond($name, $val, $inIteration = FALSE)
    {
        if ($name == '-bool') {
            if (!is_array($val)) {

                return $val;
            }

            return implode(' AND ', $val);
        }

        if ( ! $inIteration) {
            $name = $this->_escapeName($name);
        }

        if ( ! is_array($val)) {
            $val = $this->_escapeValue($val);
            if ($val === 'NULL') {

                return "$name is NULL";
            }
            return "$name=$val";
        }

        $logic = 'OR';
        if (isset($val[self::LOGIC])) {
            $logic = $val[self::LOGIC];
            unset($val[self::LOGIC]);
        }

        if (self::_isHash($val)) {
            if (count($val) == 1) {
                $keys = array_keys($val);
                $operation = array_pop($keys);
                $val = $this->_escapeValue($val[$operation]);
                return "{$name} {$operation} {$val}";
            } else {
                $newVal = array();
                foreach ($val as $iKey => $iVal) {
                    $newVal[] = array($iKey => $iVal);
                }
                $val = $newVal;
            }
        }

        //优化 name => ['val1', 'val2', ..]
        if (self::_isArray($val) && !is_array($val[0] ?? null)) {
            $in_val = implode(',', array_map(function($item){
                return Sql::es($item, true);
            }, $val));
            if ($in_val === '') {
                return "$name IS NULL";
            }
            return "$name in ($in_val)";
        }

        $conds = array();

        foreach ($val as $condVal) {
            if (self::_isArray($condVal)) {
                //array('val1', 'val2', ...)
                $conds[] = $this->_cond($name, $condVal, TRUE);
                continue;
            } else if (self::_isHash($condVal)) {
                //array('!=' => 'val')
                $operation = array_last(array_keys($condVal));
                $condVal = $condVal[$operation];
            } else {
                $operation = '=';
            }
            $condVal = $this->_escapeValue($condVal);
            $conds[] = "{$name} {$operation} {$condVal}";
        }

        if (empty($conds)) {
            return "$name = ''";
        }

        return '(' . implode(" $logic ", $conds) . ')';
    }

    private function _wrapWithBrackets($str)
    {
        return '('.$str.')';
    }

    //是不是纯数字索引
    private static function _isArray($val)
    {
        if (!is_array($val)) {

            return FALSE;
        }
        $keys = array_keys($val);
        foreach ($keys as $key) {
            if ( ! is_numeric($key)) {
                return FALSE;
            }
        }

        return TRUE;
    }

    private static function _isHash($val)
    {
        return is_array($val) && !self::_isArray($val);
    }

    private function _escapeValue($str)
    {
        if ($str === NULL) {

            return 'NULL';
        }
        if (is_int($str) || is_float($str)) return $str;
        if (strpos($str, self::RAW_STR_NO_ESCAPE_PREFIX) === 0) {
            return substr($str, $this->rawStrNoEscapePrefixLength);
        }
        if (strpos($str, self::RAW_STR_PREFIX) === 0) {
            return $this->escape(substr($str, $this->rawStrPrefixLength));
        }

        return $this->escape($str, TRUE);
    }

    private function _escapeName($str)
    {
        if (strpos($str, self::RAW_STR_NO_ESCAPE_PREFIX) === 0) {

            return substr($str, $this->rawStrNoEscapePrefixLength);
        }
        if (strpos($str, self::RAW_STR_PREFIX) === 0) {

            return $this->escape(substr($str, $this->rawStrPrefixLength));
        }

        $str = $this->escape($str);

        return "`$str`";
    }

    public function escape($str, $addQuote = FALSE)
    {
        return self::es($str, $addQuote);
    }

    public static function es($str, $addQuote = FALSE)
    {
        #$str = @mysql_escape_string($str);
        if (is_int($str) || is_float($str)) {

            return $str;
        }
        $str = str_replace(
            array('\\', "\0", "\n", "\r", "'", '"', "\x1a"),
            array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'),
            $str
        );

        if (! $addQuote) {

            return $str;
        }

        return "'" . $str . "'";
    }
}
/* End of file <`2:filename`>.php */
