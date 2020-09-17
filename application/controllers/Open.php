<?php
use \GG\Cache\FileCache as FileCache;

class OpenController extends MY\Controller_Abstract {

    public function isAccessable($config)
    {
        if ($config['ip_whitelist'] ?? false) {
            $ok = false;
            $client_ip = get_client_ip();
            foreach ($config['ip_whitelist'] as $ip_def) {
                if ($client_ip === $ip_def) {
                    $ok = true;
                }
            }
            if (!$ok) {
                return false;
            }
        }
        return true;
    }

    public function indexAction()
    {
        param_request([
            '_access_key' => 'STRING',
            'secret' => 'STRING',
        ]);

        $access_key = @$GLOBALS['req__access_key'];
        $format = @$_GET['format'];
        $secret = @$GLOBALS['req_secret'];

        if (!$secret && !empty($_COOKIE['secret'])) {
            $secret = aes_decrypt($_COOKIE['secret'], \GG\Config::get('secret.key'));
        }

        $where = [
            ['uri' => "open:${access_key}" ],
            ['uri' => [ 'like' => "open:${access_key}@%" ]],
            \GG\Db\Sql::LOGIC => 'OR',
        ];

        if (!$access_key || !($info = M('menuItem')->selectOne($where))) {
            header("HTTP/1.1 404 Not Found");
            echo "404 Not Found";
            return FALSE;
        }

        if (in_array($info['type'], ['link', 'folder'])) {
            header("HTTP/1.1 404 Not Found");
            echo "404 Not Found";
            return FALSE;
        }

        $options = my_json_decode($info['settings']);

        $open_config = $options['open'] ?? [];

        if (!$this->isAccessable($open_config)) {
            header("HTTP/1.1 403 Access Forbidden");
            echo "403 Access Forbidden";
            return FALSE;
        }

        $this->data['info'] = $info;

        $options = my_json_decode($info['settings']);

        $options['dsn'] = $info['dsn'];

        if (R('is_cli') || isset($_GET['_disable_cache'])) {
            $options['disable_cache'] = TRUE;
        }

        $engine = new \MY\Data_Template($info['content'], $options, $_GET, R('permission'), $info['safe_code'], $this->filecache);

        $engine->preparse();

        if (is_callable('ddy_open_auth')) {
            $info['uri'] .= '@dynamic:ddy_open_auth';
        }

        if (preg_match('/@(.+)\s*$/', $info['uri'], $ma)) {
            $auth_ok = false;
            if (preg_match('@^dynamic:(\w+)$@', $ma[1], $ma2)) {
                $auth_ok = call_user_func_array($ma2[1], [$secret]);
            } else {
                $auth_ok = $secret === $ma[1];
            }
            if (!$auth_ok) {
                $need_username = strpos($ma[1], ':') !== FALSE;
                if ($secret) {
                    $this->data['error'] = $need_username ? '用户名或密码错误' : '密码错误';
                }
                $this->data['need_username'] = $need_username;
                $this->display('auth', $this->data);
                return FALSE;
            }
            $secret_encrypt = aes_encrypt($secret, \GG\Config::get('secret.key'));
            setcookie('secret', $secret_encrypt, time()+3600, '/', @$_SERVER['HTTP_HOST']);
        }

        try {
            $result = $engine->run();

        } catch (Exception $e) {

            return $this->error($e);
        }

        if ($result == FALSE) {

            return $this->error('报表解析失败');
        }

        if ($engine->is_safe_code != !!$info['safe_code']) {
            M('menuItem')->update(['id' => $info['id']], ['safe_code' => 1*$engine->is_safe_code]);
        }

        $this->data['result'] = &$result;

        if($format == 'json'){
            $rowData = $result['data'];
            //可能有多个报表
            $outputDataJson = array();
            if($rowData){
                foreach($rowData as $data){
                    $outputDataJson[] = $data['rows'];
                }
            }
            echo json_encode($outputDataJson);
            Yaf\Dispatcher::getInstance()->disableView();
        }

        if ($result['type'] == 'raw' || $format == 'raw') {
            echo $result['data'];
            Yaf\Dispatcher::getInstance()->disableView();
        }

        if ($result['type'] == 'html') {
            return;
        }

        foreach ($result['data'] as $report_id => &$report) {
            $chart_options = isset($options['chart']) ? $options['chart'] : [];

            if (isset($options['charts'][$report_id])) {
                $chart_options = $options['charts'][$report_id];
            }

            if (isset($GLOBALS['ddy_chart_options']) && isset($GLOBALS['ddy_chart_options'][$report_id])) {
                if (is_array($GLOBALS['ddy_chart_options'][$report_id])) {
                    $chart_options = array_merge($chart_options, $GLOBALS['ddy_chart_options'][$report_id]);
                } else {
                    $chart_options = $GLOBALS['ddy_chart_options'][$report_id];
                }
            }

            $report['chart_options'] = $chart_options;

            unset($report);
        }

        $this->data['options'] = $result['options'];
    }

    public function downloadAction()
    {
        if (empty($_POST['data'])) {
            return;
        }

        $lines = preg_split('@\s*</tr>\s*<tr[^>]*>\s*@iu', trim(strip_tags($_POST['data'], '<tr><th><td>')));
        $table = array();
        $fieldRule = array();

        foreach ($lines as $i => $line) {
            $tokens = preg_split('@\s*</t[dh]>\s*@iu', $line);
            $fields = array();
            $colIndex = 0;

            while ($tokens) {

                if (isset($fieldRule[$colIndex]) && $fieldRule[$colIndex]['rowspan'] > 0) {
                    $fields[] = $fieldRule[$colIndex]['value'];
                    $fieldRule[$colIndex]['rowspan']--;
                    $colIndex++;
                    continue;
                }

                $token = array_shift($tokens);

                $field = htmlspecialchars_decode(preg_replace('@^\s+|\s+$@u', '', strip_tags($token)));

                if (preg_match('@rowspan=\D(\d+)\D@', $token, $ma)) {
                    if ($ma[1] > 1) {
                        $fieldRule[$colIndex] = array(
                            'rowspan' => $ma[1] - 1,
                            'value' => $field,
                        );
                    }
                }

                $fields[] = $field;

                $colIndex++;
            }

            $fields = array_map(
                create_function('$s', 'return \'"\' . trim($s) . \'"\';'),
                $fields
            );
            $row = implode(",", $fields);
            if (!preg_match('@^[\s,"]+$@', $row)) {
                $table[] = $row;
            }
        }

        $output = implode("\n", $table);

        header("Content-Type: application/vnd.ms-excel; charset=GB2312");
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header("Content-Disposition: attachment;filename=" . md5($output) . ".csv");
        header("Content-Transfer-Encoding: binary ");
        $output = iconv("utf-8", "gbk//ignore", $output);
        echo $output;

        return FALSE;
    }
}
