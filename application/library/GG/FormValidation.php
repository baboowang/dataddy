<?php
namespace GG;

class FormValidation {

    const INVALID_RULE = '%s规则错误:%d';

    public $errors = [];

    public function __construct()
    {
    }

    public function check($rules, $obj = NULL)
    {
        $validation_data = [];

        foreach ($rules as $key => $rule) {
            @list($name, $label) = explode('|', $key);

            $is_array = FALSE;
            if (preg_match('@\[\]$@', $name)) {
                $is_array = TRUE;
                $name = preg_replace('@\[\]$@', '');
            }

            $is_required = preg_match('@\brequired\b@', $rule);

            if (!isset($_POST[$name])) {

                if ($is_required) {
                    $this->errors[$name] = sprintf('%s为必填项', $label);
                }

                continue;
            }

            $value = $_POST[$name];

            if ($is_array && $value && !is_array($value)) {
                $value = [ $value ];
            }

            if (!$is_array && is_array($value)) {
                $this->errors[$name] = sprintf(self::INVALID_RULE, $label, 1);

                continue;
            }

            $values;

            if (!is_array($value)) {
                $values = [ &$value ];
            } else {
                $values = &$value;
            }

            foreach ($values as &$ivalue) {
                if ($this->_check($name, $label, $rule, $ivalue, $obj) === FALSE) {
                    break;
                }
            }

            $validation_data[$name] = $value;
        }

        if (empty($this->errors)) {

            return $validation_data;
        }

        return FALSE;
    }

    protected function _check($name, $label, $rule, &$value, $obj)
    {
        $check_list = explode('|', $rule);

        foreach ($check_list as $check_item) {
            $err_msg = '';

            if (preg_match('@^cb_(.+)$@', $check_item, $ma)) {
                $cb = $ma[1];

                if ($obj && method_exists($obj, $cb)) {
                    if ($obj->$cb($value, $err_msg) === FALSE) {
                        $this->errors[$name] = sprintf($err_msg, $label);
                    }
                } elseif (is_callable($cb)) {
                    if (call_user_func_array($cb, [$value, &$err_msg])) {
                        $this->errors[$name] = sprintf($err_msg, $label);
                    }
                } else {
                    $this->errors[$name] = sprintf(self::INVALID_RULE, $label, 2);
                }

                return FALSE;
            }

            if (!preg_match('@^(\w+)(?:\[(.+)\])?$@', $check_item, $ma)) {
                $this->errors[$name] = sprintf(self::INVALID_RULE, $label, 3);

                return FALSE;
            }

            $func = $ma[1];
            $method = 'rule_' . $func;
            $args = isset($ma[2]) ? preg_split('@\s*,\s*@', $ma[2]) : [];

            if (!method_exists($this, $method)) {

                // As filter function
                if (is_callable($func)) {
                    $value = call_user_func($func, $value);
                    continue;
                }

                $filter_method = 'filter_' . $func;
                if (method_exists($this, $filter_method)) {
                    $value = call_user_func_array([$this, $filter_method], [$value]);
                    continue;
                }

                $this->errors[$name] = sprintf(self::INVALID_RULE, $label, 4);

                return FALSE;
            }

            array_unshift($args, $value);

            $args[] = &$err_msg;

            if (call_user_func_array([$this, $method], $args) === FALSE) {
                $this->errors[$name] = sprintf($err_msg, $label);

                return FALSE;
            }
        }

        return TRUE;
    }

    public function errors()
    {
        return implode("<br/>", $this->errors);
    }

    public function filter_bool($val)
    {
        if (empty($val) || in_array(strtolower($val), [ 'false', 'null', 'nil', 'none' ])) {

            return FALSE;
        }

        return TRUE;
    }

    public function rule_any($val, &$err_msg)
    {
        return TRUE;
    }

    public function rule_required($val, &$err_msg)
    {
        if ($val === '') {
            $err_msg = '%s为必填项';

            return FALSE;
        }

        return TRUE;
    }

    public function rule_uri($val, &$err_msg)
    {
        if ($val === '') return TRUE;

        if (!preg_match('@^http@i', $val)) {
            $val = 'http://xxx.com/' . $val;
        }

        return $this->rule_url($val, $err_msg);
    }

    public function rule_url($val, &$err_msg)
    {
        if ($val === '') return TRUE;

        $pattern = "/^(http|https|ftp):\/\/([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i";

        if (!preg_match($pattern, $val)) {
            $err_msg = '%s不是合法的URL';

            return FALSE;
        }

        return TRUE;
    }

    public function rule_email($val, &$err_msg)
    {
        if ($val === '') return TRUE;

        if ( ! preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $val)) {
            $err_msg = '%s不是合法的Email地址';

            return FALSE;
        }

        return TRUE;
    }

    public function rule_json($val, &$err_msg)
    {
        if ($val === '') return TRUE;

        $val = my_json_decode($val, NULL);

        if (is_null($val)) {
            $err_msg = '%s不是合法的JSON:' . json_last_error();

            return FALSE;
        }

        return TRUE;
    }

    public function rule_date($val, &$err_msg)
    {
        if ($val === '') return TRUE;

        $ret = strtotime($val);

        if ($ret < 0 || $ret === FALSE || !preg_match('@^\d{4}-\d{2}-\d{2}$@', $val)) {
            $err_msg = '%s不是有效日期';

            return FALSE;
        }

        return TRUE;
    }

    public function rule_safe_password($val, &$err_msg)
    {
        if ($val === '') return TRUE;

        if (strlen($val) < 8) {
            $err_msg = '%s长度最少为8位';

            return FALSE;
        }

        $level = 0;

        if (preg_match('@\d@', $val)) $level++;
        if (preg_match('@[a-z]@', $val)) $level++;
        if (preg_match('@[A-Z]@', $val)) $level++;
        if (preg_match('@[^0-9a-zA-Z]@', $val)) $level++;

        if ($level < 3) {
            $err_msg = '您设置的%s太简单，密码必须包含数字、大小写字母、其它符号中的三种及以上';

            return FALSE;
        }

        return TRUE;
    }

    function rule_exists_row($val, $table, &$err_msg)
    {
        if ($val === '' || $val === '0') return TRUE;

        $tokens = explode('.', $table);

        $key = array_pop($tokens);
        $table = array_pop($tokens);
        $dbClusterId = NULL;

        if ($tokens) {
            $dbClusterId = $tokens[0];
        }

        $vals = explode(',', $val);
        $valCount = count($vals);

        $count = M($table, $dbClusterId)->selectCount(array(
            $key => $vals
        ));

        if ($count != $valCount) {
            $err_msg = '%s包含不存在的记录';

            return FALSE;
        }

        return TRUE;
    }

    function rule_unique_row($val, $table, &$err_msg)
    {
        if ($val === '') return TRUE;

        $tokens = explode('.', $table);

        $key = array_pop($tokens);
        $table = array_pop($tokens);
        $dbClusterId = NULL;

        if ($tokens) {
            $dbClusterId = $tokens[0];
        }

        param_request(array(
            'id' => 'UINT'
        ));

        $where = array(
            $key => $val,
        );

        if (!empty($GLOBALS['req_id'])) {
            $where['id'] = array(
                '!=' => $GLOBALS['req_id']
            );
        }

        $count = M($table, $dbClusterId)->selectCount($where);

        if ($count > 0) {
            $err_msg = '%s已经存在';

            return FALSE;
        }

        return TRUE;
    }

    function rule_max_width($val, $len, &$err_msg)
    {
        if ($val === '') return TRUE;

		$res = (mb_strwidth($val) > $len) ? FALSE : TRUE;

        if (!$res) {
            $err_msg = "%s最大长度为{$val}";

            return FALSE;
        }

        return $res;
    }

    public function rule_natural($val, &$err_msg)
    {
        if ($val === '') return TRUE;

        if (!preg_match( '/^[0-9]+$/', $val)) {
            $err_msg = '%s不是合法的自然数';

            return FALSE;
        }

        return TRUE;
    }

    public function rule_int($val, &$err_msg)
    {
        if ($val === '') return TRUE;

        if (!preg_match('/^[\-+]?[0-9]+$/', $val)) {
            $err_msg = '%s不是合法的整数';

            return FALSE;
        }

        return TRUE;
    }


    public function rule_alpha($val, &$err_msg)
    {
        if ($val === '') return TRUE;

        if ( ! preg_match("/^([a-z])+$/i", $val)) {
            $err_msg = '%s仅能包含字母';

            return FALSE;
        }

        return TRUE;
    }

    public function rule_alpha_numeric($str, &$err_msg)
    {
        if ($val === '') return TRUE;

        if ( ! preg_match("/^([a-z0-9])+$/i", $val)) {
            $err_msg = '%s仅能包含字母和数字';

            return FALSE;
        }

        return TRUE;
    }

    public function rule_alpha_dash($val, &$err_msg)
    {
        if ($val === '') return TRUE;

        if ( ! preg_match("/^([-a-z0-9_-])+$/i", $val)) {
            $err_msg = '%s仅能包含字母、数字、_-';

            return FALSE;
        }

        return TRUE;
    }

    public function rule_numeric($val, &$err_msg)
    {
        if ($val === '') return TRUE;

        if (!preg_match( '/^[\-+]?[0-9]*\.?[0-9]+$/', $val)) {
            $err_msg = '%s不是合法数字';

            return FALSE;
        }

        return TRUE;
    }

    public function rule_gt($val, $n, &$err_msg)
    {
        if ($val === '') return TRUE;

        if (!$this->rule_numeric($val, $err_msg)) {

            return FALSE;
        }

        if ($val <= $n) {

            $err_msg = '%s必须大于' . $n;

            return FALSE;
        }

        return TRUE;
    }

    public function rule_ge($val, $n, &$err_msg)
    {
        if ($val === '') return TRUE;

        if (!$this->rule_numeric($val)) {

            return FALSE;
        }

        if ($val < $n) {
            $err_msg = '%s必须大于等于' . $n;

            return FALSE;
        }

        return TRUE;
    }

    public function rule_lt($val, $n, &$err_msg)
    {
        if ($val === '') return TRUE;

        if (!$this->rule_numeric($val)) {

            return FALSE;
        }

        if ($val >= $n) {
            $err_msg = '%s必须小于' . $n;

            return FALSE;
        }

        return TRUE;
    }

    public function rule_le($val, $n, &$err_msg)
    {
        if ($val === '') return TRUE;

        if (!$this->rule_numeric($val)) {

            return FALSE;
        }

        if ($val > $n) {
            $err_msg = '%s必须小于等于' . $n;

            return FALSE;
        }

        return TRUE;
    }
}
/* End of file <`2:filename`>.php */
