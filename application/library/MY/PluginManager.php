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

    public function getPlugin($name, $withContent = false)
    {
        if (!$this->isRegistered($name)) {
            return null;
        }
        $plugin = $this->_PLUGINS[self::name2Id($name)];
        if ($withContent) {
            $plugin['source'] = $this->getPluginSource($className);
        }
        return $plugin;
    }

    public function registerResource($url, $type = NULL)
    {
        if (is_null($type) && preg_match('@\.(\w+)$@', $url, $ma)) {
            $type = $ma[1];
        }

        if (!$type) {

            return FALSE;
        }

        $this->_RESOURCE[strtolower($type)][] = $url;
    }

    public function getResource()
    {
        $html = [];
        if (isset($this->_RESOURCE['css'])) {
            foreach ($this->_RESOURCE['css'] as $url) {
                $html[] = '<link href="' . $url . '" rel="stylesheet" type="text/css"/>';
            }
        }

        if (isset($this->_RESOURCE['js'])) {
            foreach ($this->_RESOURCE['js'] as $url) {
                $html[] = '<script src="' . $url . '"></script>';
            }
        }

        return implode("\n", $html);
    }

    public function updatePlugin($className, $source)
    {
        $file = $this->classPath($className);
        $dir = dirname($file);
        if (!file_exists($dir)) {
            mkdir($dir);
        }
        return file_put_contents($file, $source, LOCK_EX);
    }

    private function getPluginSource($className)
    {
        $file = $this->classPath($className);
        if (file_exists($file)) {
            return file_get_contents($file);
        }
        return 'plugin file not found';
    }

    private function classPath($className) {
        $pos = strrpos($className, '_');
        $dir = substr($className, 0, $pos);
        $dir = str_replace('_', DIRECTORY_SEPARATOR, $dir);
        $file = substr($className, $pos + 1, strlen($className));

        $filePath = array(
            APPLICATION_PATH,
            'application',
            'library',
            PluginManager::PLUGIN_NAMESPACE,
            strtolower($dir),
            ucfirst(strtolower($file)) . '.php'
        );
        $path = implode(DIRECTORY_SEPARATOR, $filePath);

        return $path;
    }
}
