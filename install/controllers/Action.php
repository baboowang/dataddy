<?php
class Action {

    protected $charset = 'utf-8';
    protected $viewPath = NULL;
    protected $ext = '.php';
    protected $variables = array();
    protected $parameters = array();
    protected $queryString = NULL;
    protected $moduleIndex = 'System';
    protected $actionIndex = 'index';
    protected $moduleName = 'm';
    protected $actionName = 'a';
    protected $allCheck = array();
    public    $checkResult = 1;

    public function __construct($viewPath, $ext = '.php') {
        $this->setViewPath($viewPath);
        $this->setViewExt($ext);
        $this->setQueryString();
        $this->parseParameters();
        $checkFile = INSTALL_PATH . DS . 'check.php';
        if(!file_exists($checkFile)){
            exit('安装文件不存在');
        }
        $this->allCheck = require($checkFile);

        $step = @trim(file_get_contents(INSTALL_PATH . DS . 'step.txt'));
        $this->initTitle();

        if ($this->getModuleIndex() == 'index') {
            $this->redirect($this->getAppHost());
        }

        if(file_exists(APP_CONF_PATH) || $step == 'all_install'){
            if ($this->isAjax()) {
                $this->_fail('已安装成功');
            } else {
                $this->redirect($this->getAppHost());
            }
        }
    }

    public function isAjax() {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest'
        ) {
            return TRUE;
        }

        return FALSE;
    }

    protected function initTitle(){
        $this->assign('subTitle', '');
    }

    protected function getAppHost(){
        return 'http://' . $_SERVER['SERVER_NAME'];
    }

    protected function setViewPath($viewPath){
        $this->viewPath = $viewPath;
    }

    protected  function getViewPath(){
        return $this->viewPath;
    }

    protected function setViewExt($ext){
        $this->ext = $ext;
    }

    protected  function getViewExt(){
        return $this->ext;
    }

    protected function setModuleIndex($name){
        $this->moduleIndex = $name;
    }

    protected  function getModuleIndex(){
        return $this->moduleIndex;
    }

    protected function setActionIndex($name){
        $this->actionIndex = $name;
    }

    protected  function getActionIndex(){
        return $this->actionIndex;
    }

    protected function getParameters(){
        return $this->parameters;
    }

    protected function assign($key, $value) {
        $this->variables[$key] = $value;
    }

    protected function assigns($vars){
        if(!is_array($vars)){
            $vars = array(
                $vars => ''
            );
        }

        foreach($vars as $key => $var){
            $this->variables[$key] = $var;
        }
    }

    public function setHeader($key, $value) {
        header(sprintf("%s: %s", $key, $value));
    }

    protected function redirect($url) {
        $this->setHeader('Location', $url);
        exit(1);
    }

    protected function output($tplName = '') {
        $tplFile    = '';
        $viewPath   = $this->getViewPath();
        $extName    = $this->getViewExt();

        if('' == $tplName) {
            $moduleName = strtolower($this->getModuleIndex());
            $actionName = strtolower($this->getActionIndex());
            $tplName    = $moduleName . DS . $actionName . $extName;
        } else {
            if(substr($tplName, -strlen($extName) == $extName)) {
                $tplName = str_replace(array('\\', '/'), DS, $tplName);
            } else {
                $tplName = str_replace(array('\\', '/'), DS, $tplName) . $extName;
            }
        }

        $tplFile = $viewPath . DS . $tplName;
        if(file_exists($tplFile)) {

            $charset = $this->charset;
            $this->setHeader('Content-Type', 'text/html; charset=' . $charset);
            $parameters = $this->getParameters();

            if(!empty($parameters) && is_array($parameters)) {
                extract($parameters, EXTR_PREFIX_ALL, 'req_');
            }

            $this->view($tplFile);
        }
        else {
            die($tplName . "文件不存在");
        }
    }

    protected function view($tplFile){

        if(!empty($this->variables) && is_array($this->variables)) {
            extract($this->variables);
        }

        $viewPath   = $this->getViewPath();
        $extName    = $this->getViewExt();

        ob_start();
        include_once $tplFile;
        $__content = ob_get_contents();
        @ob_end_clean();

        include_once $viewPath . DS . '_base' . $extName;

    }

    protected function outputJson($data) {
        $charset = $this->charset;
        $this->setHeader('Content-Type', 'application/json; charset=' . $charset);
        echo json_encode($data);
    }

    public function getParameter($name) {
        $this->parseParameters();
        return isset($this->parameters[$name]) ? $this->parameters[$name] : null;
    }

    public function setQueryString(){
        $this->queryString = $_SERVER['QUERY_STRING'];
    }

    public function getQueryString(){
        return $this->queryString;
    }

    public function parseParameters(){
        static $hasParsed = false;

        if ($hasParsed) {
            return;
        }

        parse_str($this->getQueryString(), $results);

        $moduleName = $this->moduleName;
        $actionName = $this->actionName;

        foreach($results as $name => $value){
            if($name == $moduleName){
                $this->setModuleIndex($value);
            }else if($name == $actionName){
                $this->setActionIndex($value);
            }else{
                $this->parameters[$name] = $value;
            }

        }

        $hasParsed = true;
    }

    public function run($rules)
    {
        $errors = array();
        if (count($_POST) == 0)
        {
            foreach($rules as $key => &$rule){
                $rules = implode('|', $rule);
                $labels = explode('|', $key);
                foreach($rules as $rule){
                    FormValidate::$rule($labels[0], $labels[1], d(@$_POST[$labels[1]]), $errors);
                }
            }
        }else{
            die('没有数据');
        }

    }

    protected function _succ($msg = '保存成功', $data = array(), $onlySet = FALSE)
    {
        $ret = array(
            'code' => 0,
            'msg'  => $msg,
            'data' => $data,
        );

        echo json_encode($ret);
        exit;
    }

    protected function _fail($msg)
    {
        $ret = array(
            'code' => -1,
            'msg' => $msg,
        );
        echo json_encode($ret);
        exit;
    }

    public function dealInitData($data, $map){
        $tmp = array();
        foreach($map as $key => $rule){

            $rules = explode('|', $rule);
            $newKey = $rules[0];

            $val = d(@$data[$key], '');

            if(isset($rules[1])){
                $mode = $rules[1];
                switch($mode){
                    case '1':
                        $val = "'" . $val . "'";
                        break;
                    case '2':
                        $val = $val ? 'true' : 'false';
                        break;
                    case '3':
                        $prefix = d(@$rules[2], '');
                        $val = $prefix . $val;
                        break;
                    case '4':
                        $prefix = d(@$rules[2], '');
                        $val = $prefix . "'" . $val . "'";
                        break;
                }
            }
            $tmp[$newKey] = $val;
        }

        return $tmp;

    }

    public function getParamsToInit($params){
        if(!file_exists(USER_INSTALL_PATH)){
            $this->_fail('初始化配置文件不存在');
        }

        if(!$fp = fopen(USER_INSTALL_PATH, 'r')){
            $this->_fail('打开初始化配置文件失败');
        }

        while (!feof($fp)) {
            $line = trim(fgets($fp));
            if (!empty($line)) {
                $ps = array_map(function ($a) {
                    return trim($a);
                }, preg_split('/\s+=\s{0,}/', $line));

                if($ps[0] == $params && !empty($ps[1])){
                    return $ps[1];
                }
            }
        }

        return FALSE;

    }

    public function writeParamsToInit($data = NULL, $map, $isDeal = TRUE, $compulsive = FALSE){

        if($isDeal) {
            $map = $this->dealInitData($data, $map);
        }

        $initFile = USER_INSTALL_PATH;

        if(!file_exists($initFile)){
            $this->_fail('初始化配置文件不存在');
        }

        if( ! $fp = fopen($initFile, 'r')){
            $this->_fail('打开初始化配置文件失败');
        }

        $tmp = array();
        if ($fp) {
            while (!feof($fp)) {
                $line = trim(fgets($fp));
                if (!empty($line)) {
                    $ps = array_map(function ($a) {
                        return trim($a);
                    }, preg_split('/\s+=\s{0,}/', $line));

                    if (isset($map[$ps[0]]) && !empty($map[$ps[0]]) && ($compulsive || empty($ps[1]))) {
                        $content = $ps[0] . ' = ' . $map[$ps[0]];
                        $tmp[] = $content;
                    }else {
                        $tmp[] = $line;
                    }

                    unset($map[$ps[0]]);
                }else{
                    $tmp[] = "";
                }
            }
            fclose($fp);
        }

        $extra_config = [];
        foreach ($map as $key => $value) {
            if ($value !== "") {
                $extra_config[] = "{$key}={$value}";
            }
        }

        if ($extra_config) {
            $index = array_search(';; EXTRA CONFIG', $tmp);
            if ($index === FALSE) {
                $tmp = array_merge($tmp, $extra_config);
            } else {
                array_splice($tmp, $index, 1, $extra_config);
            }
        }

        if(!file_put_contents($initFile, implode("\n", $tmp))){
            return FALSE;
        }

        return TRUE;

    }
}
