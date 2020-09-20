<?php
use GG\Config;

class ConfigModel extends \GG\Db\Model\Base {

    const DEFAULT_NAMESPACE = 'system';
    const DEFAULT_CONFIG = 'system:default';

    public function __construct()
    {
        parent::__construct(
            \GG\Config::get('database.table.prefix', '') . 'config',
            'dataddy'
        );
        $this->addEventHandler(new \GG\Db\DataVersion(new DataVersionModel()));
    }

    public static function get($name, $default = NULL)
    {
        $m = self::getInstance();

        $parts = explode(':', $name, 2);
        $realname = array_pop($parts);
        $namespace = $parts ? array_pop($parts) : self::DEFAULT_NAMESPACE;

        $row = $m->selectOne(array('namespace' => $namespace, 'name' => $realname));

        $ret = $row ? my_json_decode($row['value'], TRUE) : $default;

        return $ret;
    }

    public static function set($name, $value)
    {
        $m = self::getInstance();

        @list($namespace, $realname) = explode(':', $name, 2);

        if (!$realname) {
            $realname = $namespace;
            $namespace = self::DEFAULT_NAMESPACE;
        }

        $data = [
            'namespace' => $namespace,
            'name' => $realname,
            'value' => json_encode($value, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),
        ];

        return $m->replace($data, $data);
    }

    public static function setDefault($key, $value)
    {
        $cfg = self::get(self::DEFAULT_CONFIG);
        $cfg[$key] = $value;

        return self::set(self::DEFAULT_CONFIG, $cfg);
    }
}
/* End of file filename.php */
