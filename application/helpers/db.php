<?php
/**
 * Quote variable to make safe
 *
 * @param string $value
 * @return string
 */
function escapeInputValue($value)
{
    return "'" . mysql_escape_string($value) . "'";
}

/**
 * Escape string use mysql_escape_string
 *
 * @param string $value
 * @return string
 */
function escapeRawInputValue($value)
{
    if (get_magic_quotes_gpc()) {
        $value = stripslashes($value);
    }
    return mysql_escape_string($value);
}

/**
 * Quote string or string array use function escapeInputValue
 *
 * @param string|array(string) $value
 * @return string|array
 */
function escapeInput($value)
{
    return is_array($value) ? array_map('escapeInput', $value)
                            : escapeInputValue($value);
}

/**
 * Escape string or string array use function mysql_escape_string
 *
 * @param string|array $value
 * @param string|array
 */
function escapeRawInput($value)
{
    return is_array($value) ? array_map('escapeRawInput', $value)
                            : escapeRawInputValue($value);
}

/**
 * Get pdo error
 *
 * @param object $pdo PDO object
 * @return string
 */
function getPdoErr($pdo)
{
    if (!empty($pdo)) {
        $err = $pdo->errorInfo();
        return $err[2];
    }
    return '';
}

function _escapeInputValue($val)
{
    if (strpos($val, '&/') === 0) {
        return escapeRawInputValue(substr($val, 2));
    }
    return escapeInputValue($val);
}

/**
 * 获取SQL语句的条件串
 * 
 * @param array   $conds array('id' => 3, name => 'baboo')
 * @param integer $start 
 * @param integer $count 当start = count = 0, 返回所有
 * @param string  $sort  NULL or string like 'id DESC'
 * @param array   $otherConds 复杂的条件, array( array('key' => 'id'), )
 */
function getSqlCond($conds, $start = 0, $count = 0, $sort = NULL, $otherConds = array())
{
    if (!empty($conds)) {
        $sql = ' WHERE ';
    } else {
        $sql = '';
        $conds = array();
    }
    foreach ($conds as $condName => $condVal) {
        if ($sql != ' WHERE ') {
            $sql .= ' AND ';
        }
        $condName = escapeRawInputValue($condName);
        if (!is_array($condVal)) {
            $condVal = _escapeInputValue($condVal);
            $sql .= " $condName=$condVal ";                
        } else if (!empty($condVal)) {
            if (isset($condVal[0])) {
                $sql .= "(";
                foreach ($condVal as $orCondVal) {
                    $orCondVal = _escapeInputValue($orCondVal);
                    $sql .= " $condName=$orCondVal OR ";
                }
                $sql = rtrim($sql, ' OR ');
                $sql .= ')';    
            } else {
                //关系数组， array('operation' => 'value')
                $operation = array_shift(array_keys($condVal));
                $value = array_shift(array_values($condVal));
                $value = _escapeInputValue($value);
                $sql .= ' '.$condName.$operation.$value.' ';
            }
        } else {
            //空数组，这应该算是个错误的参数
            $sql .= " $condName=''";    
        }
    }
    if (!empty($otherConds)) {
        if (empty($conds)) {
            $sql = ' WHERE ';
        }
        $otherCondStr = '';
        foreach ($otherConds as $cond) {
            if (!empty($otherCondStr)) {
                $otherCondStr .= ' AND ';
            }
            $orConds = $cond;
            if (isset($cond['key'])) {
                $orConds = array($cond);
            }
            if (count($orConds) > 1) {
                $otherCondStr .= '(';
            }
            foreach ($orConds as $orCond) {
                $otherCondStr .= escapeRawInputValue($orCond['key']) . ' ' . $orCond['operation'] . ' ';
                if (!empty($orCond['raw_value'])) {
                    $otherCondStr .= escapeRawInputValue($orCond['raw_value']);
                } else {
                    $otherCondStr .= escapeInputValue($orCond['value']);
                }
                $otherCondStr .= ' OR ';
            }
            $otherCondStr = rtrim($otherCondStr, ' OR ');
            if (count($orConds) > 1) {
                $otherCondStr .= ')';
            }
        }
        if (!empty($conds)) {
            $sql .= ' AND ';
        }
        $sql .= $otherCondStr;
    }
    if (!empty($sort)) {
        $sql .= ' ORDER BY '.trim(escapeRawInputValue($sort), "'");
    }
    if ($start >= 0 && $count > 0) {
        $start = escapeRawInputValue($start);
        $count = escapeRawInputValue($count);
        $sql .= " LIMIT $start,$count";
    }
    return $sql;
}

/**
 * 获取SQL的插入串
 * (field1, field2) VALUES (val1, val2)
 *
 * @param array $toConvArr array('field1' => 'val1', ..), val会进行调用escapeInput过滤
 * @param array $rawArr array('field1' => 'CURRENT_TIMESTAMP', ...), 
 * @return string
 */
function getSqlInsert($toConvArr, $rawArr = NULL)
{
    $sqlKey = ' (';
    $sqlVal = ' (';
    
    if (is_array($toConvArr)) {
        foreach ($toConvArr as $key => $val) {
            $key = escapeRawInputValue($key);
            $val = _escapeInputValue($val);
            $sqlKey .= "`$key`,";
            $sqlVal .= "$val,";
        }
    }

    if (!empty($rawArr) && is_array($rawArr)) {
        foreach ($rawArr as $key => $val) {
            $sqlKey .= "`$key`,";
            $sqlVal .= "$val,";
        }
    }

    $sqlKey = rtrim($sqlKey, ',') . ')';
    $sqlVal = rtrim($sqlVal, ',') . ')';
    return $sqlKey . ' VALUES ' . $sqlVal;
}

/**
 * 获取SQL的插入串（一次插入多行）
 * (field1, field2) VALUES (val1, val2),(val1, val2)...
 *
 * @param array $toConvArr array(array('field1' => 'val1', ..)), val会进行调用escapeInput过滤
 * @param array $rawArr array(array('field1' => 'CURRENT_TIMESTAMP', ...)), 
 * @return string
 */
function getSqlInsertMulti($toConvArr, $rawArr = NULL)
{
    $fieldPart = '';
    $valuePart = array();
    if (empty($toConvArr)) {
        return false;
    }
    $count = count($toConvArr);
    if (is_array($rawArr) && count($rawArr) != $count) {
        return false;
    }
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($toConvArr[$i])) {
            return false;
        }
        $str = getSqlInsert($toConvArr[$i], is_array($rawArr) ? $rawArr[$i] : null);
        list($part1, $part2) = explode(' VALUES ', $str);
        if ($fieldPart == '') {
            $fieldPart = $part1;
        } else if ($part1 != $fieldPart) {
            return false;
        }
        $valuePart[] = $part2;
    }
    return $fieldPart . ' VALUES ' . implode(',', $valuePart);
}


/**
 * 获取SQL更新串
 * Set field1=val1, field2=val2 ...
 *
 * @param array $toConvArr array('field1' => 'val1', ..)
 * @param array $rawArr array('count' => 'count+1', ...)
 * @return string
 */
function getSqlUpdate($toConvArr, $rawArr = NULL)
{
    $sql = '';

    if (!empty($toConvArr)) {
        foreach ($toConvArr as $key => $val) {
            $key = escapeRawInputValue($key);
            $val = _escapeInputValue($val);
            $sql .= " `$key`=$val,";
        }
    }

    if (!empty($rawArr)) {
        foreach ($rawArr as $key => $val) {
            $sql .= " `$key`=$val,";
        }
    }

    return ' SET ' . trim($sql, ',');
}

/**
 * 获取SQL替换语句
 * 有重复主键时进行更新，否则进行插入操作
 * 
 * @example
 * getSqlReplace(
 *  array('id' => 1, 'name' => 'baboo', 'count' => 1),
 *  NULL,
 *  NULL,
 *  array('count' => 'count+1')
 *  );  
 *  
 * @param array $toConvInsArr 
 * @param array $rawInsArr
 * @param array $toConvUptArr
 * @param array $rqwUptArr
 * @return string
 */
function getSqlReplace($toConvInsArr, $rawInsArr = NULL, $toConvUptArr = NULL, $rawUptArr = NULL) 
{
    $sql = getSqlInsert($toConvInsArr, $rawInsArr);

    if (!empty($toConvUptArr) || !empty($rawUptArr)) {
        $sql .= ' ON DUPLICATE KEY UPDATE ';
    } else {
        return $sql;
    }
    
    $uptSql = '';
    if (!empty($toConvUptArr)) {
        foreach ($toConvUptArr as $key => $val) {
            $key = escapeRawInputValue($key);
            $val = _escapeInputValue($val);
            //if (isset($toConvInsArr[$key])) {
                $uptSql .= " `$key`=$val,";
            //}
        }
    }
    if (!empty($rawUptArr)) {
        foreach ($rawUptArr as $key => $val) {
            //if (isset($rawInsArr[$key])) {
                $uptSql .= " `$key`=$val,";
            //}
        }
    }
    $sql .= rtrim($uptSql, ',');

    return $sql;
}
