<?php

class DataddyPlugin extends \Yaf\Plugin_Abstract {
    public function routerShutdown(\Yaf\Request_Abstract $request, \Yaf\Response_Abstract $response) {

        if (preg_match('@^/%23@', $_SERVER['REQUEST_URI'])) {
            $correct_url = preg_replace('@^/%23@', '/#', $_SERVER['REQUEST_URI']);
            redirect($correct_url);
        }

        $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';

        R('is_ajax', $is_ajax);

        if (strtolower($request->getControllerName()) == 'open') {
            $action = $request->getActionName();
            if ($action != 'download') {
                $_GET['_access_key'] = $request->getActionName();
                $request->setActionName('index');
            }
        }

        if (preg_match('@\.json@', $request->getActionName()) || @$_GET['format'] == 'json') {
            $request->setActionName(preg_replace('@\.json@', 'Json', $request->getActionName()));

            R('need_json', TRUE);
        }

        $module = strtolower($request->module);
        $controller = strtolower($request->controller);
        $action = preg_replace('@json@u', '', strtolower($request->action));
        $method = $request->method;

        if ($controller === 'report' && preg_match('@^(\d+)$@', $action, $ma)) {
            $action = 'index';
            $request->setActionName($action);
            $_GET['id'] = $ma[1];
        }

        $resource = "$controller.$action";
        $mode = 'r';

        if ($module != 'index') {
            $resource .= "$module.$resource";
        }

        if ($method == 'POST' || strpos($action, 'save') !== FALSE || strpos($action, 'version') !== FALSE) {
            $mode = 'rw';
        }

        if ($resource == 'report.save') {
            $resource = 'report.index';
            $mode = 'r';
        }

        param_request([ 'id' => 'UINT' ]);
        $id = @$GLOBALS['req_id'];

        if ($resource == 'report.index' && $id) {
            $resource = "report.{$id}";
        }

        if (preg_match('@^menu\.@', $resource) && $id) {
            $resource = "menu.{$id}";
        }

        if ($controller == 'menu' && $action == 'save' && !$id) {
            param_request([ 'parent_id' => 'UINT' ]);
            $pid = @$GLOBALS['req_parent_id'];
            $resource = "menu.{$pid}";
        }

        //var_dump([$resource, $mode, $this->permission->check($resource, $mode)]);exit;
        if (!R('permission')->check($resource, $mode) && !in_array($resource, ['login.auth', 'dataddy.gettrustticket'])) {
            $login_url = '/login?redirect_uri=' . urlencode($_SERVER['REQUEST_URI']);
            if (R('need_json')) {
                if (!R('user')) {
                    response(['redirectUri' => $login_url], CODE_ERR_AUTH);
                    exit;
                }

                response_error(CODE_ERR_DENY);
                exit;
            } else {
                if (!R('user')) {
                    if ($is_ajax) {
                        echo '<script>window.location="/login?redirect_uri=" + encodeURIComponent(location.href);</script>';
                        exit;
                    } else {
                        redirect($login_url);
                    }
                }

                //@header('HTTP/1.1 403 Forbidden', TRUE, 403);
                $request->setControllerName('Error');
                $request->setActionName('index');
                $request->setParam('err_msg', '访问拒绝');
            }
        }
     }

    public function dispatchLoopShutdown(\Yaf\Request_Abstract $request, \Yaf\Response_Abstract $response) {
        $user = R('user');

        if (!$user) return;

        $use_time = microtime(TRUE) - R('starttime');
        $url = $_SERVER['REQUEST_URI'];
        $name = d(R('title'), '');

        $item = [
            'page_url' => $url,
            'page_name' => $name,
            'start_time' => date('Y-m-d H:i:s', R('starttime')),
            'end_time' => date('Y-m-d H:i:s'),
            'use_time' => $use_time,
            'access_user' => $user['nick'],
        ];

        M('statistic')->insert($item);
    }
 }
/* End of file <`2:filename`>.php */
