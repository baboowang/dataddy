<?php
require_once __dir__ . '/../../application/library/MY/Plugin/Info.php';
use MY\Plugin_Info;

class PluginsUtils{
    public static function getPluginsList($dir = NULL, $isAll = TRUE){

        if(!$dir) $dir = PLUGINS_PATH;

        $plugins = array();
        if(!is_dir($dir)){
            return $plugins;
        }

        self::getDirInfo($dir, '', $isAll, $plugins);
        return $plugins;
    }

    public static function getDirInfo($dir, $prevDir, $isAll, &$files){
        if(is_dir($dir))
        {
            if ($dh = opendir($dir))
            {
                while (($file = readdir($dh)) !== false)
                {
                    if($file!="." && $file!=".."){
                        if(is_dir($dir ."/" . $file)){
                            if($isAll) {
                                self::getDirInfo($dir . DS . $file, $file, $isAll, $files);
                            }
                        }else{
                            if(preg_match('@\.php$@', $file)) {
                                $plugin_info = new Plugin_Info($dir . DS . $file);
                                $pKey = $plugin_info->plugin_name;
                                $files[$pKey] = array(
                                    'name' => $plugin_info->plugin_name,
                                    'mem' => $plugin_info->name,
                                    'isCheck' => $plugin_info->plugin_name === 'Test\\TestFilter',
                                );
                            }
                        }
                    }
                }
                closedir($dh);
                return $files;
            }
        }else{
            $files[$dir] = basename($dir);
            return $files;
        }
    }
}
