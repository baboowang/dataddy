<?php
namespace GG;

/**
 * 全局配制接口文件
 *
 * 所有与环境相关的配制都集中放在一个配制文件中
 * config_development.php 开发环境的配制文件
 * config_production.php 线上环境的配制文件
 * config_test.php 测试环境的配制文件
 *
 * 程序根据当前服务器的hostname自动读取当前环境的配制文件
 *
 **/
class Config
{
    private static $CONFIG = array();

    /**
     * 添加配制数组
     *
     * @param $config array
     * @return void
     */
    public static function add($config)
    {
        self::$CONFIG = self::_merge($config, self::$CONFIG);
    }

    private static function _merge($source, $target)
    {
        foreach ($source as $key => $val) {
            if ( !is_array($val) || !isset($target[$key]) || !is_array($target[$key])) {
                $target[$key] = $val;
            } else {
                $target[$key] = self::_merge($val, $target[$key]);
            }
        }
        return $target;
    }

    public static function set($key, $val)
    {
        $config = &self::$CONFIG;
        $segments = explode('.', $key);
        $key = array_pop($segments);
        foreach ($segments as $segment) {
            if ( ! isset($config[$segment])) {
                $config[$segment] = array();
            }
            $config = &$config[$segment];
        }
        $config[$key] = $val;
    }

    /**
     * 获取一个配制值
     *
     * @param string $key 配制名, 可包含多级，用 "." 分隔
     * @param string $default default NULL,默认值
     * @return mixed
     */
    public static function get($key = NULL, $default = NULL)
    {
        $config = self::$CONFIG;

        if (!is_null($key)) {
            $path = explode('.', $key);
            foreach ($path as $key) {
                $key = trim($key);
                if (empty($config) || !isset($config[$key])) {
                    return $default;
                }
                $config = $config[$key];
            }
        }

        return $config;
    }

    /**
     * Alias of method get
     */
    public static function g($key, $default = NULL)
    {
        return self::get($key, $default);
    }
}

/**
 * 分库hash函数
 *
 */
function partition_16_by_md5_hash ($objId)
{
    return hexdec(substr(md5($objId), 0, 2)) % 16 + 1;
}

function partition_256_by_md5_hash ($objId)
{
    return hexdec(substr(md5($objId), 0, 2)) + 1;
}

function partition_by_last_3_digits ($objId)
{
    return intval(substr($objId, - 3, 3), 10);
}

function partition_1 ($objId)
{
    return 1;
}
/* End of file <`2:filename`>.php */
