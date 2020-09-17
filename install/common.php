<?php
function _dump($obj){
    echo '<pre>';
    var_dump($obj);
}

function d()
{
    $args = func_get_args();
    foreach ($args as $arg) {
        if (!empty($arg)) {
            return $arg;
        }
    }
    return $arg;
}

function create_link($host, $port = 3306, $user, $pwd, $dbName){
    static $link = NULL;

    $key = implode('_', func_get_args());

    if(isset($link[$key])) return $link[$key];

    try{
        $conn = mysqli_init();
        mysqli_options($conn,MYSQLI_OPT_CONNECT_TIMEOUT, 3);
        mysqli_real_connect($conn, $host, $user, $pwd, $dbName, $port);
        mysqli_query($conn, 'SET NAMES UTF8');
        if($conn) $link[$key] = $conn;
    }catch (Exception $e){
        $msg = get_error_msg($e);
        log_message($msg, LOG_ERR);
    }

    return $link[$key];
}

function ins_aes_encrypt($content, $password)
{
    return AESCipher::getInstance()->encrypt($content, $password);
}

function get_error_msg($e, $cg = FALSE){

    static $levels = array(
        E_ERROR             =>    'Error',
        E_WARNING           =>    'Warning',
        E_PARSE             =>    'Parsing Error',
        E_NOTICE            =>    'Notice',
        E_CORE_ERROR        =>    'Core Error',
        E_CORE_WARNING      =>    'Core Warning',
        E_COMPILE_ERROR     =>    'Compile Error',
        E_COMPILE_WARNING   =>    'Compile Warning',
        E_USER_ERROR        =>    'User Error',
        E_USER_WARNING      =>    'User Warning',
        E_USER_NOTICE       =>    'User Notice',
        E_STRICT            =>    'Runtime Notice'
    );
    $trace = $e->getTrace();
    $trace = $trace[0]['args'];

    $codeMsg = '';
    if ($cg) {
        $code = $trace[0];
        $codeMsg = isset($levels[$code]) ? $levels[$code] : $code . ':';
    }
    $errStr= $trace[1];
    $errFile = $trace[2];
    $errLine = $trace[3];

    $msg = $codeMsg  . $errStr . ' in ' . $errFile  . ' on line ' . $errLine;
    return $msg;
}

/**
 *
 * email发送
 * @param $recevier
 * @param $msg
 * @param array $config
 */
function ins_send_mail($recevier, $subject, $msg, $config)
{
    set_time_limit(6);
    if(empty($recevier))
        return false;
    if (is_string($recevier)) {
        $recevier = explode(",", $recevier);
    }

    $mail = new Mail_PHPMailer();

    $mail->IsSMTP(); // telling the class to use SMTP
    $mail->SMTPSecure = 'ssl';
    $mail->Timeout = 4;

    $lines = preg_split('@\n@u', $msg);
    $subject = d($subject, $lines[0]);
    $ret = '';

    try {
        $mail->Host = $config['host'];
        $mail->SMTPDebug= 1; // enables SMTP debug information (for testing)
        $mail->CharSet = 'UTF-8';
        $mail->SMTPAuth = true;
        $mail->Port = $config['port'];
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->ClearAddresses();
        foreach($recevier as $to)
        {
            $mail->AddAddress($to);
        }
        $mail->SetFrom($config['username'], '测试邮件');
        $mail->Subject = "=?UTF-8?B?".base64_encode($subject)."?=";
        $mail->MsgHTML($msg);

        if($mail->Send()){
            return array("code" => CODE_SUCC, "message" => date("Y-m-d H:i:s")." 成功发送至 ".implode(",", $recevier).". ");
        }else{
            return array("code" => CODE_ERR_SYSTEM, "message" =>  "发送失败" );
        }
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

    if ($level <= $log_level) {
        $log_type = $log_types[$level];
        $msg = date('Y-m-d H:i:s') . ":[{$log_type}] {$msg}\n";
        $fn = date('Ymd') . ".log";

        $logdir = INSTALL_LOG_DIR;

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
    }
}

function array_rand_user($min, $max) {
    $range = range($min, $max);
    shuffle($range);
    $random = array_rand($range);
    return $range[$random];
}