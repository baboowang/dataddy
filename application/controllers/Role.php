<?php
class RoleController extends MY\Controller_Abstract {

    public function init()
    {
        parent::init();

        $this->form_validation = new \GG\FormValidation();
    }

    private function _getRoleTree()
    {
        $attrs = [
            'select' => 'id,name,parent_id',
            'order_by' => 'parent_id ASC,id ASC'
        ];
        $rows = M('role')->select([], $attrs);

        $roles = [];
        $map = [];

        $users = M('user')->select([], ['select' => 'roles']);

        $role_count = [];
        foreach ($users as $user) {
            if ($user['roles']) {
                foreach (explode(',', $user['roles']) as $role_id) {
                    @$role_count[$role_id]++;
                }
            }
        }

        foreach ($rows as &$row) {
            if (!$this->permission->isSubRole($row['id'])) {
                continue;
            }
            $row['writeable'] = $this->permission->isAdmin() ||
                ($this->permission->check('role', 'w') && $this->permission->isSubRole($row['id'], TRUE));
            $row['user_count'] = d(@$role_count[$row['id']], 0);
            $map[$row['id']] = &$row;
            $parent_id = $row['parent_id'];
            if (!$parent_id) {
                $roles[] = &$row;
                continue;
            }

            if (!isset($map[$parent_id])) {
                $roles['p' . $parent_id][] = &$row;
            } else {
                $map[$parent_id]['children'][] = &$row;
            }

            if (isset($roles['p' . $row['id']])) {
                #$child = &$roles['p' . $row['id']];
                #unset($roles['p' . $row['id']]);
                #$row['children'][] = $child;
                $row['children'] = $roles['p' . $row['id']];
                unset($roles['p' . $row['id']]);
            }
        }
        unset($row);

        return $roles;
    }

    public function listJsonAction()
    {
        $roles = $this->_getRoleTree();
        R('title', '角色列表');
        return response($roles);
    }

    protected function getReportTree()
    {
        $where = ['disabled' => 0, /*'visiable' => 1*/];
        $attrs = [
            'select' => 'id,name,type,visiable,parent_id',
            'order_by' => 'sort DESC'
        ];
        $rows = M('menuItem')->select($where, $attrs);

        $tree = [];
        $incomplete_parent_node = [];

        foreach ($rows as $item) {
            if (P("report.{$item['id']}", 'r')) {
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

                $tree[] = $node;
            } else if ($item['parent_id']) {
                $incomplete_parent_node[$item['parent_id']] = TRUE;
            }
        }

        foreach ($tree as &$item) {
            if (isset($incomplete_parent_node[$item['id']])) {
                $item['state'] = [ 'incomplete' => TRUE ];
            }
        }

        return $tree;
    }

    public function detailJsonAction()
    {
        param_request([
            'id' => 'UINT',
        ]);

        $role_id = @$GLOBALS['req_id'];

        $data = [];

        if ($role_id) {
            $data['role'] = M('role')->find($role_id);

            R('title', '角色编辑:' . $data['role']['name']);
        }

        $roles = $this->_getRoleTree();

        $new_roles = [];

        while ($roles) {
            $role = array_shift($roles);
            if (isset($role['children'])) {
                foreach (array_reverse($role['children']) as $item) {
                    $item['prefix'] = @$role['prefix'] . '─';
                    array_unshift($roles, $item);
                }
                unset($role['children']);
            }

            $role['display_name'] = $role['name'];

            if (isset($role['prefix'])) {
                $role['display_name'] = "<span class=\"text-warning\">{$role['prefix']}</span> {$role['name']}";
                unset($role['prefix']);
            }

            $new_roles[] = $role;
        }

        $data['roles'] = $new_roles;

        $data['tree'] = $this->getReportTree();

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

        $role = M('role')->find($id);

        if (empty($role)) {
            return response_error(CODE_ERR_PARAM, '你要删除的角色不存在');
        }

        if (!$this->permission->isAdmin() && !$this->permission->isSubRole($id, TRUE)) {

            return response_error(CODE_ERR_DENY);
        }

        R('title', '角色删除:' . $role['name']);

        M('role')->delete(array('id' => $id));

        return response($role);
    }

    public function saveAction()
    {
        $rules = [
            'id' => 'int',
            'name|角色名称' => 'required|unique_row[role.name]',
            'parent_id|父级节点' => 'int|exists_row[role.id]',
            'config|配置' => 'json',
            'resource|资源' => 'json'
        ];

        $result = $this->form_validation->check($rules, $this);

        if ($result === FALSE) {

            return response_error(CODE_ERR_PARAM, $this->form_validation->errors());
        }

        $id = @$result['id'];
        unset($result['id']);

        $m = M('role');
        $ok = TRUE;
        $err_msg = '';

        if ($id) {
            if (!$this->permission->isAdmin() && $this->permission->isRole($id)) {

                return response_error(CODE_ERR_DENY, '没有权限编辑该角色');
            }

            $permission = new \MY\Permission([[ 'id' => $id ] + $result]);
            if (!$this->permission->contains($permission, $err_msg)) {

                return response_error(CODE_ERR_DENY, '角色更新的权限大于操作者的权限:' . $err_msg);
            }

            if ($result['parent_id'] && $permission->isSubRole($result['parent_id'])) {

                return response_error(CODE_ERR_PARAM, '父级角色不能同时为子级角色');
            }

            $where = array('id' => $id);

            $ok = $m->update($where, $result);
        } else {

            if (!$this->permission->contains([ ['id' => '-1'] + $result ], $err_msg)) {

                return response_error(CODE_ERR_DENY, '新角色权限大于操作者的权限:' . $err_msg);
            }

            $id = $m->insert($result, TRUE);

            $ok = !!$id;
        }

        if ($ok) {
            $role = $m->find($id);
            R('title', '角色保存:' . $role['name']);
            return response([ 'id' => $id ]);
        } else {
            return response_error(CODE_ERR_SYSTEM);
        }
    }
}
/* End of file Index.php */
