<?php

$IF_SAFE_USER = TRUE;

//安装软件
require_once dirname(__FILE__) . '/install.php';

define("APPLICATION_PATH",  dirname(dirname(__FILE__)));

date_default_timezone_set('Asia/Shanghai');

require_once dirname(__FILE__) . '/../vendor/autoload.php';
require_once dirname(__FILE__) . '/../bootstrap.php';

$app  = new Yaf\Application(APPLICATION_PATH . "/conf/application.ini");
$app->bootstrap() //call bootstrap methods defined in Bootstrap.php
    ->run();

/* End of file index.php */
