<?php
class DataddyController extends MY\Controller_Abstract {

    public function indexAction()
    {
    }

    //@todo
    // 目前只支持目录
    public function menuJsonAction()
    {
        //[ "id" => "1", "name" => "Dashboard", "icon" => "", "url" => "", "target" => "", "submenu" : [] ],
        $dashboardmenu = $this->_genDashBoardMenu();
        $reportmenu = $this->_genReportMenu();
        $adminmenu = $this->_genAdminMenu();
        $menu = array_merge($dashboardmenu,$reportmenu,$adminmenu);

        return response($menu);
    }

    protected function _genDashBoardMenu()
    {
        $menu = [];

        if (P('dashboard')) {
            $menu[] = [
                "name" => "Dashboard",
                'url' => '#/dashboard',
                'icon' => 'icon-home',
            ];
        }

        return $menu;
    }

    protected function _genReportMenu()
    {
        $where = ['disabled' => 0, 'visiable' => 1];
        $attrs = [
            'select' => 'id,name,type,visiable,uri, parent_id,settings',
            'order_by' => 'sort desc,id ASC'
        ];
        $menu_list = M('menuItem')->select($where,$attrs);
        $parent_nodes = [];
        $uncle_nodes = [];
        $children_nodes = [];

        $nodes = [];

        if (is_array($menu_list) && !empty($menu_list))
        {
            foreach ($menu_list as $item) {

                if (!P("report.{$item['id']}")) continue;

                $node = [
                    'id' => $item['id'],
                    'parent' => $item['parent_id'],
                    'name' => array_last(preg_split('@/@u', $item['name'])),
                    'type' => $item['type'],
                ];
                if (!empty($item['uri'])) {
                    $node['url'] = $item['uri'];
                }

                if (/*$node['type'] == 'folder' && */$item['settings']) {
                    $settings = json_decode($item['settings'], TRUE);
                    if ($settings && isset($settings['icon'])) {
                        $node['icon'] = $settings['icon'];
                    }
                }

                $nodes[] = $node;
            }
        }

        return $this->_getTree($nodes, '0');
    }

    protected function _getTree($nodes, $pid)
    {
        $tree = [];
        foreach ($nodes as $k => $node)
        {
            if ($node['parent'] == $pid)
            {
                if ($node['type'] == 'folder') {
                    if (!isset($node['icon'])) {
                        $node['icon'] = 'icon-bar-chart';
                    }
                    $node['submenu'] = $this->_getTree($nodes, $node['id']);
                } else if ($node['type'] == 'report') {
                    $node['url'] = '#/report/'.$node['id'];
                } else if ($node['type'] == 'link') {
                    $node['target'] = '_blank';
                }
                $tree[] = $node;
            }
        }
        return $tree;
    }


    protected function _genAdminMenu()
    {
        $menu = [];

        if (P('role')) {
            $menu[] = [ "name" => "角色管理", 'url' => '#/role/list', 'icon' => 'icon-ghost'];
        }

        if (P('user')) {
            $menu[] = [ "name" => "用户管理", 'url' => '#/user/list', 'icon' => 'icon-users' ];
        }

        if (P('menu', 'R')) {
            $menu[] = [ "name" => "菜单管理", 'url' => '#/menu', 'icon' => 'icon-list' ];
        }

        if (P('dsn')) {
            $menu[] = [ "name" => "DSN管理", 'url' => '#/dsn', 'icon' => 'icon-key' ];
        }

        if (P('cron')) {
            $menu[] = [ "name" => "Cron管理", 'url' => '#/cron', 'icon' => 'icon-calendar' ];
        }

        if (P('plugin.list')) {
            $menu[] = [ "name" => "插件管理", 'url' => '#/plugin/list', 'icon' => 'icon-paper-clip' ];
        }

        if (P('config')) {
            $menu[] = [ "name" => "系统配置", 'url' => '#/config', 'icon' => 'icon-settings' ];
        }

        if ($menu) {
            array_unshift($menu, [ "name" => "系统管理", 'type' => 'seprator' ]);
        }

        return $menu;
    }

    public function getTrustTicketAction()
    {
        param_request([
            'user' => 'STRING',
            'client_ip' => 'STRING',
        ]);

        $user = $GLOBALS['req_user'];
        $client_ip = $GLOBALS['req_client_ip'];

        if (empty($user) || empty($client_ip)) {
            return response_error(CODE_ERR_PARAM);
        }

        $server_ip = get_client_ip();
        $trust_servers = Config::get('trust_servers');

        if (empty($trust_servers)
            || !isset($trust_servers[$server_ip])
            || !in_array($user, explode(',', $trust_servers[$server_ip]))
        ) {
            return response_error(CODE_ERR_DENY);
        }

        $ticket = M('trust_ticket')->getTicket($server_ip, $client_ip, $user);

        return response($ticket);
    }

    public function sessionJsonAction()
    {
        if (!isset($this->data['me']['id'])) {

            $data['redirectUri'] = '/login';

            return response($data, CODE_ERR_AUTH);
        }

        $data = $this->data['me'];

        if (!isset($data['config'])) {
            $data['config'] = [];
        }

        if ($data['theme']) {
            $data['config']['theme'] = json_decode($data['theme']);
        }

        if (!isset($data['config']['editor']['vim_mode'])) {
            $data['config']['editor']['vim_mode'] = \GG\Config::get('editor.vim_mode', TRUE);
        }

        return response($data);
    }

    public function themeAction()
    {
        $options = json_encode($_POST);

        if (!isset($this->data['me']['id'])) {
            $data['redirectUri'] = '/login';

            return response($data, CODE_ERR_AUTH);
        }

        M('user')->update(['id' => $this->data['me']['id']], [ 'theme' => $options ]);

        return response($options);
    }

    public function downloadAction()
    {
        if (empty($_POST['data'])) {
            return;
        }

        $lines = preg_split('@\s*</tr>\s*<tr[^>]*>\s*@iu', trim(strip_tags($_POST['data'], '<tr><th><td>')));
        $table = array();
        $fieldRule = array();

        foreach ($lines as $i => $line) {
            $tokens = preg_split('@\s*</t[dh]>\s*@iu', $line);
            $fields = array();
            $colIndex = 0;

            while ($tokens) {

                if (isset($fieldRule[$colIndex]) && $fieldRule[$colIndex]['rowspan'] > 0) {
                    $fields[] = $fieldRule[$colIndex]['value'];
                    $fieldRule[$colIndex]['rowspan']--;
                    $colIndex++;
                    continue;
                }

                $token = array_shift($tokens);

                $field = htmlspecialchars_decode(preg_replace('@^\s+|\s+$@u', '', strip_tags($token)));

                if (preg_match('@rowspan=\D(\d+)\D@', $token, $ma)) {
                    if ($ma[1] > 1) {
                        $fieldRule[$colIndex] = array(
                            'rowspan' => $ma[1] - 1,
                            'value' => $field,
                        );
                    }
                }

                $fields[] = $field;

                $colIndex++;
            }

            $fields = array_map(
                create_function('$s', 'return \'"\' . trim($s) . \'"\';'),
                $fields
            );
            $row = implode(",", $fields);
            if (!preg_match('@^[\s,"]+$@', $row)) {
                $table[] = $row;
            }
        }

        $output = implode("\n", $table);

        header("Content-Type: application/vnd.ms-excel; charset=GB2312");
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header("Content-Disposition: attachment;filename=" . md5($output) . ".csv");
        header("Content-Transfer-Encoding: binary ");
        $output = iconv("utf-8", "gbk//ignore", $output);
        echo $output;

        return FALSE;
    }
}
/* End of file Index.php */
