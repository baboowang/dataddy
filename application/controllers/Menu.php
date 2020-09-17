<?php
class MenuController extends MY\Controller_Abstract {

    public function init()
    {
        parent::init();

        $this->form_validation = new \GG\FormValidation();
        $this->_version_table = M('menuItem')->getTable();
    }

    public function listJsonAction()
    {
        //@todo 根据权限设定查询条件
        $where = [ 'disabled' => 0 ];
        $attrs = [
            'select' => 'id,name,type,visiable,parent_id',
            'order_by' => 'sort DESC,id ASC'
        ];
        $menu_list = M('menuItem')->select($where, $attrs);

        $version = $this->getJstreeVersion();

        $menu_tree = [];

        foreach ($menu_list as $item) {
            $required_mode = preg_match('@folder@', $item['type']) ? 'r' : 'rw';

            if (!P("report.{$item['id']}", $required_mode)) {
                continue;
            }

            $node = [
                'id' => $item['id'],
                'parent' => $item['parent_id'],
                'text' => array_last(preg_split('@/@', $item['name'])),
                'type' => $item['type'],
            ];

            if (!$item['visiable']) {
                $node['type'] = 'secret-' . $node['type'];
            }

            if (!$node['parent']) {
                $node['parent'] = '#';
            }

            $menu_tree[] = $node;
        }

        R('title', '菜单列表');

        return response(array("tree" => $menu_tree, "version" => $version));
    }

    public function detailJsonAction()
    {
        param_request([
            'id' => 'UINT',
        ]);

        $menu_id = @$GLOBALS['req_id'];

        $data = [];

        if ($menu_id) {
            $data['menu'] = M('menuItem')->find($menu_id);

            $data['menu']['visiable'] = !!$data['menu']['visiable'];

            if ($data['menu']['dev_uid']) {
                $user = M('user')->find($data['menu']['dev_uid']);
                $data['menu']['dev_user'] = [ 'nick' => $user['nick'] ];
            }

            $data['versions'] = $this->_getVersionList('menuitem', $menu_id);

            R('title', '菜单编辑:' . $data['menu']['name']);
        }

        return response($data);
    }

    public function removeAction()
    {
        param_post([
            'id' => 'UINT',
        ]);

        $id = $GLOBALS['post_id'];

        if (empty($id)) {
            return response_error(CODE_ERR_PARAM);
        }

        $menu = M('menuItem')->find($id);

        if (empty($menu)) {
            return response_error(CODE_ERR_PARAM, '你要删除的menu不存在');
        }

        M('menuItem')->delete(array('id' => $id));

        R('title', '菜单删除:' . $menu['name']);

        return response($menu);
    }

    public function updateTreeAction()
    {
        param_post([
            'tree' => 'STRING',
            'id' => 'UINT',
            'parent' => 'STRING',
            'position' => 'UINT',
            'version' => 'UINT',
        ]);

        $tree_data = json_decode($GLOBALS['post_tree'], true);

        $id = $GLOBALS['post_id'];
        $parent_id = $GLOBALS['post_parent'];
        $position = $GLOBALS['post_position'];
        $version = d(@$GLOBALS['post_version'], 0);

        if (empty($id)) {
            return response_error(CODE_ERR_PARAM);
        }

        $newVersion = $this->checkJstreeVersion($version);
        if (!$newVersion) {
            return response_error(CODE_ERR_PARAM, '目录树在其他地方被修改，请刷新后重试');
        }

        $sort = 100;
        $m = M('menuitem');
        foreach ($tree_data as $node) {
            if ($node['id'] == $parent_id) continue;
            if ($node['parent'] == '#') $node['parent'] = 0;
            $m->update(array("id" => $node['id']), array("parent_id" => $node['parent'], "sort" => $sort--));

        }

        R('title', '菜单位置更新');

        return response(array("version" => $newVersion));
    }

    private function getJstreeVersion()
    {
        $where = array('namespace' => 'system', 'name' => 'jstree');
        $m = M('config');
        $model = $m->selectOne($where);
        if ($model) {
            return $model['value'];
        }

        return 0;
    }

    private function checkJstreeVersion($version)
    {
        $where = array('namespace' => 'system', 'name' => 'jstree');
        $m = M('config');
        $model = $m->selectOne($where);

        if ($model) {
            if ($version >= $model['value']) {
                $m->update($where, array("value" => $version + 1));
                return $version + 1;
            } else {
                return false;
            }
        } else {
            $where['remark'] = '目录树版本';
            $where['value'] = 1;
            $m->insert($where, TRUE);

        }
        return true;
    }

    public function checkMenu($menu, &$err_msg)
    {
        #user:pass@protocol(proto_opts)/database
        if (!preg_match('@\w+:.+\@\w+\(.+?\)/\w+$@', $menu)) {
            $err_msg = 'menu格式不正确';

            return FALSE;
        }

        return TRUE;
    }

    public function validType($type, &$err_msg)
    {
        $types = [ 'report', 'link', 'folder', 'alarm' ];

        if (in_array($type, $types)) {

            return TRUE;
        }

        $err_msg = '不合法的菜单类型';

        return FALSE;
    }

    //@todo
    public function validContent($val, &$err_msg)
    {
        return TRUE;
    }

    //@todo
    public function validDesc($val, &$err_msg)
    {
        return TRUE;
    }

    public function validCron($val, &$err_msg)
    {
        $ok = \MY\Crontab::validate($val);

        if (!$ok) $err_msg = '%s格式错误';

        return $ok;
    }

    public function saveAction()
    {
        param_request([
            'id' => 'UINT'
        ]);

        $id = $GLOBALS['req_id'];

        $rules = [
            'name|名称' => 'strip_tags|trim|required|unique_row[menuItem.name]',
            'type|类型' => 'required|cb_validType',
            'uri|URI' => 'trim|uri|unique_row[menuItem.uri]',
            'dev_content|内容' => 'cb_validContent',
            'dsn|DSN' => 'exists_row[dsn.name]',
            'desc|描述' => 'cb_validDesc',
            'crontab|Cron配置' => 'trim|cb_validCron',
            'settings|配置' => 'json',
            'parent_id|父级节点' => 'exists_row[menuItem.id]',
            'release|是否发布' => 'bool',
            'visiable|用户可见' => 'bool'
        ];

        if ($id) {
            $new_rules = [];

            foreach (array_keys($rules) as $key) {
                $name = preg_split('@\|@u', $key)[0];

                if (isset($_POST[$name])) {
                    $new_rules[$key] = $rules[$key];
                }
            }

            $rules = $new_rules;
        }

        $result = $this->form_validation->check($rules, $this);

        if ($result === FALSE) {
            return response_error(CODE_ERR_PARAM, $this->form_validation->errors());
        }

        $release = $result['release'];
        unset($result['release']);

        if (!$id) {
            $result['visiable'] = $result['visiable'] ? 1 : 0;
            $result['desc'] = d(@$result['desc'], '');
            $result['settings'] = d(@$result['settings'], '');
            $result['mail_receiver'] = d(@$result['mail_receiver'], '');
            $result['mail_memo'] = d(@$result['mail_memo'], '');
        }

        $result['dev_uid'] = $this->data['myuid'];
        $result['dev_version_time'] = date('Y-m-d H:i:s');
        $result['dev_safe_code'] = 0;

        if ($release) {
            $result['content'] = $result['dev_content'];
            $result['safe_code'] = 0;
            $result['release_version_time'] = $result['dev_version_time'];
        }

        $m = M('menuItem');
        $ok = TRUE;

        if ($id) {
            $where = array('id' => $id);

            if (isset($result['crontab'])) {
                $origin = $m->find($id);
            }

            $ok = $m->update($where, $result);

            if ($ok && $origin && $result['crontab'] != $origin['crontab']) {
                $result = \MY\Crontab::build();

                if (!$result) {
                    return response_error(CODE_ERR_SYSTEM, 'CRON生成失败');
                }
            }
        } else {
            $result['create_time'] = '&/CURRENT_TIMESTAMP';
            $id = $m->insert($result, TRUE);

            $ok = !!$id;
        }

        if ($ok) {
            $menu = $m->find($id);

            R('title', '菜单保存:' . $menu['name']);

            $menu['visiable'] = !!$menu['visiable'];
            return response($menu);
        } else {
            return response_error(CODE_ERR_SYSTEM);
        }
    }
}
/* End of file Index.php */
