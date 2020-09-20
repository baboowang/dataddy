<?php
namespace MY;

trait Plugin_FieldTplRenderTrait
{
    private static $BIG_CACHE = [];

    public function pluginInit($dispatcher, $manager)
    {
        \MY\Data_Template::registerPlugin($this);
    }

    public function field_tpl_data($config_value, $value, $field, $i, $row, $report, $cache_key, $params)
    {
        if (!isset($row[$field])) {
            return '';
        }

        if (!is_array($config_value)) {
            $config_value = [
                'tpl' => is_bool($config_value) || is_numeric($config_value) ? '' : $config_value,
            ];
        }
        if (empty($config_value['tpl'])) {
            $config_value['tpl'] = d(@$params['default_tpl'], '{name}');
        }
        if (empty($config_value['cache_group']) && isset($report['id'])) {
            $config_value['cache_group'] = $report['id'];
        }

        $cache_key .= '.' . ($config_value['cache_group'] ?? '');
        $CACHE = self::getCache($cache_key);

        $pk = d(@$params['pk'], 'id');
        $m = $params['model'];
        $fields = $params['fields'];
        if (empty($CACHE)) {
            $ids = array_filter(array_unique(array_get_column($report['rows'], $field)));
            if ($ids) {
                if (is_numeric($ids[array_rand($ids)])) {
                    $ids = array_map('intval', $ids);
                }
                $rows = $m->select([
                    $pk => $ids,
                ], [
                    'select' => $fields,
                ]);
                if ($params['process_handler']) {
                    $params['process_handler']($rows);
                }
                array_change_key($rows, $pk);
                $CACHE = $rows;
                self::setCache($cache_key, $CACHE);
            }
        }

        $item = $CACHE[$row[$field]] ?? [];
        if (isset($params['render']) and $params['render'] instanceof \Closure) {
            return $params['render']($item);
        }
        if (empty($item)) {
            return $row[$field];
        }

        $tpl = isset($config_value['tpl']) ? $config_value['tpl'] : '';

        $default = isset($config_value['default']) ? $config_value['default'] : '';
        return preg_replace_callback('@\{([\w.]+)(?::(\d+))?(?:\|(.+?))?\}@', function($ma) use($item, $default) {
            $keys = explode('.', $ma[1]);
            $max_len = $ma[2];
            $value = $item;
            foreach ($keys as $key) {
                if (is_array($value) && isset($value[$key])) {
                    $value = $value[$key];
                } else {
                    $value = $ma[3] ?? $default;
                    break;
                }
            }
            if ($max_len) {
                $value = str_truncate($value, $max_len);
            }
            return $value;
        }, $tpl);
    }

    public static function getCache($key)
    {
        log_message("get cache:{$key}", LOG_DEBUG);
        if (isset(self::$BIG_CACHE[$key])) {
            return self::$BIG_CACHE[$key];
        }

        return NULL;
    }

    public static function setCache($key, $data)
    {
        log_message("set cache:{$key}:" . count($data), LOG_DEBUG);
        if (empty($data)) {
            $data['__EMPTY_CACHE'] = TRUE;
        }
        self::$BIG_CACHE[$key] = $data;
    }
}

