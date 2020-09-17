<?php
namespace MY;

class FilterFactory {
    private static $_FILTERS = [
        'date' => '\MY\Filter\DateFilter',
        'time' => '\MY\Filter\TimeFilter',
        'string' => '\MY\Filter\TextFieldFilter',
        'number' => '\MY\Filter\NumberFilter',
        'macro' => '\MY\Filter\EnumSelectorFilter',
        'enum' => '\MY\Filter\EnumSelectorFilter',
        'enum.multiple' => '\MY\Filter\EnumSelectorFilter',
        'date_range' => '\MY\Filter\DateRangeFilter',
        'time_range' => '\MY\Filter\TimeRangeFilter',
        'combine' => '\MY\Filter\CombineFilter',
        'bool' => '\MY\Filter\CheckboxFilter',
    ];

    public static function register($type, $obj)
    {
        self::$_FILTERS[$type] = $obj;
    }

    public static function isRegistered($type)
    {
        return array_key_exists($type, self::$_FILTERS);
    }

    public static function getFilter($define, $data, $report_options, &$err_msg = '')
    {
        @list($name, $label, $default, $type) = preg_split('@\|@u', $define);

        if (!$name || !preg_match('@^\w+$@', $name))
        {
            $err_msg = "无效的控件名称：" . h($name);

            return FALSE;
        }

        if (!$type) {
            $type = 'date';
        }

        $params = array();

        if (preg_match('@(.+)\((.+)\)@', $type, $ma)) {
            $type = $ma[1];
            if (preg_match('@^ddy_\w+$@', $ma[2], $cb)) {
                $params = call_user_func($cb[0]);
            } else {
                $rawParams = preg_split('@\s*,\s*@u', trim($ma[2]));
                $params = array();
                foreach ($rawParams as $key => $param) {
                    if (strpos($param, ':') !== FALSE) {
                        list($k, $v) = explode(":", $param);
                        $params[$k] = $v === 'true' ? true : $v;
                    } else {
                        $params[$param] = $param;
                    }
                }
            }
        }

        if (preg_match('@^(\w+)((?:\.\w+)*)$@', $type, $ma)) {
            $type = $ma[1];
            $flags = explode('.', trim($ma[2], '.'));
            foreach ($flags as $flag) {
                $params[$flag] = TRUE;
            }
        }

        if (!isset(self::$_FILTERS[$type])) {
            $err_msg = "无效的控件类型：" . h($type);

            return FALSE;
        }

        $class = self::$_FILTERS[$type];

        $filter = new $class([
            'type' => $type,
            'params' => $params,
            'report_options' => $report_options,
            'name' => $name,
            'label' => $label,
            'default' => $default,
            'data' => $data
        ]);

        $filter->filterInit();

        if ($err_msg = $filter->error()) {

            return FALSE;
        }

        return $filter;
    }
}
