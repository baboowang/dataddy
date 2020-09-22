<?php
namespace MY;

class PluginManager {

    const PLUGIN_NAMESPACE = 'PL';

    public $sso_plugin = NULL;

    private $dispatcher;
    private $_PLUGINS = [];
    private $_DATA = [];
    private $_RESOURCE = [];

    private function __construct()
    {
    }

    public static function getInstance()
    {
        static $instance = NULL;

        if (is_null($instance)) {
            $instance = new self();
        }

        return $instance;
    }

    public function setDispatcher($dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function register($name)
    {
        if (!$name) {
            return FALSE;
        }

        $class = '\\' . self::PLUGIN_NAMESPACE . '\\' .  $name;

        if (!class_exists($class)) {
            log_message("Plugin class[$class] not found!", LOG_ERR);

            return FALSE;
        }

        $id = self::name2Id($name);

        $plugin = new $class([
            'id' => $id,
            'name' => $name,
            'class' => $class,
        ]);

        if ($plugin instanceof \MY\Sso_Interface) {
            $this->sso_plugin = $plugin;
        }

        $this->_PLUGINS[$id] = $plugin;

        $plugin->pluginInit($this->dispatcher, $this);

        return TRUE;
    }

    public function isRegistered($name)
    {
        return isset($this->_PLUGINS[self::name2Id($name)]);
    }

    public static function name2Id($name)
    {
        return md5($name);
    }

    public function registerData($plugin, $name, $method)
    {
        $this->_DATA[$plugin->id][$name] = $method;

        $params = [ 'id' => $plugin->id, 'name' => $name ];

        return '/plugin/data?' . http_build_query($params);
    }

    public function getData($id, $name)
    {
        if (!isset($this->_DATA[$id][$name])) {

            return FALSE;
        }

        $plugin = $this->_PLUGINS[$id];

        $method = $this->_DATA[$id][$name];

        return $plugin->$method();
    }

    public function getPlugins()
    {
        return $this->_PLUGINS;
    }

    public function getPlugin($name)
    {
        if (!$this->isRegistered($name)) {
            return null;
        }
        $plugin = $this->_PLUGINS[self::name2Id($name)];

        return $plugin;
    }

    public function registerResource($res, $type = NULL)
    {
        if (is_null($type) && preg_match('@\.(\w+)(?:\?[^/]*)?$@', $res, $ma)) {
            $type = $ma[1];
        }

        if (!$type) {

            return FALSE;
        }

        $this->_RESOURCE[strtolower($type)][] = $res;
    }

    public function getResource()
    {
        $html = [];
        if (isset($this->_RESOURCE['css'])) {
            foreach ($this->_RESOURCE['css'] as $res) {
                if (preg_match('@[{\s;]@', $res)) {
                    $html[] = "<style>\n$res\n</style>";
                } else {
                    $html[] = '<link href="' . $res . '" rel="stylesheet" type="text/css"/>';
                }
            }
        }

        if (isset($this->_RESOURCE['js'])) {
            foreach ($this->_RESOURCE['js'] as $res) {
                if (preg_match('@[({\s;]@', $res)) {
                    $html[] = "<script>\n$res\n</script>";
                } else {
                    $html[] = '<script src="' . $res . '"></script>';
                }
            }
        }

        return implode("\n", $html);
    }

    public static function writePluginSourceFile($name, $source)
    {
        $file = self::pluginPath($name);
        $dir = dirname($file);
        if (!is_writeable(dirname($dir))) {
            throw new \Exception("插件目录{$dir}没有写权限");
        }
        if (!file_exists($dir)) {
            mkdir($dir);
        }

        return file_put_contents($file, $source, LOCK_EX);
    }

    private static function pluginPath($name) {

        return implode(DIRECTORY_SEPARATOR, [
            APPLICATION_PATH,
            'plugins',
            str_replace('\\', DIRECTORY_SEPARATOR, $name) . '.php'
        ]);
    }

    public static function parsePluginContent($content)
    {
        $result = [];

        if (preg_match('/namespace\s+(\S+)/', $content, $ma)) {
            $namespace = trim($ma[1], ';');
        } else {
            throw new \Exception("插件名字空间未定义");
        }

        if (preg_match('/class\s+(\S+)/', $content, $ma)) {
            $class_name = $ma[1];
        } else {
            throw new \Exception("插件类名未定义");
        }

        $result['bundle_id'] = $namespace . '\\' . $class_name;

        $namespace_prefix = '@^\\\\?PL\\\\@';

        if (!preg_match($namespace_prefix, $result['bundle_id'])) {
            throw new \Exception('插件名字空间必须在PL名字空间下');
        }

        if (!preg_match('@^[\\\\\w\d]+$@', $result['bundle_id'])) {
            throw new \Exception("插件类名不合法{$result['bundle_id']}");
        }

        $result['bundle_id'] = preg_replace($namespace_prefix, '', $result['bundle_id']);

        $properties = ['name', 'version', 'author', 'email', 'scope'];

        if (preg_match_all('/^\s*\*\s*@(\w+)\s+(.+?)\s*$/mu', $content, $ma)) {
            foreach ($ma[1] as $i => $p) {
                if (in_array($p, $properties)) {
                    $result[$p] = $ma[2][$i];
                }
            }
        }

        return $result;
    }

    public static function isPluginDirWritealbe()
    {
        $dir = $this->getPluginDir();

        return is_writeable($dir);
    }

    public static function getPluginDir()
    {
        return implode(DIRECTORY_SEPARATOR, [
            APPLICATION_PATH,
            'plugins'
        ]);
    }
}
