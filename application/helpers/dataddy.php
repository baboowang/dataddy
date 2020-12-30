<?php
use GG\Config;

function ddy_hello()
{
    echo "Hello,world!";
}

function ddy_model($table, $dsn = null)
{
    $segments = explode('.', $table);
    if (!$dsn && count($segments) > 1) {
        $cluster_id = $segments[0];
        if (M('dsn')->selectCount(['name' => $cluster_id]) > 0) {
            $dsn = $cluster_id;
            $table = $segments[1];
        }
    }

    $dsn = $dsn ?: 'default';

    $db = ddy_db($dsn);

    if (!$db) {
        return NULL;
    }

    if ($db instanceof \GG\Db\MongoDb) {
        return new \GG\Db\Model\MongoBase($table, 'dataddy_' . $dsn);
    }

    return new \GG\Db\Model\Base($table, 'dataddy_' . $dsn);
}

function ddy_date_format_es($date)
{
    return date('Y-m-d\TH:i:s.000+0800', strtotime($date));
}

function ddy_date_format_es2($date)
{
    return date('Y-m-d\TH:i:s', strtotime($date));
}

function ddy_db($dsn)
{
    if ($dsn == '') {
        $dsn = 'default';
    }

    $cluster_id = 'dataddy_' . $dsn;

    $type = 'mysql';

    if (!\GG\Config::get("db_singles.{$cluster_id}")) {
        $info = M('dsn')->selectOne([ 'name' => $dsn ]);

        if (!$info) {
            throw new \Exception("Unknow dsn {$dsn}");
        }

        $real_dsn = aes_decrypt($info['dsn'], \GG\Config::get('secret.key'));

        $user = $pass = $host = $port = $database = NULL;

        if (preg_match('@^(\w+)\:(.+)\@(\w+)\((.+?)\)/(\w+)$@', $real_dsn, $ma)) {
            list ($full, $user, $pass, $protocol, $uri, $database) = $ma;
            if ($protocol != 'tcp') {

                throw new \Exception("Dsn unsupport protocol : $protocol");
            }
            list ($host, $port) = explode(':', $uri);
            if (!$host || !$port) {

                throw new \Exception("Dsn parse uri error:$uri");
            }
        } else if (!preg_match('@\?@', $real_dsn) && preg_match_all('@(\w+)=([^;]+)@', $real_dsn, $ma)) {
            $params = array();
            foreach ($ma[1] as $i => $name) {
                $params[$name] = $ma[2][$i];
            }
            $user = @$params['user'];
            $pass = @$params['password'];
            $host = @$params['host'];
            $port = @$params['port'];
            $database = @$params['dbname'];

            //if (!($user && $host && $port)) {

                //throw new \Exception("Dsn params error");
            //}

            if (preg_match('@^(\w+):@', $real_dsn, $ma)) {
                $type = $ma[1];
            }
        } else if(preg_match('@^(\w+)://@', $real_dsn, $ma)) {
            $type = $ma[1];
            if ($type !== 'mongodb') {
                throw new \Exception("Dsn unsupport db type: $type");
            }
        }

        if ($type === 'mongodb') {
            $phy_config = [
                'type' => $type,
                'write' => $real_dsn,
            ];
        } elseif ($type === 'es') {
            $phy_config = [
                'type' => $type,
                'write' => $params,
            ];
        } else {
            $phy_config = [
                'write' => [
                    'type' => $type,
                    'host' => $host,
                    'port' => $port,
                ],
                'db_user' => $user,
                'db_pwd' => $pass,
            ];
        }

        \GG\Config::add([
            'db_physical' => [
                $cluster_id => $phy_config,
            ],

            'db_singles' => [
                $cluster_id => [
                    'map' => $cluster_id,
                    'db_name' => $database,
                ]
            ],
        ]);
    } else {
        $c = \GG\Config::get("db_physical.{$cluster_id}");
        if (isset($c['type'])) {
            $type = $c['type'];
        }
    }

    if ($type === 'mongodb') {
        return \GG\Db\MongoDb::getInstance($cluster_id);
    } elseif ($type === 'es') {
        return \GG\Db\Es::getInstance($cluster_id);
    }
    return \GG\Db\GlobalDb::getInstance($cluster_id);
}

function ddy_kw_filter($rows, $columns, $kw, $fullmatch = FALSE)
{
    if ($kw === '') return $rows;

    $ret = [];

    if (!is_array($columns)) {
        $columns = [ $columns ];
    }

    $regexp = preg_quote($kw);

    if ($fullmatch) {
        $regexp = "^{$regexp}$";
    }

    foreach ($rows as $row) {
        foreach ($columns as $column) {
            if (preg_match('@' . $regexp . '@', $row[$column])) {
                $ret[] = $row;
            }
        }
    }

    return $ret;
}

function ddy_macro($name, $value, $quote = TRUE)
{
    if (!is_string($name)) {
        $name = "$name";
    }
    \MY\Data_Template::getInstance()->setMacro($name, $value, $quote);
    return $value;
}

function ddy_data($name, $default = NULL)
{
    $value = \MY\Data_Template::getInstance()->getData($name);

    return is_null($value) ? $default : $value;
}

function ddy_math_exp($exp)
{
    $exp = preg_replace('@\s+@', '', $exp);

    $result = NULL;

    if (preg_match('@^[0-9+\-*/().]+$@', $exp)) {
        @eval('$result=' . $exp . ';');
    }

    return $result;

}

function ddy_set_page_data($data, $name = 'default')
{
    $GLOBALS['ddy_page_data:' . $name] = $data;

    echo 'data:' . $name;
}

function ddy_get_page_data($name = 'default')
{
    return @$GLOBALS['ddy_page_data:' . $name];
}

function ddy_set_table_options($table_id, $options)
{
    return @$GLOBALS['ddy_table_options'][$table_id] = $options;
}

function ddy_set_chart_options($chart_id, $options)
{
    return @$GLOBALS['ddy_chart_options'][$chart_id] = $options;
}

function ddy_json_decode($json, $default = [])
{
    return my_json_decode($json, $default);
}

function ddy_set_options($options, $name = 'default')
{
    if (is_string($options)) {
        $options = json_decode($options, true);

        if (json_last_error()) {
            echo "ddy_set_options JSON format: " . json_last_error_msg();
            return;
        }
    }

    return @$GLOBALS['ddy_option_' . $name] = $options;
}

function ddy_get_options($name = 'default')
{
    return @$GLOBALS['ddy_option_' . $name];
}

function ddy_view_filter()
{
    echo \MY\PluginManager::getInstance()->getResource();

    $filters = R('filters');

    if (!empty($filters)) {
?>
<div class="panel panel-default" style="position:relative;z-index:200">
    <div class="panel-body">
        <form class="form-inline form-search" method="get" action="">

        <?php $filter_names = []; ?>

        <?php foreach ($filters as $filter) :?>
        <?php $filter->display($filter_names); ?>
        <?php endforeach;?>

        <?php foreach ($_GET as $name => $value) :?>
        <?php if (!in_array($name, $filter_names) && strpos($name, '_') !== 0) :?>
        <input type="hidden" name="<?=$name?>" value="<?=h($value)?>"/>
        <?php endif;?>
        <?php endforeach;?>
        <input type="submit" class="btn btn-primary" value="查询"/>
    </form>
    </div>
</div>
<?php
    }
}

function ddy_config($key, $value = NULL) {
    $key = "app:$key";

    if (!is_null($value)) {
        return \ConfigModel::set($key, $value);
    } else {
        return \ConfigModel::get($key);
    }
}

function ddy_register_form_handler($report_id, $handler = NULL)
{
    if (is_null($handler)) {
        $handler = $report_id;
        $report_id = '0';
    }
    $GLOBALS['ddy_page_form_handler'][get_state_string($report_id)] = $handler;

    return '';
}

function ddy_get_form_handler($state)
{
    if (isset($GLOBALS['ddy_page_form_handler'][$state])) {

        return $GLOBALS['ddy_page_form_handler'][$state];
    }

    return NULL;
}

function ddy_current_session()
{
    $user = R('user');

    $session = [];

    if ($user) {
        array_partial_copy($user, $session, [ 'id', 'username', 'nick', 'roles', 'email', 'mobile', 'setting' ]);
    }

    return $session;
}

function ddy_perm_check($resource, $mode = 'r') {
    $permission = R('permission');

    return $permission->check($resource, $mode);
}

function ddy_debug($data)
{
    if (ddy_perm_check('debug')) {
        echo '<pre>';
        var_dump($data);
        echo '</pre>';
    }
}

function ddy_alarm($receivers, $message)
{
    if (!$receivers || !$message) {
        log_message("Alarm data invalid, miss receiver or message!", LOG_ERR);

        return FALSE;
    }

    if (is_array($message)) {
        $message = json_encode($message);
    }

    foreach ($receivers as $type => $type_receiver) {

        if (!$type_receiver) {
            log_message("Alarm data type:{$type} error!", LOG_ERR);

            return FALSE;
        }

        if ($type == 'mail') {
            if (preg_match('@^\s*(\S+)@', strip_tags($message), $ma)) {
                $subject = str_truncate($ma[1], 30);
            }

            send_mail($type_receiver, $subject, $message);
        } else {
            $url = \GG\Config::get("notify.{$type}.url");

            if (!$url) {
                echo "Alarm type:{$type} miss notify url!\n";

                return FALSE;
            }

            $url = preg_replace('@\{receiver\}@', urlencode($type_receiver), $url);
            $url = preg_replace('@\{message\}@', urlencode($message), $url);

            $return_content = @file_get_contents($url);
        }

        $debug_info = '';
        $debug_info .= "----------------------------\n";
        $debug_info .= "TYPE:{$type}\n";
        $debug_info .= "RECEIVER:{$type_receiver}\n";
        $debug_info .= "CONTENT:\n";
        $debug_info .= $message . "\n";
        $debug_info .= "RESPONSE:{$return_content}\n";
        $debug_info .= "----------------------------\n";

        log_message($debug_info, LOG_DEBUG);
    }
}

function ddy_dump(...$arg)
{
    foreach($arg as $index => $value) {
        $id = 'ddy-dump-' . ($index);
        $str = json_encode($value, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        echo <<<HTML
<textarea id="$id" style="width:98%;height:auto">$str</textarea>
<script>
$(function(){
    CodeMirror.fromTextArea($('#$id')[0], {
        mode : { name : "javascript", json : true },
        lineNumbers: true,
        lineWrapping: true,
        matchBrackets: true,
        autoCloseBrackets : true,
        indentWithTabs: true,
        indentUnit: 4,
        extraKeys: {"Cmd-/": "toggleComment", "Ctrl-/":"toggleComment"}
    });
});
</script>
HTML;
    }
}

function ddy_data_dict($db_name, $table_names, $options = [], $dsn = 'default')
{
    $db = ddy_db($dsn);
    if (!preg_match('@^\w+$@', $db_name)) {
        return;
    }

    $tables = [];
    $ignore_tables = isset($options['ignore_tables']) ? $options['ignore_tables'] : [];
    if (!is_array($ignore_tables)) {
        $ignore_tables = explode(',', $ignore_tables);
    }
    if (!$table_names || $table_names === '*') {
        $res = [];
        $db->select("SHOW TABLES IN $db_name", $res);
        foreach ($res as $item) {
            $table_name = array_values($item)[0];
            if (in_array($table_name, $ignore_tables)) {
                continue;
            }
            $tables[]['TABLE_NAME'] = $table_name;
        }
    } else {
        if (!preg_match('@^[\w,]+$@', $table_names)) {
            return;
        }
        foreach (explode(',', $table_names) as $table_name) {
            $tables[]['TABLE_NAME'] = $table_name;
        }
    }

    foreach($tables as $k => $v){
        $sql = 'SELECT * FROM ';
        $sql .= 'INFORMATION_SCHEMA.TABLES ';
        $sql .= 'WHERE ';
        $sql .= "table_name = '{$v['TABLE_NAME']}'  AND table_schema = '{$db_name}'";
        $table_result = [];
        $db->select($sql, $table_result);
        $tables[$k]['TABLE_COMMENT'] = $table_result[0]['TABLE_COMMENT'];

        $sql = 'SELECT * FROM ';
        $sql .= 'INFORMATION_SCHEMA.COLUMNS ';
        $sql .= 'WHERE ';
        $sql .= "table_name = '{$v['TABLE_NAME']}' AND table_schema = '{$db_name}'";

        $fields = array();
        $res = [];
        $db->select($sql, $res);
        $tables [$k] ['COLUMN'] = array_values($res);
    }

    $content = '';
    // 循环所有表
    $collapse_title = 'collapse';
    $collapse_body = '';
    $collapse_style = '';

    /*
    if (count($tables) > 3) {
        $collapse_title = 'expand';
        $collapse_body = 'portlet-collapsed';
        $collapse_style = 'display:none';
    }
     */

    foreach($tables as $k => $v){
        $content .= <<<EOT
<div class="portlet light bg-inverse">
            <div class="portlet-title">
                <div class="caption font-green-haze">
                    <i class="icon-paper-plane font-green-haze"></i>
                    <span class="caption-subject bold uppercase">#{$v ['TABLE_NAME']}</span>
                    <span class="caption-helper">{$v ['TABLE_COMMENT']}</span>
                </div>
                <div class="tools">
                    <a href="javascript:;" class="{$collapse_title}"> </a>
                    <a href="" class="fullscreen"> </a>
                </div>
            </div>
            <div class="portlet-body {$collapse_body}" style="{$collapse_style}">
            <table class="table table-striped table-hover">
EOT;
        $content .= '<tbody><tr><th>字段名</th><th>数据类型</th><th>默认值</th>
        <th>允许非空</th>
        <th>自动递增</th><th>备注</th></tr>';
        $content .= '';

        foreach($v ['COLUMN'] as $f){
            $content .= '<tr><td class="bold">' . $f ['COLUMN_NAME'] . '</td>';
            $content .= '<td class="c2">' . $f ['COLUMN_TYPE'] . '</td>';
            $content .= '<td class="c3">&nbsp;' . $f ['COLUMN_DEFAULT'] . '</td>';
            $content .= '<td class="c4">&nbsp;' . $f ['IS_NULLABLE'] . '</td>';
            $content .= '<td class="c5">' . ($f ['EXTRA'] == 'auto_increment' ? '是' : '&nbsp;') . '</td>';
            $content .= '<td class="c6">&nbsp;' . $f ['COLUMN_COMMENT'] . '</td>';
            $content .= '</tr>';
        }
        $content .= '</tbody></table></div></div>';
    }

    echo $content;
}

function ddy_memory_convert($size)
{
    try {
        return @(round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i]);
    } catch (Exception $e) {
        return '';
    }
}
