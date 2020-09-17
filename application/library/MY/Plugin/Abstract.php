<?php
namespace MY;

abstract class Plugin_Abstract {

    public $id;
    public $class;
    public $author;
    public $since;
    public $version;

    private $auto_setted = FALSE;

    public function __construct($info)
    {
        $this->setInfo($info);
    }

    public abstract function pluginInit($dispatcher, $manager);

    public function getRunningTimeDir()
    {
        return dirname(__FILE__) . '/../../PL/running_time';
    }

    public function writeRunningTimeData($name, $content, $expire = 3600)
    {
        $dir = $this->getRunningTimeDir() . '/' . $this->id;

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, TRUE)) {
                log_message("Create plugin running_time dir error:$dir");

                return FALSE;
            }
        }

        $expire_time = time() + $expire;

        $content = "Expire:$expire_time\n$content";

        return file_put_contents($dir . '/' . md5($name), $content);
    }

    public function getRunningTimeData($name)
    {
        $file = $this->getRunningTimeDir() . '/' . $this->id . '/' . md5($name);

        if (!file_exists($file)) {

            return FALSE;
        }

        $content = file_get_contents($file);

        if (preg_match('@^Expire:(\d+)\n(.+)$@us', $content, $ma)) {
            if (time() >= $ma[1]) {
                unlink($file);

                return FALSE;
            }

            $content = $ma[2];
        }

        return $content;
    }

    public function setInfo($info)
    {
        foreach ($info as $name => $value) {
            $this->$name = $value;
        }
    }

    public function setInfoFromDoc()
    {
        if ($this->auto_setted) return;

        $reflector = new \ReflectionClass($this->class);
        $block = new DocBlock($reflector->getDocComment());

        $this->auto_setted = TRUE;

        foreach (['author', 'version', 'since'] as $name) {
            $this->$name = $block->$name;
        }
    }

    public function hasAdminPage()
    {
        return FALSE;
    }

    public function adminPage($request)
    {
        return '';
    }

    public function install()
    {
        return true;
    }

    public function uninstall()
    {
        return true;
    }
}
