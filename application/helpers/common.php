<?php
require_once dirname(__FILE__) . '/AESCipher.php';
require_once dirname(__FILE__) . '/mail/PHPMailer.php';
use \GG\Config as Config;

define('CODE_SUCC', 0);
define('CODE_ERR_AUTH', 1);
define('CODE_ERR_DENY', 2);
define('CODE_ERR_REDIRECT', 3);
define('CODE_ERR_PARAM', 4);
define('CODE_ERR_SYSTEM', 5);
define('CODE_ERR_OTHER', 100);
define('STATE_PARAM_NAME', 'dataddy_state');

$GLOBALS['CODE_MESSAGES'] = array(
    CODE_SUCC => 'ok',
    CODE_ERR_AUTH => '未登录',
    CODE_ERR_DENY => '未授权',
    CODE_ERR_REDIRECT => '页面跳转',
    CODE_ERR_PARAM => '参数错误',
    CODE_ERR_SYSTEM => '系统错误',
    CODE_ERR_OTHER  => '未定义错误',
);

function d(...$vals)
{
    foreach ($vals as $val) {
        if (!empty($val)) {
            return $val;
        }
    }
    return $val;
}

function h($str)
{
    return htmlspecialchars($str);
}

//@todo db cluster id
function M($model_name, $db_cluster_id = NULL)
{
    $class = camelize($model_name) . 'Model';

    return call_user_func(array($class, 'getInstance'));
}

//Yaf\Registry::set('is_ajax', $is_ajax);
function R(...$args)
{
    if (count($args) == 1) {

        return Yaf\Registry::get($args[0]);
    }

    return Yaf\Registry::set($args[0], $args[1]);
}

function P($resource, $mode = 'r')
{
    return R('permission')->check($resource, $mode);
}

function aes_encrypt($content, $password)
{
    return AESCipher::getInstance()->encrypt($content, $password);
}

function aes_decrypt($cryptext, $password)
{
    return AESCipher::getInstance()->decrypt($cryptext, $password);
}

function my_json_decode($json, $default = [])
{

    $json = preg_replace('@//[^"]+?$@mui', '', $json);
    $json = preg_replace('@^\s*//.*?$@mui', '', $json);

    $json = $json ? @json_decode($json, TRUE) : $default;

    if (is_null($json)) {
        $json = $default;
    }

    return $json;
}

function response($data, $code = CODE_SUCC, $message = '')
{
    $ret = [
        'code' => $code,
        'message' => d($message, $GLOBALS['CODE_MESSAGES'][$code]),
        'data' => $data,
    ];

    header("Cache-Control: no-cache");
    header("Pragma: no-cache");
    //header('Content-Type: application/json; charset=UTF-8');

    echo json_encode($ret);

    return FALSE;
//    exit;
}

function response_error($code, $message = '')
{
    return response(NULL, $code, $message);
}

function template_cb($data)
{
    $cb = isset($_GET['_cb']) ? $_GET['_cb'] : '';

    if (!$cb) return '';

    $content = [ '<script>template_cb(' . json_encode($cb) . ', function($scope){' ];

    foreach ($data as $key => $value) {
        $content[] = '$scope.' . $key . '=' . json_encode($value) . ';';
    }

    $content[] = '});</script>';

    return implode('', $content);
}

function log_message($msg, $level = LOG_INFO)
{
    static $log_types = array(
        LOG_DEBUG => 'DEBUG',
        LOG_INFO => 'INFO',
        LOG_NOTICE => 'NOTICE',
        LOG_WARNING => 'WARNING',
        LOG_ERR => 'ERR',
        LOG_CRIT => 'CRIT',
        LOG_ALERT => 'ALERT',
        LOG_EMERG => 'EMERG'
    );

    if (! (is_int($level) && $level <= LOG_DEBUG && $level >= LOG_EMERG)) {

        return;
    }

    $log_level = LOG_ERR;

    if ($cl = \GG\Config::get('log.level')) {
        if (($cl = array_search(strtoupper($cl), $log_types)) !== FALSE) {
            $log_level = $cl;
        }
    }

    if ($level <= $log_level) {
        $log_type = $log_types[$level];
        $msg = date('Y-m-d H:i:s') . ":[{$log_type}] {$msg}\n";
        $fn = date('Ymd') . ".log";

        $logdir = \GG\Config::get('log.dir', '.');

        if (! is_dir($logdir)) {
            $oldmask = umask(0);
            mkdir($logdir, 0777, TRUE);
            umask($oldmask);
        }

        $filename = $logdir . '/' . $fn;
        if (! file_exists($filename)) {
        	 error_log($msg, 3, $filename);
        	 chmod($filename, 0777);
             $link_file = "$logdir/current.log";
             if (file_exists($link_file)) {
                 unlink($link_file);
             }
             symlink($filename, $link_file);
        } else {
        	error_log($msg, 3, $filename);
        }

        if (\GG\Config::get('log.stdout')) {
            echo $msg;
        }
    }
}

function n($i, $name = '', $options = NULL)
{
    if (!isset($options['number_format'])) {
        if (!\GG\Config::get('report.number_format', TRUE)) {
            return $i;
        }
    } else if (!$options['number_format']){
        return $i;
    }

    if (!is_numeric($i) || preg_match('@(?:id$|电话|号|邮编|no|idfa|imei$)@iu', $name)) {
        return $i === '' ? '-' : $i;
    }

    if (looklike_float($i)) {
        $result = number_format($i, abs($i) > 1 ? 2 : 4, '.', ',');
        return rtrim(rtrim($result, '0'), '.');
    }

    return number_format($i);
}

function looklike_float($val)
{
    return is_float($val) ||
        (((float)$val > (int)$val || strlen($val) != strlen((int)$val)) && (ceil($val)) != 0);
}

/**
 *
 * email发送
 * @param $recevier
 * @param $msg
 * @param array $config
 */
function send_mail($recevier, $subject, $msg)
{
    if(empty($recevier))
        return false;
    if (is_string($recevier)) {
        $recevier = explode(",", $recevier);
    }

    $mail = new Mail_PHPMailer();

    $mail->IsSMTP(); // telling the class to use SMTP

    $lines = preg_split('@\n@u', $msg);
    $subject = d($subject, $lines[0]);
    $ret = '';

    try {
        $mail->Host = Config::get("mail.host", '');;
        $mail->SMTPDebug= 1; // enables SMTP debug information (for testing)
        $mail->CharSet = 'UTF-8';
        $mail->SMTPAuth = Config::get("mail.auth", true);
        $mail->Port = Config::get("mail.port", 25);
        $mail->Username = trim(Config::get("mail.username", ''));
        $mail->Password = trim(Config::get("mail.password", ''));
        $mail->SMTPSecure = trim(Config::get("mail.secure", ''));
        $mail->ClearAddresses();
        foreach($recevier as $to)
        {
            $mail->AddAddress($to);
        }
        $mail->SetFrom(Config::get("mail.username", ''), Config::get("mail.name", ''));
        $mail->Subject = "=?UTF-8?B?".base64_encode($subject)."?=";
        $mail->MsgHTML($msg);
        $mail->Send();
        //echo "Message Sent OK</p>\n";
    } catch (phpmailerException $e) {
        $ret .= $e->errorMessage(); //Pretty error messages from PHPMailer
    } catch (Exception $e) {
        $ret .= $e->getMessage(); //Boring error messages from anything else!
    }

    if (!empty($ret)) {
        return array("code" => CODE_ERR_SYSTEM, "message" =>  "error:\n" . $ret );
    } else {
        return array("code" => CODE_SUCC, "message" => date("Y-m-d H:i:s")." 成功发送至 ".implode(",", $recevier).". ");
    }
}

function get_state_string($name)
{
    $session = [$name, date('Y-m-d'), '!STATE_SECRET_KEY!'];

    if ($user = R('user')) {
        $session[] = $user['id'];
    }

    $hash = sha1(implode('-', $session));

    return $hash;
}

function check_state_string($name, $to_check = NULL)
{
    if (is_null($to_check)) {
        $to_check = isset($_GET[STATE_PARAM_NAME])
            ? $_GET[STATE_PARAM_NAME]
            : (isset($_POST[STATE_PARAM_NAME]) ? $_POST[STATE_PARAM_NAME] : '');
    }

    return $to_check === get_state_string();
}
