<?php
namespace MY;

class Data_Template
{
    protected static $_PLUGINS = [];

    public $errors = [];

    public $ignore_data = FALSE;

    protected $_tpl;

    protected $_sandbox;
    protected $_data;
    protected $_filters = [];
    protected $_options = [];
    protected $_macro = [];
    protected $_permission = NULL;
    protected $_cache = [];
    protected $_sql_log = NULL;
    public $is_safe_code = TRUE;

    const ROW_LIMIT = 1000;

    const PRIVATE_KEY = '_';

    public static $instance = NULL;

    public function __construct($tpl, $options, $data = NULL, $permission = NULL, $safe_code = FALSE, $cache = NULL, $sql_log = NULL)
    {
        $this->_tpl = $tpl;

        $this->_options = $options;

        if ($safe_code) {
            $this->_sandbox = new \MY\SafeCodeEngine();
        } else {

            $self = $this;
            $this->_sandbox = new \PHPSandbox\PHPSandbox;
            #$this->_sandbox->error_level = E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_WARNING & ~E_NOTICE;
            #$this->_sandbox->restore_error_level = FALSE;
            $this->_sandbox->set_option(['allow_functions', 'allow_variables', 'allow_escaping', 'allow_closures'], TRUE);
            $this->_sandbox->set_func_validator(function($function_name, \PHPSandbox\PHPSandbox $sandbox) use ($self) {
                $allow_functions = [
                    'print', 'var_dump', 'json_encode', 'json_decode', 'count', 'array', 'sizeof',
                    'is_array', 'is_bool', 'is_numeric', 'is_string', 'trim',
                    'date', 'time', 'strtotime', 'printf', 'sprintf', 'number_format', 'implode', 'explode', 'substr',
                    'preg_match', 'preg_match_all', 'preg_split', 'preg_replace', 'parse_url', 'parse_str', 'http_build_query',
                    'round', 'floatval', 'intval', 'ceil', 'floor', 'rand', 'abs',
                    'usort', 'uasort', 'uksort', 'sort', 'asort', 'arsort', 'ksort', 'krsort','min', 'max',
                    'request_param', 'get_param', 'post_param',
                    'extract', 'in_array', 'mb_strlen', 'mb_substr',
                    'md5', 'base64_encode', 'base64_decode', 'h',
                ];

                if (in_array($function_name, $allow_functions)) {

                    return TRUE;
                }

                if (($functions = \GG\Config::get('allow_functions'))) {
                    if (is_array($functions)) {
                        if (in_array($function_name, $functions)) {

                            return TRUE;
                        }
                    }
                }

                $result = preg_match('@^(ddy|array|str|url)@', $function_name);

                if (!$result) {
                    $self->errors[] = '函数[' . $function_name . ']未通过验证';
                }

                return $result;
            });
            $this->_sandbox->set_error_handler(function($errno, $errstr, $errfile, $errline, $errcontext) {
                //"$errline:$errstr";
                //exit;
                if (preg_match('@Undefined index@', $errstr)) {
                    return;
                }

                throw new \Exception("PHP ERROR:$errstr:$errfile:$errline");
            });
            $this->_sandbox->set_exception_handler(function($e) {
                //echo $e;
                //exit;
                throw $e;
            });
            $this->_sandbox->set_validation_error_handler(function($error){
                if($error->getCode() == \PHPSandbox\Error::PARSER_ERROR){
                    //echo $error;
                    //exit;
                }
                throw $error;
            });
            $this->_sandbox->capture_output = TRUE;
        }
        $this->_data = is_null($data) ? $_GET : $data;
        $this->_permission = $permission;
        $this->_cache = $cache;
        $this->_sql_log = $sql_log;
        self::$instance = $this;

        $this->triggerEvent('template_engine_init', [$this]);
    }

    public function triggerEvent($event_name, $args = [])
    {
        foreach (self::$_PLUGINS as $plugin) {
            $method = get_method($plugin, 'on_' . $event_name);
            if ($method) {
                call_user_func_array([$plugin, $method], $args);
            }
        }
    }

    public static function getInstance()
    {
        return self::$instance;
    }

    public static function registerPlugin(...$plugins)
    {
        self::$_PLUGINS = array_merge(self::$_PLUGINS, $plugins);
    }

    public function plugin_hook_result_response(&$result)
    {
        $key = 'plugin_hook_result_response';
        if (self::$_PLUGINS) {
            foreach (self::$_PLUGINS as $plugin) {
                $method = get_method($plugin, $key);
                if ($method) {
                    $plugin->$method($result);
                }
            }
        }
    }

    public function plugin_hook_before_sql($dsn, $sql, $options, &$error)
    {
        $key = 'plugin_hook_before_sql';
        if (self::$_PLUGINS) {
            foreach (self::$_PLUGINS as $plugin) {
                $method = get_method($plugin, $key);
                if ($method) {
                    if ($plugin->$method($dsn, $sql, $options, $error) === false) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function getErrors()
    {
        if (empty($this->errors)) return '';

        $ret = '';
        foreach (array_unique($this->errors) as $error) {
            $ret .= '<div class="alert alert-danger alert-dismissable">' . $error . '</div>';
        }

        return $ret;
    }

    public function getFilters()
    {
        return $this->_filters;
    }

    private function setErrorHandler()
    {
        $this->origin_error_handler = set_error_handler(function($errno, $errstr, $errfile, $errline, $errcontext) {
            echo "$errline:$errstr";
            //exit;
            switch ($errno) {
                case E_NOTICE:
                case E_WARNING:
                case E_STRICT:
                    return true;
            }
            throw new \Exception("PHP ERROR:$errstr:$errfile:$errline");
        });
    }

    public function run()
    {
        $start_time = microtime(TRUE);

        $content = $this->_parse();

        $parse_time = round((microtime(TRUE) - $start_time) * 1000);

        if ($dy_options = ddy_get_options()) {
            $this->_options = array_merge($this->_options, $dy_options);
        }

        if (!$content) {

            $result = [
                'type' => 'html',
                'options' => $this->_options,
                'data' => d($this->getErrors(), '<p class="text-center">没有指定内容</p>'),
            ];
            $this->plugin_hook_result_response($result);

            return $result;
        }

        if (preg_match('@^\s*data:(\w+)\s*$@u', $content, $data_ma) || preg_match('@^\s*(\[|\{).*?(\]|\})\s*$@us', $content)) {

            if ($data_ma) {
                $data = ddy_get_page_data($data_ma[1]);
            } else {
                $data = @json_decode($content, TRUE);
            }

            if (isset($data[0])) {
                $data = [ $data ];
            }

            if (!is_array($data)) {
                $this->errors[] = '无效的JSON数据';
                $result = [
                    'type' => 'json',
                    'options' => $this->_options,
                    'parse_time' => $parse_time,
                    'data' => [],
                ];
                $this->plugin_hook_result_response($result);

                return $result;
            }

            foreach (array_keys($data) as $report_id) {
                $report = &$data[$report_id];

                if ($report && !isset($report['rows'])) {
                    $report = [ 'rows' => $report, 'id' => $report_id ];
                }

                $ext_options = [];
                if (isset($report['options']) && is_array($report['options'])) {
                    $ext_options = $report['options'];
                }
                $options = $this->getOptions('table', $report_id, $ext_options);
                $report['options'] = $options;

                $start_time = microtime(TRUE);

                $this->_processReport($report);

                $report['process_use_time'] = round((microtime(TRUE) - $start_time) * 1000);

                unset($report);
            }

            $result = [
                'type' => 'json',
                'options' => $this->_options,
                'parse_time' => $parse_time,
                'data' => $data,
            ];

            $this->plugin_hook_result_response($result);

            return $result;
        }

        if (!preg_match('@^\s*SELECT\b@mui', $content) || preg_match('@^#!\w+@u', $content)) {

            $type = 'html';
            if (preg_match('@^#!(\w+)@u', $content, $ma)) {

                $content = preg_replace('@^#!\w+\s*@u', '', $content);

                switch (strtoupper($ma[1])) {
                    case 'MARKDOWN' :
                        $type = 'html';
                        $content= \Michelf\MarkdownExtra::defaultTransform($content);
                        $content = <<<EOT
<link rel="stylesheet" href="/css/github-markdown.css">
<style>.markdown-body pre code{box-shadow:none}</style>
<link rel="stylesheet" href="/css/highlight.default.min.css">
<div class="markdown-body">
$content
</div>
<script src="/js/highlight.min.js"></script>
<script>hljs.initHighlighting();</script>
EOT;
                        break;
                    case 'RAW' :
                        $type = 'raw';
                        break;
                }
            }

            $result = [
                'type' => $type,
                'options' => $this->_options,
                'parse_time' => $parse_time,
                'data' => $content
            ];

            $this->plugin_hook_result_response($result);

            return $result;
        }

        $sqls = preg_split('@;\s*\n@', preg_replace('@/\*.*?\*/@u', '', $content));

        $data = [];
        $real_sql = [];

        $join_report = [];
        $sql_count = count($sqls);
        $default_report_id = 0;
        foreach ($sqls as $sql_i => $sql) {
            $start_time = microtime(TRUE);
            $error = '';

            if ($this->errors) {
                break;
            }

            if ($report = $this->_runSql($default_report_id, $sql)) {

                $report['sql_use_time'] = round((microtime(TRUE) - $start_time) * 1000);

                $start_time = microtime(TRUE);

                $report_id = $report['options']['id'] ?? $default_report_id;

                $real_sql[] = $report['sql'] . ';';

                if (isset($report['options']['union'])) {
                    $report['options']['join'] = (is_array($report['options']['union']) ? $report['options']['union'] : []) + [
                        'union' => true,
                    ];
                }
                if (isset($report['options']['join']) || $join_report) {
                    $join_report[$report_id] = $report;
                } else {
                    $start_time = microtime(TRUE);
                    $this->_processReport($report);
                    $report['process_use_time'] = round((microtime(TRUE) - $start_time) * 1000);
                    $data[$report_id] = $report;
                }
            } else {
                if ($sql) {
                    $real_sql[] = $sql . ';';
                }
            }

            if ($join_report && ($sql_i == $sql_count - 1 || !($report['options']['join'] ?? false))) {
                $report = $this->joinReport($join_report);
                uksort($report['rows'], function($a, $b) {
                    if (!is_numeric($a)) {
                        $a = PHP_INT_MAX;
                    }
                    if (!is_numeric($b)) {
                        $b = PHP_INT_MAX;
                    }

                    if ($a === $b) return 0;
                    return $a - $b > 0 ? 1 : -1;
                });

                $start_time = microtime(TRUE);
                $this->_processReport($report);
                $report['process_use_time'] = round((microtime(TRUE) - $start_time) * 1000);
                $data[$report['id']] = $report;
                $join_report = [];
            }
            $default_report_id++;
        }

        $result = [
            'type' => 'sql',
            'parse_time' => $parse_time,
            'options' => $this->_options,
            'sql' => implode("\n\n", $real_sql),
            'data' => $data,
        ];

        if (is_callable('ddy_dynamic_reports')) {
            $reports = call_user_func_array("ddy_dynamic_reports", [ &$result, $this->_data ]);
            if ($reports) {
                if (!isset($reports[0])) {
                    $reports = [ $reports ];
                }
                foreach ($reports as $report) {
                    $default_report_id = count($result['data']);
                    $start_time = microtime(TRUE);
                    if (!isset($report['options'])) {
                        $report['options'] = [];
                    }
                    if (!isset($report['id'])) {
                        $report['id'] = $default_report_id;
                    }
                    $this->_processReport($report);
                    $report['process_use_time'] = round((microtime(TRUE) - $start_time) * 1000);
                    $result['data'][$report['id']] = $report;
                }
            }
        }

        if (is_callable('ddy_process_result')) {
            call_user_func_array("ddy_process_result", [ &$result, $this->_data ]);
        }

        $this->plugin_hook_result_response($result);

        return $result;
    }

    protected function joinReport($reports)
    {
        $report_ids = array_keys($reports);
        if (!$report_ids) {
            return;
        }

        $result_report = [
            'id' => $report_ids[0],
            'rows' => [],
            'sql' => '',
            'options' => [],
        ];
        $main_dataset = [];
        while (!$main_dataset && $report_ids) {
            $report_id = array_shift($report_ids);
            $report = $reports[$report_id];
            unset($result_report['options']['join']);
            $result_report['options'] = array_merge_recursive_distinct(
                $result_report['options'],
                $report['options'] ?? []
            );
            $result_report['sql'] .= $report['sql'] . ';' . PHP_EOL;
            $main_dataset = $report['rows'];
        }
        $table = $main_dataset ? new Data_ArrayTable($main_dataset) : null;
        while ($table && $report_ids) {
            $report_id = array_shift($report_ids);
            $report = $reports[$report_id];
            $dataset = $report['rows'];
            $join_config = $result_report['options']['join'] ?? [];
            unset($result_report['options']['join']);
            if ($dataset) {
                $join_keys = $join_config['on'] ?? [];
                if (!is_array($join_keys)) {
                    if (is_string($join_keys)) {
                        $join_keys = preg_split('@,@u', $join_keys);
                    } else {
                        $this->errors[] = 'invalid join keys:' . json_encode($join_keys);
                        $join_keys = [];
                    }
                }
                if ($join_config['union'] ?? false) {
                    $table->union($dataset);
                } else {
                    $table->join($dataset, $join_keys, $join_config['full'] ?? false);
                }
            }
            $result_report['options'] = array_merge_recursive_distinct(
                $result_report['options'],
                $report['options'] ?? []
            );
            $result_report['sql'] .= $report['sql'] . ';' . PHP_EOL;
        }
        if ($table) {
            $result_report['rows'] = $table->getDataset();
        }

        if (is_array($result_report['sql_use_time'])) {
            $result_report['sql_use_time'] = array_sum($result_report['sql_use_time']);
        }
        if (is_array($result_report['process_use_time'])) {
            $result_report['process_use_time'] = array_sum($result_report['process_use_time']);
        }

        return $result_report;
    }

    protected function _report($sql, $rows, $options)
    {
        return [
            'sql' => $sql,
            'rows' => $rows,
            'options' => $options
        ];
    }

    protected function _parseSql($sql)
    {
        if (!preg_match('@^\s*(SELECT (.+?) FROM@uis', $sql, $ma)) {

            return NULL;
        }

        preg_match('@[^,]@ui', $ma[1]);
    }

    public function sqlErrorHandler($message, $exception)
    {
        //$this->_sql_error = $message;
        if ($exception) {
            throw $exception;
        }
    }

    public function getOptions($type, $report_id, $ext = [])
    {
        $options = [
            'avg' => FALSE,
            'sum' => TRUE,
            //'plugin_sum' => TRUE,
        ];

        if (isset($this->_options[$type])) {
            $options = array_merge($options, $this->_options[$type]);
        }

        if (isset($this->_options["{$type}s"][$report_id])) {
            $options = array_merge($options, $this->_options["{$type}s"][$report_id]);
        }

        if (isset($GLOBALS['ddy_table_options']) && isset($GLOBALS['ddy_table_options'][$report_id])) {
            $options = array_merge($options, $GLOBALS['ddy_table_options'][$report_id]);
        }

        if ($ext) {
            $options = array_merge_recursive_distinct($options, $ext);
        }

        # 兼容sum插件老版本配置
        $default_sum = [
            'enable' => TRUE,
        ];
        if (!isset($options['plugin_sum']) && !isset($options['plugin_delay_sum'])) {
            $options['plugin_delay_sum'] = $default_sum;
        } else {
            if (!empty($options['plugin_sum']) && !is_array($options['plugin_sum'])) {
                $options['plugin_sum'] = $default_sum;
            }
            if (!empty($options['plugin_delay_sum']) && !is_array($options['plugin_delay_sum'])) {
                $options['plugin_delay_sum'] = $default_sum;
            }
        }
        if (!empty($options['no_delay_sum']) && !empty($options['plugin_delay_sum'])) {
            $options['plugin_sum'] = $options['plugin_delay_sum'];
            unset($options['plugin_delay_sum']);
        }
        $plugin_key = empty($options['plugin_sum']) ? empty($options['plugin_delay_sum']) ? '' : 'plugin_delay_sum' : 'plugin_sum';
        if ($plugin_key) {
            if (!isset($options[$plugin_key]['avg'])) {
                $options[$plugin_key]['avg'] = $options['avg'];
            }
            if (!isset($options[$plugin_key]['sum'])) {
                $options[$plugin_key]['sum'] = $options['sum'];
            }
        }

        return $options;
    }

    protected function _runSql($default_report_id, &$sql)
    {
        static $CONTENT_FOR_CACHE_KEY= '';

        $cmds = [];
        $rows = [];
        $report = [];

        $tokens = array_map('trim',
            preg_split('/^(--\s*@[\w.]+(?:=.*?)?)\s*$/ium', $sql, -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY)
        );

        foreach ($tokens as $token) {

            if (!preg_match('/^--\s*(@[\w.]+)(?:=(.*?))?$/ium', $token, $ma)) {
                continue;
            }

            $value = d(@$ma[2], '');

            if ($value && preg_match('@^\{.+\}$@ui', $value)) {
                $value = @json_decode($value, TRUE);
            } else {
                if (strtolower($value) == 'false') {
                    $value = FALSE;
                }
            }
            $cmds[strtolower(substr($ma[1], 1))] = $value;
        }

        $report_id = d(@$cmds['id'], $default_report_id);
        $options = $this->getOptions('table', $report_id, $cmds);

        $report['id'] = $report_id;

        $sql = $this->_macro(trim($sql));

        ## column config
        if (preg_match_all('@AS\s+([`\'"]?)([^\'"`]+?)\1\s*,?\s*--\s+\@(.+?)\s*$@uim', $sql, $ma)) {
            foreach ($ma[2] as $i => $field) {
                $field_config = $ma[3][$i];
                if (preg_match('@^\{\s*".+\}$@', $field_config)) {
                    $field_config = @json_decode($field_config, TRUE);
                    if (!$field_config) {
                        $report['msg']['error'][] = "字段【{$field}】配置错误:不是合法的JSON";
                        continue;
                    }
                } else {
                    $field_config = [ 'def' => $field_config ];
                }

                $options['fields'][$field] = $field_config;
            }
        }

        $sql = preg_replace('/--\s+@.+$/um', '', $sql);
        $sql = preg_replace('@;\s*$@s', '', $sql);
        $sql = trim(preg_replace('@^--\s.*$@m', '', $sql));

        if(!$sql) return FALSE;

        $cache_time = 0;
        $prefer_cache = TRUE;
        if (isset($this->_options['sql_cache']) && is_numeric($this->_options['sql_cache'])) {
            $cache_time = intval($this->_options['sql_cache']);
        }
        if (isset($options['sql_cache']) && is_numeric($options['sql_cache'])) {
            $cache_time = intval($options['sql_cache']);
        }

        if (isset($this->_options['disable_cache']) && $this->_options['disable_cache']) {
            $prefer_cache = FALSE;
        }

        $CONTENT_FOR_CACHE_KEY .= $sql;

        $cache_key = md5($CONTENT_FOR_CACHE_KEY);

        $dsn = d(@$cmds['dsn'], @$this->_options['dsn'], 'default');
        $db = $this->_getDb($dsn);

        $db->errorHandler = [ $this, 'sqlErrorHandler' ];

        if (!$db) {
            $this->errors[] = "获取不到数据源[{$dsn}]";

            return FALSE;
        }

        if (!$this->ignore_data) {
            $row_count = FALSE;

            if (preg_match('@^select@i', $sql)) {

                $limit = d(@$options['limit'], self::ROW_LIMIT);

                if (!preg_match('@limit@i', $sql)) {
                    $sql .= "\n LIMIT $limit";
                };

                try {

                    if ($prefer_cache && $cache_time > 0 && ($cache_data = $this->_cache->get($cache_key))) {
                        $rows = unserialize($cache_data);
                        $row_count = count($rows);
                    } else {
                        $sql_error = '';
                        if ($this->_sql_log) {
                            if ($this->_sql_log->startSql($sql, $dsn, $sql_error) === false) {
                                throw new \Exception($sql_error);
                            }
                        }
                        if ($this->plugin_hook_before_sql($dsn, $sql, $options, $sql_error) === false) {
                            throw new \Exception($sql_error ?: '插件校验不通过[plugin_hook_before_sql]');
                        }
                        $row_count = $db->select($sql, $rows);
                        if ($this->_sql_log) {
                            $this->_sql_log->endSql($sql);
                        }
                        if ($cache_time > 0) {
                            $this->_cache->set($cache_key, serialize($rows), $cache_time);
                        }
                    }

                    if ($row_count == $limit) {
                        $count_sql = preg_replace('@LIMIT\s+\d+\s*;?\s*\Z@', '', $sql);
                        $count_sql = "SELECT COUNT(*) AS ct FROM ($count_sql) tmp";
                        #ddy_debug($count_sql);
                        #$count_sql = preg_replace('@\A\s*SELECT(.+?)\bFROM\b@iusm', 'SELECT COUNT(*) AS ct FROM', $sql);
                        #$count_sql = preg_replace('@ORDER\s+BY[^\(\)]+\Z@i', '', $count_sql);
                        $total_rows = 0;
                        if ($db->select($count_sql, $count_rows)) {
                            $total_rows = $count_rows[0]['ct'];
                        }
                        $report['msg']['warning'][] = "当前显示并非全部数据，为设置的最大行数：{$limit}。全部记录数为：{$total_rows}。";
                    }

                } catch (\PDOException $e) {
                    if ($this->_sql_log) {
                        $this->_sql_log->endSql($sql, true);
                    }
                    $this->errors[] = 'SQL错误：<pre>' . h($sql) . '</pre>' . $e->getMessage();
                } catch (\Exception $e) {
                    if ($this->_sql_log) {
                        $this->_sql_log->endSql($sql, true);
                    }
                    $this->errors[] = $e->getMessage();
                }

            } else {
                try {
                    $db->query($sql);
                } catch (\Exception $e) {
                    $this->errors[] = 'SQL错误：<pre>' . h($sql) . '</pre>' . $e->getMessage();
                }

                return FALSE;
            }

            if($row_count === FALSE) return FALSE;
        }

        $report['sql'] = $sql;
        $report['rows'] = $rows;
        $report['options'] = $options;

        return $report;
    }

    protected function _getDb($dsn)
    {
        return ddy_db($dsn);
    }

    public function setMacro($name, $value, $quote = TRUE)
    {
        if (!is_array($value)) {
            $value = [ $value ];
        }
        $list = [];
        foreach ($value as $item) {
            $list[] = $quote ? "'" . preg_replace('@\'@u', '\\\'', $item) . "'"  : $item;
        }
        $this->_macro[$name] = implode(',', $list);
    }

    public function getData($name)
    {
        return isset($this->_data["$name"]) ? $this->_data["$name"] : NULL;
    }

    /**
     * e.g.
     * {name} {?name} {?!name} {name1,name2} {name[raw]} {date[+1 day|ymd|raw]}
     * {macro_fun} {macro_fun[params]}
     *
     * macro_fun definition:
     *     default_param1,default_param2,... => ...{1}...{1}...{2}...
     * default_params: 默认参数列表，用半角逗号分隔，如果参数本身包含半角逗号，则用三个连续逗号表示
     * 在函数体中，通过{1}引用第一个参数的值，以此类推
     * e.g. m1 :  col1 => {1} like 'prefix%'
     * {m1} => col1 like 'prefix%'
     * {m1[col2]} => col2 like 'prefix%'
     *
     * special macros:
     * {ROLE_5}
     * {USER_6}
     * {PERM_resource} {RPERM_resource} {WPERM_resource}
     */
    protected function _macro($content)
    {
        $macro_define = $this->_macro + \ConfigModel::get('macro', []);
        $macro_lamda = [];
        $lamda_params_parser = function ($params) {
            return array_map(
                function ($param) {
                    return trim(preg_replace("@\001@", ',', $param));
                },
                preg_split(
                    '@,@u',
                    preg_replace('@,,,@u', "\001", $params)
                )
            );
        };
        $lamda_executor = function ($code, $params) {
            return preg_replace_callback('@\{(\d+)\}@', function ($ma) use ($params) {
                return $params[$ma[1] - 1] ?? '';
            }, $code);
        };
        foreach ($macro_define as $k => $v) {
            if (is_string($v) && preg_match('@^(.+?)\s+=>\s+(.+?)$@', $v, $ma)) {
                if (preg_match('@\{1\}@', $ma[2])) {
                    $macro_lamda[$k] = $ma[2];
                    // 设置默认值
                    $macro_define[$k] = $lamda_executor($macro_lamda[$k], $lamda_params_parser($ma[1]));
                }
            }
        }

        if (!preg_match_all('@(?<!\@)\{(?:(\d+)?(\?!?))?([\w,]+)(?:\[(.+?)\])?\}@', $content, $ma)) {

            return $content;
        }

        foreach ($ma[3] as $i => $name) {

            $pattern = preg_quote($ma[0][$i], '@');

            $names = preg_split('@,@', $name);

            $values = [];
            foreach ($names as $iname) {
                if (!isset($macro_define[$iname])) {
                    if (preg_match('@^ROLE_(\d+)$@', $iname, $_ma)) {
                        $macro_define[$iname] = $this->_permission->containsRole($_ma[1]) ? '1' : '';
                    } else if (preg_match('@^(R?W?)PERM_(.+)$@', $iname, $_ma)) {
                        $macro_define[$iname] = $this->_permission->check($_ma[2], $_ma[1] ? strtolower($_ma[1]) : 'r') ? '1' : '';
                    } else if (preg_match('@^USER_(\d+)$@', $iname, $_ma)) {
                        $macro_define[$iname] = ($this->_permission->isAdmin() || ddy_current_session()['id'] == $_ma[1]) ? '1' : '';
                    }
                }
                if (isset($macro_define[$iname])) {
                    $values[] = trim($macro_define[$iname], "'");
                }
            }

            if (count($names) > 1) {
                $macro_define[$name] = implode('', $values);
                #if ($macro_define[$name] === '') {
                    $macro_define[$name] = "'{$macro_define[$name]}'";
                #}
            }

            $value = $macro_define[$name] ?? null;
            $pipelines = preg_split('@\|@u', isset($ma[4][$i]) ? $ma[4][$i] : '', -1, PREG_SPLIT_NO_EMPTY);

            if (($macro_lamda[$name] ?? false) && $pipelines) {
                $value = $lamda_executor($macro_lamda[$name], $lamda_params_parser(array_shift($pipelines)));
            }

            # 宏条件模式，宏值为空，则删除宏所在行
            if (isset($ma[2][$i]) && ($ma[2][$i] == '?' || $ma[2][$i] == '?!')) {
                $remove_line = !isset($value) || in_array($value, ['', "''", '""', FALSE ], TRUE);

                if ($ma[2][$i] == '?!') {
                    $remove_line = !$remove_line;
                }

                if ($remove_line) {
                    $remove_count = d($ma[1][$i], 1);
                    $addon = '';
                    if ($remove_count > 1) {
                        $addon = '(?:.*?\n){' . ($remove_count - 1) . '}';
                    }
                    $content = preg_replace('@^--\s+' . $pattern  . '(.|\n)*\z@mu', '', $content);
                    $content = preg_replace('@^.*?' . $pattern . '.*?(\n|\z)' . $addon . '@mu', "", $content);
                    continue;
                } else {
                    $content = preg_replace('@--[ \t]+' . $pattern . '@u', '-- ', $content);
                }
            }

            if (!isset($value)) {

                #throw new \Exception("Unknow macro {$ma[0][$i]}");
                continue;
            }
            if ($pipelines) {
                $data = $this->_data;
                foreach ($pipelines as $pipeline) {
                    if ($pipeline === 'raw') {
                        $value = trim($value, "'");
                    } else {
                        //date format
                        $value = preg_replace_callback('@^(\D*)(\d.+\d)(\D*)$@', function ($ma) use ($pipeline) {
                            $fmt_str = '';
                            if (preg_match('@^\w+$@', $pipeline)) {
                                $format_fun = 'ddy_date_format_' . $pipeline;
                                if (is_callable($format_fun)) {
                                    $fmt_str = call_user_func_array($format_fun, [$ma[2]]);
                                }
                            } elseif (preg_match('@^[+-]@', $pipeline)) {
                                $fmt_str = substr(date('Y-m-d H:i:s', strtotime($pipeline, strtotime($ma[2]))), 0, strlen($ma[2]));
                            }
                            if (!$fmt_str) {
                                $fmt_str = date($pipeline, strtotime($ma[2]));
                            }
                            return $ma[1] . $fmt_str . $ma[3];
                        }, $value);
                    }
                }
            }

            log_message('[' . __CLASS__ . "] Process macro {$ma[0][$i]} => {$value}", LOG_DEBUG);

            $content = preg_replace('@' . $pattern . '@u', $value, $content);
        }

        return $content;
    }

    public function preparse()
    {
        static $content = null;

        if (!is_null($content)) {
            return $content;
        }

        $content = $this->_tpl;

        if (!empty($this->_options['ignore_parse'])) {
            return $content;
        }

        if (preg_match('@#+df_start(.*)#+df_end@isu', $content, $ma)) {
            $prepare = $this->_execute('<?php ' . $ma[1] . ' ?>');
            $content = preg_replace('@#+df_start(.*)#+df_end@isu', $prepare, $content);
        }

        return $content;
    }

    public function validate()
    {
        $this->_options['ignore_parse'] = true;
        $this->_parse(true);
    }

    protected function _parse($validate = false)
    {
        $content = $this->preparse();

        # tpl  : ${name|label|default|type(params)}
        # type : date|string|enum(val1,val2,val3)|macro(val1,val2,val3)
        # @todo 类型定义校验
        if (preg_match_all('@(?<!`)\$\{([^}]+)\}@u', $content, $ma)) {
            foreach ($ma[1] as $filter_define) {
                $err_msg = '';
                $filter = FilterFactory::getFilter($filter_define, $this->_data, $this->_options, $err_msg);

                if (!$filter) {
                    throw new \Exception("Filter error {$err_msg}。<span class='text-danger'>" . h($filter_define) . '</span>');
                }

                $names = $filter->getName();
                $values = $filter->getValue();
                if ($filter->error()) {
                    $this->errors[] = '查询条件错误：' . $filter->error();
                    #throw new \Exception('查询条件错误：' . $filter->error());
                }
                if (!is_array($names)) {
                    $names = [ $names ];
                    $values = [$values ];
                }
                foreach ($names as $i => $name) {
                    if (!isset($this->_data[$name]) && $values) {
                        $this->_data[$name] = $values[$i];
                    }
                }

                $replace_value = $filter->getReplaceValue();
                $regexp = '\$\{' . preg_quote($filter_define) . '\}';
                if ($replace_value === '') {
                    $regexp .= ';?';
                }

                $content = preg_replace('@' . $regexp . '@u', $replace_value, $content);

                if ($macro_data = $filter->getMacroData()) {
                    $this->_macro = array_merge($this->_macro, $macro_data);
                }

                $this->_filters[] = $filter;
            }
        }

        R('filters', $this->_filters);

        return $this->_execute($content, $validate);
    }

    protected function _execute($content, $validate = false)
    {
        $segments = preg_split('@(<\?(?:php|=).+?\?>)@msui', $content, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

        $code = [];

        foreach ($segments as $segment) {
            if (!preg_match('@^<\?(php|=)(.+)\?>$@sui', $segment, $ma)) {
                if (!preg_match('@\'@u', $segment)) {
                    $code[] = "echo '$segment';";
                } else {
                    // 会增加源码的行数，导致报错行数不准确
                    $code[] = "echo <<<'EOT'\n$segment\nEOT;\n";
                }
                continue;
            }

            if ($ma[1] == '=') {
                $code[] = "echo {$ma[2]};";
                continue;
            }

            $code[] = $ma[2];
        }

        $code = implode("", $code);

        log_message("execute code:$code", LOG_INFO);

        $result = '';

        try {
            if ($validate) {
                return $this->_sandbox->validate($code);
            } else {
                return $this->_sandbox->execute($code);
            }
        } catch (\Exception $e) {
            if ($validate) {
                throw $e;
            }
            $this->is_safe_code = FALSE;
            $this->errors[] = '执行PHP代码错误：<pre>' . h($code) . '</pre>'
                . $e->getMessage() . '<pre>' . $e . '</pre>';
        }

        return false;
    }

    protected function _processReport(&$report)
    {
        $this->_processFieldDefine($report);

        $options = $report['options'];

        $keys = array_keys($options);
        //if ($key = array_search('plugin_sum', $keys)) {
        //    unset($keys[$key]);
        //    $keys[] = 'plugin_sum';
        //}

        $delay_plugins = [];

        foreach ($keys as $key) {
            if (!preg_match('@^plugin_@', $key) || !$options[$key]) {
                continue;
            }

            $is_custom_plugin = FALSE;
            $is_delay_plugin = preg_match('@^plugin_delay_@', $key);
            $origin_key = $key;
            if ($is_delay_plugin) {
                $key = preg_replace('@^plugin_delay_@', 'plugin_', $key);
            }

            if (self::$_PLUGINS) {
                foreach (self::$_PLUGINS as $plugin) {
                    $method = get_method($plugin, $key);
                    if ($method) {
                        if ($is_delay_plugin) {
                            $delay_plugins[] = [[$plugin, $method], $origin_key];
                        } else {
                            $plugin->$method($report, $report['options'][$key], $this->_data);
                        }
                        $is_custom_plugin = TRUE;
                        break;
                    }
                }
            }

            if (!$is_custom_plugin) {
                if ($method = get_method($this, $key)) {
                    if ($is_delay_plugin) {
                        $delay_plugins[] = [[$this, $method], $origin_key];
                    } else {
                        $this->$method($report, $report['options'][$key], $this->_data);
                    }
                } else if (is_callable("ddy_{$key}")) {
                    if ($is_delay_plugin) {
                        $delay_plugins[] = ["ddy_{$key}", $origin_key];
                    } else {
                        call_user_func_array("ddy_{$key}", [ &$report, $report['options'][$key], $this->_data ]);
                    }
                } else {
                    $report['msg']['error'][] = "未知插件【{$key}】";
                }
            }

            if (!$is_delay_plugin) {
                unset($report['options'][$key]);
            }
        }

        $this->_processFieldConfig($report);
        if ($delay_plugins) {
            foreach ($delay_plugins as $plugin) {
                call_user_func_array($plugin[0], [&$report, $report['options'][$plugin[1]], $this->_data]);
                unset($report['options'][$plugin[1]]);
            }
        }
        $this->_removePrivateColumn($report);
    }

    protected function _processFieldConfig(&$report)
    {
        if (isset($report['options']['fields'])) {
            #ddy_debug($report['options']['fields']);
            foreach ($report['options']['fields'] as $field => $field_config) {
                if (!is_array($field_config)) {
                    $report['msg']['error'] = "未知的字段【{$field}】配置：" . json_encode($field_config);
                    continue;
                }

                foreach ($field_config as $config_name => $config_value) {
                    if (in_array($config_name, ['def', 'count', 'tooltip'])) continue;
                    $method = NULL;

                    $key = "field_" . $config_name;
                    if (self::$_PLUGINS) {
                        foreach (self::$_PLUGINS as $plugin) {
                            if ($method_name = get_method($plugin, $key)) {
                                $method = [ $plugin, $method_name ];
                                break;
                            }
                        }
                    }
                    if (!$method && ($method_name = get_method($this, $key))) {
                        $method = [ $this, $method_name ];
                    }
                    if (!$method && is_callable("ddy_{$key}")) {
                        $method = "ddy_{$key}";
                    }

                    foreach ($report['rows'] as $i => &$row) {
                        if (!isset($row[$field])) {
                            continue;
                        }
                        $value = $row[$field];
                        $attrs = [];
                        if ($method) {
                            $attrs = call_user_func_array(
                                $method,
                                [ $config_value, $value, $field, $i, $row, $report ]
                            );
                            if (!is_array($attrs)) {
                                $attrs = [ 'value' => $attrs ];
                            }
                        } else {
                            $attrs[$config_name] = $this->processRowTpl(
                                $config_value,
                                $row,
                                $config_name == 'href' && strpos($config_value, '{') !== 0,
                                FALSE,
                                $i,
                                $report['rows']
                            );
                        }

                        if (isset($attrs['value'])) {
                            $row[$field] = $attrs['value'];
                            unset($attrs['value']);
                        }

                        if ($attrs) {
                            if (!isset($report['attrs'][$i][$field])) {
                                $report['attrs'][$i][$field] = [];
                            }
                            $report['attrs'][$i][$field] = array_merge($report['attrs'][$i][$field], $attrs);
                        }
                    }
                }
            }
        }
    }

    public function processRowTpl($tpl, $row, $encode = FALSE, $excute = FALSE, $row_index = 0, $rows = NULL)
    {
        if (preg_match_all('@\{([^:}]+)\}@u', $tpl, $ma)) {
            $vars = array_unique($ma[1]);
            $keys = array_keys($row);
            foreach ($vars as $var) {
                @list($var_key, $offset) = preg_split('/@(-?\d+)$/u', $var, -1, PREG_SPLIT_DELIM_CAPTURE);

                if (preg_match('@^\d{1,2}$@', $var_key) && $var_key < count($keys)) {
                    $var_key = $keys[$var_key];
                }

                $replace = '';

                if ($offset) {
                    if (is_numeric($row_index)) {
                        $index = $row_index + intval($offset);
                        if ($rows && isset($rows[$index])) {
                            $replace = $rows[$index][$var_key];
                        }
                    }
                } else {
                    if (isset($row[$var_key])) {
                        $replace = $row[$var_key];
                    }
                }

                if ($excute && $replace === '') {
                    $replace = 0;
                }

                if ($encode) {
                    $replace = urlencode($replace);
                }

                $tpl = preg_replace('/\{' . preg_quote($var) . '\}/', $replace, $tpl);
            }
        }

        if ($excute) {
            try {
                $tpl = $this->_sandbox->execute("echo $tpl;");
            } catch (\Exception $e) {
                $this->is_safe_code = FALSE;
                if (!preg_match('@Division by zero@', $e->getMessage())) {
                    $this->errors[] = '执行PHP代码错误：<pre>' . h("echo $tpl;") . '</pre>'
                        . $e->getMessage() . '<pre>' . $e . '</pre>';
                }
                $tpl = '';
            }
        }

        return $tpl;
    }

    protected function _processFieldDefine(&$report, $index = [])
    {
        if (!isset($report['options']['fields'])) {

            return;
        }

        $keys = $index ? $index : array_keys($report['rows']);

        foreach ($report['options']['fields'] as $field => &$field_config) {
            if (!isset($field_config['def'])) {
                continue;
            }

            $def = $field_config['def'];
            for ($i = 0, $n = count($keys); $i < $n; $i++) {
                $key = $keys[$i];
                if (!isset($report['rows'][$key])) {
                    continue;
                }

                $row = &$report['rows'][$key];

                if ($row[$field] !== '' && is_numeric($key)) {
                    continue;
                }
                $row[$field] = $this->processRowTpl($def, $row, FALSE, TRUE, $key, $report['rows']);
            }
            unset($row);
            //unset($field_config['def']);
        }
    }

    protected function _removePrivateColumn(&$report)
    {
        $private_fields = [];

        if ($report['rows']) {
            foreach ($report['rows'][0] as $field => $val) {
                if (preg_match('@^_@u', $field)) {
                    $private_fields[] = $field;
                }
            }
        }

        if ($private_fields) {
            foreach ($private_fields as $field) {
                foreach ($report['rows'] as &$row) {
                    unset($row[$field]);
                }
            }
        }
    }

    /**
     * 用于过滤结果集的表插件，应用场景？想不起来了，理论结果集查询时已经过滤了？
     *
     * $config = [["col", "operator", "raw_value_or_macro_name"], ...];
     *
     * operator: =, is, >, >=, =, <, <=, !=, in, not in, between
     * 详见：compare方法
     */
    public function plugin_filter(&$report, $config, $data)
    {
        $filters = @json_decode($config, true);
        if (!$filters) {
            return ;
        }
        $rows = [];
        foreach ($report['rows'] as $item) {
            $flag = true;
            foreach ($filters as $filter) {
                $rule_val = $filter[2];
                if (isset($this->_macro[$rule_val])) {
                    $rule_val = $this->_macro[$rule_val];
                }
                $valid = $this->compare($filter[1], $item[$filter[0]] ?? '', $rule_val);
                if ($valid === false) {
                    $flag = false;
                    break;
                }
            }
            if ($flag) {
                $rows[] = $item;
            }
        }
        $report['rows'] = $rows;
    }

    /**
     * @param string $operator
     * @param string|number $current_val 用户实际值
     * @param string|number $rule_val filter限定值/范围
     * @return bool
     */
    public function compare($operator, $current_val, $rule_val)
    {
        switch($operator) {
            case '=':
            case 'is':
                return $current_val == $rule_val;
            case '>':
                return $current_val > $rule_val;
            case '>=':
                return $current_val >= $rule_val;
            case '<':
                return $current_val < $rule_val;
            case '<=':
                return $current_val <= $rule_val;
            case '!=':
                return $current_val != $rule_val;
            case 'in':
                return in_array($current_val, explode(',', $rule_val));
            case 'not in':
                return !in_array($current_val, explode(',', $rule_val));
            case 'between':
                $rule = explode(',', $rule_val);
                if($this->isDateStr($rule[0])) {
                    $rule[0] = strtotime($rule[0]);
                    $rule[1] = strtotime($rule[1]);
                }
                return $current_val > $rule[0] && $current_val < $rule[1];
            default:
                return false;
        }
    }

    public function plugin_sort(&$report, $config, $data)
    {

        $segments = preg_split('@\s*,\s*@u', trim($config));
        $order_list = [];
        foreach ($segments as $segment) {
            $order_type = 'DESC';
            $group_fields = NULL;
            if (preg_match('@^[+-]@u', $segment, $ma)) {
                $segment = preg_replace('@^[+-]@u', '', $segment);
                $order_type = $ma[0] == '+' ? 'ASC' : 'DESC';
            }

            if (preg_match('@\((.+)\)@u', $segment, $ma)) {
                $group_fields = array_map('trim', $group_fields = preg_split('@\s*>\s*@u', $ma[1]));
                $segment = preg_replace('@\(.+\)@u', '', $segment);
            }

            $order_list[] = [
                'field' => trim($segment),
                'group' => $group_fields,
                'type' => $order_type,
            ];
        }

        if (!$order_list) {
            $this->errors[] = '[plugin_sort]插件需要配置排序参数';

            return;
        }

        if (!$report['rows']) {

            return;
        }

        $row = $report['rows'][0];

        foreach ($order_list as &$order_item) {
            $order_field = $order_item['field'];
            if (!isset($row[$order_field])) {
                $this->errors[] = "[plugin_sort]排序字段[{$order_item['field']}]不存在";

                return;
            }

            if ($order_item['group']) {
                foreach ($order_item['group'] as $field) {
                    if ($field !== '$' && !isset($row[$field])) {
                        $this->errors[] = "[plugin_sort]group字段[{$field}]不存在";

                        return;
                    }
                }

                $weight_map = [];

                foreach ($report['rows'] as $i => $row) {
                    $value = 1*$row[$order_field];
                    $ref_weight_map = &$weight_map;

                    foreach ($order_item['group'] as $field) {
                        if ($field === '$') break;
                        $group_value = $row[$field];
                        if (!isset($ref_weight_map[$group_value])) {
                            $ref_weight_map[$group_value]['__total'] = 0;
                        }
                        $ref_weight_map[$group_value]['__total'] += $value;
                        $ref_weight_map = &$ref_weight_map[$group_value];
                    }

                    unset($ref_weight_map);
                }

                $order_item['group_weight'] = $weight_map;
            }
        }
        unset($order_item);
        uasort($report['rows'], function($a, $b) use ($order_list) {

            foreach ($order_list as $order_item) {
                $order_type = $order_item['type'];
                $order_field = $order_item['field'];
                $group_list = $order_item['group'];

                $result = 0;

                if (!$group_list) {
                    if ($a[$order_field] != $b[$order_field]) {
                        $result = $a[$order_field] > $b[$order_field] ? 1 : -1;
                    }
                } else {
                    $weight_a = [];
                    $weight_b = [];

                    $ref_weight_map = &$order_item['group_weight'];
                    foreach ($group_list as $field) {
                        $weight_a[] = $ref_weight_map[$a[$field]]['__total'] * 10;
                        $ref_weight_map = &$ref_weight_map[$a[$field]];
                    }

                    $ref_weight_map = &$order_item['group_weight'];
                    foreach ($group_list as $field) {
                        if ($field === '$') break;
                        $weight_b[] = $ref_weight_map[$b[$field]]['__total'] * 10;
                        $ref_weight_map = &$ref_weight_map[$b[$field]];
                    }

                    unset($ref_weight_map);

                    if ($field !== '$') {
                        $weight_a[] = 1*$a[$order_field];
                        $weight_b[] = 1*$b[$order_field];
                    }

                    foreach ($weight_a as $i => $wa) {
                        $wb = $weight_b[$i];
                        $result = ($wa - $wb);
                        if ($result != 0) break;
                    }

                    $result = $result ? intval($result / abs($result)) : 0;
                }

                $result *= $order_type == 'ASC' ? 1 : -1;

                if ($result != 0) {

                    return $result;
                }
            }

            return 0;
        });
    }

    public function plugin_flip(&$report, $config, $data)
    {
        if (empty($report['rows'])) {
            return;
        }

        if (count($report['rows']) > 50) {
            $this->errors[] = "[PLUGIN_FLIP]数据表行数过多，请控制在50行以内";
            return;
        }

        $plugin_key = empty($options['plugin_sum']) ? empty($options['plugin_delay_sum']) ? '' : 'plugin_delay_sum' : 'plugin_sum';
        if ($plugin_key) {
            $report['options'][$plugin_key]['sum'] = FALSE;
            $report['options'][$plugin_key]['avg'] = FALSE;
        }

        $columns = array_keys($report['rows'][0]);
        $keys = $config && is_array($config) && isset($config['key']) ? preg_split('@,@u', $config['key']) : [];

        if ($keys) {
            foreach ($keys as $key) {
                $index = array_search($key, $columns);
                if ($index === FALSE) {
                    $this->errors[] ="[PLUGIN_FLIP]配置key错误，找不到对应列[$key]";
                    return;
                }
                unset($columns[$index]);
            }
            $columns = array_merge($columns, []);
        }

        $new_rows = [];
        foreach ($report['rows'] as $row) {
            foreach ($columns as $i => $column) {
                if (!isset($new_rows[$i])) {
                    $new_rows[$i] = [];
                }
                if (!isset($new_rows[$i]['名称'])) {
                    $new_rows[$i]['名称'] = $column;
                }
                $key = '数值';
                if ($keys) {
                    $key = [];
                    foreach ($keys as $ikey) {
                        $key[] = $ikey.'['.$row[$ikey].']';
                    }
                    $key = implode(',', $key);
                }
                $new_rows[$i][$key] = $row[$column];
            }
        }

        $report['rows'] = $new_rows;
        $report['fliped'] = TRUE;
    }

    public function plugin_sum(&$report, $config, $data)
    {
        $row_count = count($report['rows']);

        if ($row_count <= 1) return;

        if (!$config['sum'] && !$config['avg']) {

            return;
        }
        $avgPk = '';
        if (isset($report['options']['avg_pk'])) {
            $avgPk = $report['options']['avg_pk'];
        }

        if (!is_array($config)) {
            $config = [];
        }
        $g_data_rule = \ConfigModel::get('data_rule', []);
        $number_fields = d(@$config['fields'], []);
        $group_fields = [];

        if (isset($config['group'])) {
            $group_fields = preg_split('@,@u', $config['group']);

            foreach ($group_fields as $gfield) {
                if (!isset($report['rows'][0][$gfield])) {
                    $this->errors[] = "[PLUGIN_SUM]字段[{$gfield}]不存在";
                    $group_fields = NULL;
                    break;
                }
            }
        }
        foreach ($report['rows'][0] as $field => $value) {
            if (preg_match('@(数|金额)$@ui', $field) || preg_match('@^-?[\d.E]+$@u', $value)) {
                if (!isset($report['options']['fields'][$field]['nan'])) {
                    $number_fields[$field] = TRUE;
                }
                continue;
            }
        }
        $ignore_list = array_filter([@$g_data_rule['sum']['ignore'], @$config['ignore'], join('|', $group_fields)]);

        foreach ($ignore_list as $regexp) {
            foreach (array_keys($number_fields) as $field) {
                if (preg_match('@' . $regexp . '@ui', $field)) {
                    unset($number_fields[$field]);
                }
            }
        }

        foreach (array_keys($number_fields) as $field) {
            $report['options']['fields'][$field]['count'] = TRUE;
        }

        $sum = [];
        $group_sum = [];
        $avgPKs = [];
        foreach ($report['rows'] as $i => $row) {
            if (is_numeric($i)) {
                foreach ($row as $field => $value) {
                    if ($avgPk == $field) {
                        $avgPKs[] = $value;
                    }
                    if (isset($number_fields[$field])) {
                        @$sum[$field] += d($value, 0);
                    } else {
                        $sum[$field] = '';
                    }
                }

                if ($group_fields) {
                    $ref_group_sum = &$group_sum;
                    foreach ($group_fields as $gfield) {
                        foreach ($row as $field => $value) {
                            if (isset($number_fields[$field])) {
                                @$ref_group_sum[$row[$gfield]]['__sum'][$field] += d($value, 0);
                            } else {

                                @$ref_group_sum[$row[$gfield]]['__sum'][$field] = in_array($field, $group_fields) ? $value : '';
                            }
                        }
                        @$ref_group_sum[$row[$gfield]]['__row_count']++;
                        $ref_group_sum = &$ref_group_sum[$row[$gfield]];
                    }
                    unset($ref_group_sum);
                }
            }
        }

        if (!empty($avgPKs)) {
            $row_count = count(array_unique($avgPKs));
        }
        $show_sum = $config['sum'];

        if ($show_sum) {
            if(!isset($report['rows']['sum'])){
                $sum[array_keys($sum)[0]] = '合计';
                $report['rows']['sum'] = $sum;
            }
        }

        //合计也使用平均规则的列
        $avg_fields = $show_sum && !empty($config['avg_fields']) ? $config['avg_fields'] : [];
        if (!is_array($avg_fields)) {
            $avg_fields = preg_split('@,@u', $avg_fields);
        }

        $show_avg = $config['avg'];
        if ($show_avg || $avg_fields) {
            if(!isset($report['rows']['avg'])) {
                $avg = [];
                foreach ($sum as $field => $value) {
                    if (isset($number_fields[$field])) {
                        $avg[$field] = $value / $row_count;
                        if (is_int($value)) {
                            $avg[$field] = (int)round($avg[$field]);
                        }
                    } else {
                        $avg[$field] = '';
                    }
                }
                if ($show_avg) {
                    $avg[array_keys($avg)[0]] = '平均';
                    $report['rows']['avg'] = $avg;
                }
                //@todo
                //如果show_avg==FALSE时，可能会执行多次，导致avg的值不对？
                if ($avg_fields) {
                    foreach ($avg_fields as $field) {
                        if (isset($report['rows']['sum'][$field])) {
                            $report['rows']['sum'][$field] = $avg[$field];
                        }
                    }
                }
            }

            if ($group_fields) {
                $ref_list = [ &$group_sum ];
                while ($ref_list) {
                    $ref_group_sum = &$ref_list[0];
                    array_shift($ref_list);

                    if (isset($ref_group_sum['__sum'])) {
                        $avg = [];
                        foreach ($ref_group_sum['__sum'] as $field => $value) {
                            if (isset($number_fields[$field])) {
                                $avg[$field] = $value / $ref_group_sum['__row_count'];
                                if (is_int($value)) {
                                    $avg[$field] = round($avg[$field]);
                                }
                            } else {
                                $avg[$field] = in_array($field, $group_fields) ? $value : '';
                            }
                        }
                        $ref_group_sum['__avg'] = $avg;

                        //@todo
                        //如果show_avg==FALSE时，可能会执行多次，导致avg的值不对？
                        if ($avg_fields) {
                            foreach ($avg_fields as $field) {
                                if (isset($ref_group_sum['__sum'][$field])) {
                                    $ref_group_sum['__sum'][$field] = $avg[$field];
                                }
                            }
                        }
                    }

                    foreach (array_keys($ref_group_sum) as $key) {
                        if (!preg_match('@^__@u', $key)) {
                            $ref_list[] = &$ref_group_sum[$key];
                        }
                    }
                    unset($ref_group_sum);
                }
            }
        }

        $add_rows = [ 'avg', 'sum' ];

        if ($group_fields) {
            $new_rows = [];
            $group_status = [];

            if (isset($report['rows']['sum'])) {
                $new_rows['sum'] = $report['rows']['sum'];
                unset($report['rows']['sum']);
            }

            if (isset($report['rows']['avg'])) {
                $new_rows['avg'] = $report['rows']['avg'];
                unset($report['rows']['avg']);
            }

            foreach ($report['rows'] as $i => $row) {
                if (is_numeric($i)) {
                    $ref_group_status = &$group_status;
                    $ref_group_sum = &$group_sum;
                    foreach ($group_fields as $gfield) {

                        if (!isset($row[$gfield])) break;

                        $gvalue = $row[$gfield];

                        if (!isset($ref_group_sum[$gvalue])) break;

                        $ref_group_sum = &$ref_group_sum[$gvalue];

                        if (!isset($ref_group_status[$gvalue])) {
                            if (!isset($group_status['__field_next'][$gfield])) {
                                $fields = array_keys($row);
                                $j = array_search($gfield, $fields);
                                if ($j !== FALSE && isset($fields[$j + 1])) {
                                    $group_status['__field_next'][$gfield] = $fields[$j + 1];
                                }
                            }

                            if (isset($ref_group_sum['__sum']) && $ref_group_sum['__row_count'] > 1) {
                                $key = "sum.{$i}.{$gfield}";
                                $new_rows[$key] = $ref_group_sum['__sum'];
                                if (isset($group_status['__field_next'][$gfield])) {
                                    $new_rows[$key][$group_status['__field_next'][$gfield]] = '合计';
                                }
                                $add_rows[] = $key;
                            }

                            if (isset($ref_group_sum['__avg']) && $ref_group_sum['__row_count'] > 1) {
                                $key = "avg.{$i}.{$gfield}";
                                $new_rows[$key] = $ref_group_sum['__avg'];
                                if (isset($group_status['__field_next'][$gfield])) {
                                    $new_rows[$key][$group_status['__field_next'][$gfield]] = '平均';
                                }
                                $add_rows[] = $key;
                            }

                            $ref_group_status[$gvalue] = [];
                        }

                        $ref_group_status = &$ref_group_status[$gvalue];
                    }
                    $new_rows[] = $row;
                } else {
                    $new_rows[$i] = $row;
                }
            }

            $report['rows'] = $new_rows;
        }

        $this->_processFieldDefine($report, $add_rows);
    }

    public function field_ratio($config_value, $value, $field, $i, $row, $report)
    {
        if (!is_numeric($value)) {
            return '-';
        }

        $ratio = $config_value && is_numeric($config_value) ? $config_value : 100;
        return $value / $ratio;
    }

    public function field_date($config_value, $value, $field, $i, $row, $report)
    {
        $format = $config_value && is_string($config_value) ? $config_value : 'Y-m-d H:i:s';

        return date($format, strtotime($value));
    }

    public function field_time2str($config_value, $value, $field, $i, $row, $report)
    {
        if (!$value || !is_numeric($value)) {
            return '-';
        }

        $format = $config_value && is_string($config_value) ? $config_value : 'Y-m-d H:i:s';
        return date($format, $value);
    }
}
/* End of file filename.php */
