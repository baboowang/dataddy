<?php

define('LOG_LEVEL', LOG_DEBUG);
error_reporting(E_ALL);
ini_set('display_errors', 'on');
ini_set('track_errors', 1);

date_default_timezone_set('Asia/Shanghai');

!defined('DS') ? define('DS', '/') : '';
define('INSTALL_PATH', dirname(dirname(dirname(__FILE__))) . DS . 'install');
define('MYSQL_INSTALL_PATH', INSTALL_PATH  . DS . 'doc' . DS . 'mysql.init.sql');
define('MYSQL_INSTALL_EG_DATA_PATH', INSTALL_PATH  . DS . 'doc' . DS . 'mysql.eg.sql');
define('USER_INSTALL_PATH', INSTALL_PATH  . DS . 'doc' . DS . 'install.application.ini');
define('USER_INSTALL_PATH_TPL', INSTALL_PATH  . DS . 'doc' . DS . 'install.application.ini.tpl');
define('APP_CONF_PATH', INSTALL_PATH  . DS . '../conf' . DS . 'application.ini');
define('INSTALL_STEP', INSTALL_PATH . DS . 'step.txt');
define('INSTALL_LOG_DIR', INSTALL_PATH . DS . 'logs');

define('FORCE_USE_DB', FALSE); //是否可以使用已经存在的库

if(!defined('APPLICATION_PATH')){
    define("APPLICATION_PATH",  dirname(dirname(dirname(__FILE__))));
}

if(!defined('PLUGINS_PATH')){
    define("PLUGINS_PATH",  dirname(dirname(dirname(__FILE__))) . DS .'plugins');
}

include_once INSTALL_PATH . DS . '/../bootstrap.php';
include_once INSTALL_PATH . DS . 'config.php';
include_once INSTALL_PATH . DS . 'common.php';

include_once INSTALL_PATH . DS . '/../application/helpers/AESCipher.php';
include_once INSTALL_PATH . DS . '/../application/helpers/mail/PHPMailer.php';

define('CODE_SUCC', 0);
define('CODE_ERR_AUTH', 1);
define('CODE_ERR_DENY', 2);
define('CODE_ERR_REDIRECT', 3);
define('CODE_ERR_PARAM', 4);
define('CODE_ERR_SYSTEM', 5);
define('CODE_ERR_OTHER', 100);
define('STATE_PARAM_NAME', 'dataddy_state');

$ps = getModuleAndAction();

// 定义载入类库路径
define('INSTALL_PATH_LIB', implode(';', array(
    INSTALL_PATH . DS . 'controllers',
    INSTALL_PATH . DS . 'utils',
)));

function sp_autoload ($class)
{
    static $lookUpPath = array();

    if (empty($lookUpPath)) {
        $lookUpPath = explode(';', INSTALL_PATH_LIB);
    }

    $paths = explode('_', $class);
    $filename = array_pop($paths);
    $segments = array();
    $segments[] = implode(DS, $paths);
    $segments[] = $filename.'.php';

    array_unshift($segments, '');
    foreach ($lookUpPath as $libPath) {
        $segments[0] = $libPath;
        $path = implode(DS, array_filter($segments));
        if (file_exists($path)) {
            require($path);
            return;
        }
    }
}

function getModuleAndAction(){
    $hasParsed = array(
        'm' => 'System',
        'a' => 'index'
    );

    parse_str($_SERVER['QUERY_STRING'], $results);

    $moduleName = 'm';
    $actionName = 'a';

    foreach($results as $name => $value){
        if($name == $moduleName){
            $hasParsed[$moduleName] = ucfirst($value);
        }else if($name == $actionName){
            $hasParsed[$actionName] = $value;
        }
    }

    return $hasParsed;
}

set_error_handler(function($errNo, $errStr, $errFile, $errLine, array $errContext) {
    if ($errNo >= error_reporting()) {
        return FALSE;
    }

    throw new Exception($errNo, $errNo);
});

spl_autoload_register('sp_autoload');

$viewPath = INSTALL_PATH . DS . 'views';

$action = new $ps['m']($viewPath);
call_user_func_array(array(&$action, $ps['a']), array());
