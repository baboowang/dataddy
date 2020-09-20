<?php
namespace MY;

use ConfigModel;

class Config extends \GG\Config
{
    public static function init()
    {
        self::add(\Yaf\Application::app()->getConfig()->toArray());

        if ($config = ConfigModel::get(ConfigModel::DEFAULT_CONFIG)) {
            self::add($config);
        }
    }

    public static function getEnabledPlugins()
    {
        $plugins = self::get("plugins", []);

        $standard = [];
        if (is_string($plugins)) {
            foreach (explode(",", $plugins) as $def) {
                //bundle_id(scope)
                if (preg_match('@^(.+?)\((.+)\)$@', trim($def), $ma)) {
                    $bundle_id = trim($ma[1]);
                    $scope = trim($ma[2]);
                } else {
                    $bundle_id = trim($def);
                    $scope = preg_match('@sso@i', $bundle_id) ? 'login' : 'report';
                }
                $standard[$bundle_id] = $scope;
            }
        } elseif (is_array($plugins)) {
            foreach ($plugins as $bundle_id => $scope) {
                if (is_int($bundle_id)) {
                    $bundle_id = $scope;
                    $scope = preg_match('@sso@i', $bundle_id) ? 'login' : 'report';
                }
                $standard[$bundle_id] = $scope;
            }
        } else {
            return [];
        }

        return $standard;
    }

    public static function enablePlugin($plugin)
    {
        $enabled_plugins = self::getEnabledPlugins();
        if (!isset($enabled_plugins[$plugin['bundle_id']]) ||
            $enabled_plugins[$plugin['bundle_id']] != $plugin['scope']
        ) {
            $enabled_plugins[$plugin['bundle_id']] = $plugin['scope'];
            self::setEnabledPlugins($enabled_plugins);
        }
    }

    public static function disablePlugin($plugin)
    {
        $enabled_plugins = self::getEnabledPlugins();
        if (isset($enabled_plugins[$plugin['bundle_id']])) {
            unset($enabled_plugins[$plugin['bundle_id']]);
            self::setEnabledPlugins($enabled_plugins);
        }
    }

    public static function setEnabledPlugins(array $plugins)
    {
        return self::setPermanently('plugins', $plugins);
    }

    public static function setPermanently($key, $value)
    {
        ConfigModel::setDefault($key, $value);
    }
}
