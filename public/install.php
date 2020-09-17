<?php

if (!isset($IF_SAFE_USER)) {
    header(sprintf("%s: %s", 'Location', '//' . $_SERVER['SERVER_NAME']));
}

!defined('DS') ? define('DS', '/') : '';
define('INSTALL_PATH', dirname(__FILE__) . DS . 'install');

$isOldUser = file_exists(dirname(dirname(__FILE__)) . DS . 'conf' . DS . 'application.ini');

if (!$isOldUser) {
    $step = @trim(file_get_contents(INSTALL_PATH . DS . 'step.txt'));
    if(empty($step) || $step != 'all_install'){
        $step = $step ? $step : 'system';
        header(sprintf("%s: %s", 'Location', '/install/index.php?m='.$step));
        exit(1);
    }
}
