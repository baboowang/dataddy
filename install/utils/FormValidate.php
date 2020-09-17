<?php
class FormValidate{

    static $rules = array();
    static $user_fun_prefix = 'callback_';
    static $lang = array(
        'required' => '%s为必填。',
        'alpha_dash' => '%s只能包含字母、数字、下划线、中划线。',
        'numeric' => '%s只能包含数字。',
        'integer' => '%s必须是一个整数。',
        'is_natural_no_zero' => '%s只能包含自然数(不包括零)。',
        'valid_ip' => '%s不是有效IP。',
        'valid_mobile' => '%s不是有效手机号。',
        'valid_email' => '%s不是有效Email',
        'less_than' => "%s的值必须小于等于%n。",
        'greater_than' => "%s的值必须大于等于%n。",
        'min_than' => '%s的长度必须大于等于%n。',
        'max_than' => '%s的值必须小于等于%n。',
        'validate_email' => '%s不是一个有效的电子邮箱。',
        'v_is_dir' => '%s不存在或者目录没有写权限',
        'v_file_exists' => '%s不存在或者文件没有写权限',
        'safe_password' => '您设置的%s太简单，密码必须包含数字、大小写字母、其它符号中的三种及以上',
        'uri' => '%s不是合法的URL'
    );

    static $data = NULL;
    static $errs = array();

    public static function setRules($rules){
        self::$rules = $rules;
    }

    public static function run(){
        if(!self::$rules){
            return '';
        }
        $data = $_REQUEST;
        $rules = self::$rules;

        foreach($rules as $key => $rule){
            $keys = explode('|', $key);
            $name = $keys[0];
            $label = $keys[1];
            $rules = explode('|', $rule);

            if(!isset($data[$name])) $data[$name] = NULL;

            if(is_array($data[$name])){
                $val = array_map(function($a){return trim($a);}, $data[$name]);
            }else {
                $val = trim($data[$name]);
            }

            foreach($rules as $r){

                if(function_exists($r)){
                    if(is_array($data[$name])){
                        $val = array_map(function($a) use ($r){return $r($a);}, $data[$name]);
                    }else {
                        $val = $r($name);
                    }
                }

                $reqLen = NULL;
                if(preg_match('/\[(\d+)\]/', $r, $match)){
                    $reqLen = $match[1];
                    $r = preg_replace('/\[\d+\]/', '', $r);
                }

                if(method_exists('FormValidate', $r)){
                    if(self::$r($name, $label, $val, $reqLen)){
                        self::$data[$name] = $val;
                    }else{
                        return FALSE;
                    }
                }else if(strpos($r, self::$user_fun_prefix) === 0){
                    $r = str_replace(self::$user_fun_prefix, '', $r);
                    $trace = debug_backtrace();
                    $callback_class = $trace[1]['class'];
                    if($callback_class::$r($name, $label, $val)){
                        self::$data[$name] = $val;
                    }else{
                        return FALSE;
                    }
                }else{
                    self::$data[$name] = $val;
                }
            }
        }
        return TRUE;
    }

    public static function uri($name, $label, $val){
        if ($val === '') return TRUE;

        if (!preg_match('@^http|https@i', $val)) {
            $val = 'http://' . $val;
        }

        $pattern = "/^(http|https|ftp):\/\/([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i";

        if (!preg_match($pattern, $val)) {
            self::setError($name, $label, 'uri');
            return FALSE;
        }

        return TRUE;
    }

    public static function required($name, $label, $val){
        if($val !== NULL && $val != ''){
            return TRUE;
        }else{
            self::setError($name, $label, 'required');
            return FALSE;
        }
    }

    public static function valid_email($name, $label, $val){
        if(preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $val)){
            return TRUE;
        }else{
            self::setError($name, $label, 'valid_email');
            return FALSE;
        }
    }

    public static function valid_mobile($name, $label, $val){
        if(preg_match("/^(13|15|18)\d{9}$/", $val)){
            return TRUE;
        }else{
            self::setError($name, $label, 'valid_mobile');
            return FALSE;
        }
    }

    public static function numeric($name, $label, $val){
        if(is_numeric($val)){
            return TRUE;
        }else{
            self::setError($name, $label, 'numeric');
            return FALSE;
        }
    }

    public static function integer($name, $label, $val){
        if(preg_match('/^[\-+]?[0-9]+$/', $val)){
            return TRUE;
        }else{
            self::setError($name, $label, 'integer');
            return FALSE;
        }
    }

    public static function is_natural_no_zero($name, $label, $val)
    {
        if ( ! preg_match( '/^[0-9]+$/', $val))
        {
            self::setError($name, $label, 'is_natural_no_zero');
            return FALSE;
        }

        if ($val == 0)
        {
            self::setError($name, $label, 'is_natural_no_zero');
            return FALSE;
        }

        return TRUE;
    }

    public static function valid_domain($name, $label, $val) {
        return $val === 'localhost'
            || self::uri($name, $label, $val)
            || self::valid_ip($name, $label, $val);
    }

    public static function valid_ip($name, $label, $val)
    {
        if($val == 'localhost' || $val == '127.0.0.1'){
            return TRUE;
        }

        $ip_segments = explode('.', $val);

        if (count($ip_segments) !== 4)
        {
            self::setError($name, $label, 'valid_ip');
            return FALSE;
        }

        if ($ip_segments[0][0] == '0')
        {
            self::setError($name, $label, 'valid_ip');
            return FALSE;
        }

        foreach ($ip_segments as $segment)
        {

            if ($segment == '' OR preg_match("/[^0-9]/", $segment) OR $segment > 255 OR strlen($segment) > 3)
            {
                self::setError($name, $label, 'valid_ip');
                return FALSE;
            }
        }

        return TRUE;
    }

    public static function alpha_dash($name, $label, $val){
        if(preg_match("/^([-a-z0-9_-])+$/i", $val)){
            return TRUE;
        }else{
            self::setError($name, $label, 'alpha_dash');
            return FALSE;
        }
    }

    public static function greater_than($name, $label, $val, $reqLen)
    {
        $len = intval($val);

        if($len >= $reqLen){
            return TRUE;
        }else{
            self::setError($name, $label, 'greater_than', $reqLen);
            return FALSE;
        }
    }

    public static function less_than($name, $label, $val, $reqLen)
    {
        $len = intval($val);

        if($len <= $reqLen){
            return TRUE;
        }else{
            self::setError($name, $label, 'less_than', $reqLen);
            return FALSE;
        }
    }

    public static function min_than($name, $label, $val, $reqLen){

        $len = mb_strlen($val);

        if($len >= $reqLen){
            return TRUE;
        }else{
            self::setError($name, $label, 'min_than', $reqLen);
            return FALSE;
        }
    }

    public static function max_than($name, $label, $val, $reqLen){
        $len = mb_strlen($val);

        if($len <= $reqLen){
            return TRUE;
        }else{
            self::setError($name, $label, 'max_than', $reqLen);
            return FALSE;
        }
    }

    public static function validate_email($name, $label, $val){
        if( ! preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $val)){
            self::setError($name, $label, 'validate_email');
            return FALSE;
        }

        return TRUE;

    }

    public static function v_is_dir($name, $label, $val){
        if(!is_dir($val)){
            self::setError($name, $label, 'v_is_dir');
            return FALSE;
        }

        $fp = @fopen($val . '/test.txt', 'w');

        if(!$fp){
            @fclose($fp);
            @unlink($val.'/test.txt');
            self::setError($name, $label, 'v_is_dir');
            return FALSE;
        }

        @fclose($fp);
        @unlink($val.'/test.txt');

        return TRUE;
    }
    public static function v_file_exists($name, $label, $val){
        if(!file_exists($val)){
            self::setError($name, $label, 'v_file_exists');
            return FALSE;
        }

        if(!is_writable($val)){
            self::setError($name, $label, 'v_file_exists');
            return FALSE;
        }

        return TRUE;
    }


    public static function safe_password($name, $label, $val)
    {
        if ($val === '') return TRUE;
        $level = 0;

        if (preg_match('@\d@', $val)) $level++;
        if (preg_match('@[a-z]@', $val)) $level++;
        if (preg_match('@[A-Z]@', $val)) $level++;
        if (preg_match('@[^0-9a-zA-Z]@', $val)) $level++;

        if ($level < 3) {
            self::setError($name, $label, 'safe_password');
        }

        return TRUE;
    }

    public static function setError($name, $label, $err, $reqLen = NULL){
        $info = self::$lang[$err];
        $info = preg_replace('/%s/', $label, $info);
        if($reqLen){
            $info = preg_replace('/%n/', $reqLen, $info);
        }
        self::_setError($name, $info);
    }

    public static function _setError($name, $err){
        self::$errs[$name] = $err;
    }

    public static function getErrors(){
        if(!empty(self::$errs)){
            $errors = array_values(self::$errs);
            $es = array_map(function ($a){
                return '<p style="color:red">' . $a . '</p>';
            }, self::$errs);
            return implode('<br/>', $es);
        }
        return '';
    }

    public static function getValidateData(){
        return self::$data;
    }

}
