<?php
define("APPLICATION_PATH",  dirname(dirname(dirname(__FILE__))));
define("OPEN_ACCESS_MODE", TRUE);

date_default_timezone_set('Asia/Shanghai');

require_once dirname(__FILE__) . '/../../vendor/autoload.php';

if (!preg_match('@^/open/(download|[0-9a-z]{32}|doc|special)@', $_SERVER['REQUEST_URI'])) {
    header("HTTP/1.1 403 Forbidden");
    die("<h1>403 Forbidden</h1>");
}

$app  = new Yaf\Application(APPLICATION_PATH . "/conf/application.ini");
$app->bootstrap() //call bootstrap methods defined in Bootstrap.php
    ->run();


