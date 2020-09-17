<?php
require dirname(__FILE__) . '/helpers/common.php';
require dirname(__FILE__) . '/helpers/parameter.php';
require dirname(__FILE__) . '/helpers/array.php';
require dirname(__FILE__) . '/helpers/date.php';
require dirname(__FILE__) . '/helpers/string.php';
require dirname(__FILE__) . '/helpers/http.php';
require dirname(__FILE__) . '/helpers/dataddy.php';

class Bootstrap extends Yaf\Bootstrap_Abstract {
    public function _initLocal($dispatcher)
    {
        $loader = Yaf\Loader::getInstance();
        $loader->registerLocalNamespace(array('MY', 'GG', 'PL'));

        GG\Db\Model\Base::setForceReadOnMater();

        class_alias('\GG\Config', 'Config');

        R('starttime', microtime(TRUE));

        $view = new MY\View_Simple(NULL);

        $dispatcher->setView($view);

        Config::add(Yaf\Application::app()->getConfig()->toArray());

        if ($config = \ConfigModel::get('system')) {
            Config::add($config);
        }

        $uid = GG\Session::getInstance()->getUserID();

        //if (!$uid) {
            $ticket = null;
            if (isset($_GET['ticket'])) {
                $ticket = $_GET['ticket'];
            } elseif (preg_match('@ticket=(\w+)@i', @$_SERVER['HTTP_REFERER'], $ma)) {
                $ticket = $ma[1];
            }
            if ($ticket) {
                $ticket_info = \TrustTicketModel::getInstance()->getTicketInfo($ticket, get_client_ip());
                $uid = $ticket_info['uid'] ?? 0;
                R('frame_mode', true);
            }
        //}
        $roles = '';
        $is_admin = FALSE;


        if ($uid && ($user = M('user')->find($uid))) {
            unset($user['password']);
            if (!empty($user['config'])) {
                $user['config'] = my_json_decode($user['config']);
            }
            R('user', $user);
            $roles = $user['roles'];
            $is_admin = $user['is_admin'];
        }

        if ($this->isCli()){
            $roles = '1';
            $is_admin = True;
            R('is_cli', TRUE);
        }

        R('permission', new MY\Permission($roles, $is_admin));

        $dispatcher->registerPlugin(new DataddyPlugin());

        # Plugin register
        $plugin_manager = MY\PluginManager::getInstance();
        $plugin_manager->setDispatcher($dispatcher);
        $plugins = Config::get("plugins", []);

        if (is_string($plugins)) {
            $plugins = explode(",", $plugins);
        }

        foreach ($plugins as $name) {
            $className = trim($name);
            $plugin_manager->register($name);
        }

        error_reporting(E_ALL ^ E_NOTICE);
        ini_set('display_errors', '1');
    }

    private function isCli()
    {
        return (php_sapi_name() === 'cli') ? true : false;
    }

    private function getCliUid()
    {
        return 6;
    }
}
/* End of file <`2:filename`>.php */
