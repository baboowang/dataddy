<?php
namespace PL\DDY;

class Report extends \MY\Plugin_Abstract
{
    public function pluginInit($dispatcher, $manager)
    {
        \MY\Data_Template::registerPlugin($this);
    }

    public function field_percent($config_value, $value, $field, $i, $row, $report)
    {
        if (!is_array($config_value)) {
            $config_value = [
                'base' => $config_value,
            ];
        }
        if (!isset($config_value['succ'])) {
            $config_value['succ'] = 70;
        }
        if (preg_match('@^(.+)?\.(.+)$@u', $config_value['base'], $ma)) {
            $base = $report['rows'][$ma[1]][$ma[2]];
        } else {
            $base = $row[$config_value['base']];
        }
        if (!($value && is_numeric($value))) {
            return $value;
        }
        if ($base && is_numeric($base)) {
            $percent = round($value / $base * 100, $config_value['dot'] ?? 0);
        } else {
            $percent = '-';
        }
        $class = 'badge badge-default';
        if (isset($config_value['succ']) && $percent >= $config_value['succ']) {
            $class = 'badge badge-success';
        }
        if ($percent !== '-') {
            $percent .= '%';
        }
        return "{$value} <em><span class='{$class}'>{$percent}</span></em>";
    }
}
