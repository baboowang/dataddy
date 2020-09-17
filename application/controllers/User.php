<?php
class UserController extends MY\Controller_Abstract {

    public function init()
    {
        parent::init();

        $this->form_validation = new \GG\FormValidation();

        if (!$this->permission->isAdmin()) {
            param_request([
                'id' => 'UINT',
            ]);

            if ($GLOBALS['req_id']) {
                $user = M('user')->find($GLOBALS['req_id']);

                if ($user && $user['roles']) {
                    foreach (explode(',', $user['roles']) as $role_id) {
                        if (!$this->permission->isSubRole($role_id, TRUE)) {

                            response_error(CODE_ERR_DENY, '没有权限编辑该用户');
                            exit;
                        }
                    }
                }
            }
        }
    }

    public function listJsonAction()
    {
        //@todo 根据权限设定查询条件
        $session = \GG\Session::getInstance();
        $userId = $session->userID;

        $where = [];
        $user = R('user');
        //非超级管理员
        if ( $user['is_admin'] !== '1'){
            $where = [
                'id' => array(
                    '!=' => $userId
                ),
                'roles' => array(
                    '!=' => $user['roles']
                )
            ];
        }
        
        $attrs = [
            'select' => 'id,username,nick,roles,is_admin,last_login_time',
            'order_by' => 'id ASC'
        ];
        $users = M('user')->select($where, $attrs);

        $roles = M('role')->select();
        array_change_key($roles, 'id');

        foreach ($users as $i => &$user) {
            $user_names = [];
            $ok = TRUE;
            if ($user['roles']) {
                foreach (explode(',', $user['roles']) as $role_id) {
                    if (!$this->permission->isSubRole($role_id, FALSE)) {
                        $ok = FALSE;
                        break;
                    }
                    $user_names[] = @$roles[$role_id]['name'];
                }
            }

            if (!$ok) {
                unset($users[$i]);
                continue;
            }

            $user['role_names'] = implode('、', array_filter($user_names));
        }

        R('title', '用户列表');

        return response(array_merge([], $users));
    }

    public function detailJsonAction()
    {
        param_request([
            'id' => 'UINT',
        ]);

        $user_id = @$GLOBALS['req_id'];

        $data = [];

        if ($user_id) {
            if ($data['user'] = M('user')->find($user_id)) {
                unset($data['user']['password']);

                R('title', '用户编辑:' . $data['user']['nick']);
            }
        }

        $attrs = [
            'select' => 'id,name,parent_id',
            'order_by' => 'parent_id ASC'
        ];
        $rows = M('role')->select([], $attrs);

        $roles = [];
        $map = [];

        foreach ($rows as &$row) {
            if (!$this->permission->isSubRole($row['id'], TRUE)) {
                $row['virtual'] = TRUE;
            }

            if (isset($map[$row['id']])) {
                $origin = $map[$row['id']];
                $map[$row['id']] = &$row;
                $row['children'] = &$origin['children'];
            } else {
                $map[$row['id']] = &$row;
            }

            $parent_id = $row['parent_id'];
            if (!$parent_id) {
                $roles[] = &$row;
                continue;
            }
            $map[$parent_id]['children'][] = &$row;
            unset($row);
        }

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

            if (isset($role['virtual'])) {
                continue;
            }

            $role['display_name'] = $role['name'];

            if (isset($role['prefix'])) {
                $role['display_name'] = "<span class=\"text-warning\">{$role['prefix']}</span> {$role['name']}";
                unset($role['prefix']);
            }

            $new_roles[] = $role;
        }

        $data['roles'] = $new_roles;

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

        $user = M('user')->find($id);

        if (empty($user)) {
            return response_error(CODE_ERR_PARAM, '你要删除的用户不存在');
        }

        M('user')->delete(array('id' => $id));

        R('title', '用户删除:' . $user['nick']);

        return response($user);
    }

    //@todo 和当前用户的角色比较，是否有权限操作
    public function checkRole($role_ids, &$err_msg)
    {
        foreach (explode(',', $role_ids) as $role_id) {
            if (!$this->permission->isSubRole($role_id, TRUE)) {
                $err_msg = '你没有权限设置用户角色';

                return FALSE;
            }
        }

        return TRUE;
    }

    public function saveAction()
    {
        $rules = [
            'id' => 'int',
            'username|用户名' => 'required|alpha_dash|unique_row[user.username]',
            'nick|昵称' => 'required|unique_row[user.nick]',
            'password|密码' => 'safe_password',
            'role_ids|角色' => 'exists_row[role.id]|cb_checkRole',
            'email|邮箱' => 'email',
            'mobile|手机' => 'numeric',
//            'config|配置' => 'json',
        ];

        $result = $this->form_validation->check($rules, $this);

        if ($result === FALSE) {

            return response_error(CODE_ERR_PARAM, $this->form_validation->errors());
        }

        $id = @$result['id'];
        unset($result['id']);

        $result['roles'] = $result['role_ids'];
        unset($result['role_ids']);

        if (!$id && empty($result['password'])) {
            $result['password'] = UserModel::randPassword();
        }

        if (!empty($result['password'])) {
            $result['password'] = UserModel::hash($result['username'], $result['password'], \GG\Config::get('secret.key'));
        }

        if ($id) {
            unset($result['username']);
        }

        $m = M('user');
        $ok = TRUE;

        if ($id) {
            $where = array('id' => $id);

            $ok = $m->update($where, $result);
        } else {
            $id = $m->insert($result, TRUE);

            $ok = !!$id;
        }

        if ($ok) {
            $user = $m->find($id);
            R('title', '用户保存:' . $user['nick']);
            return response([ 'id' => $id ]);
        } else {
            return response_error(CODE_ERR_SYSTEM);
        }
    }
}
/* End of file Index.php */
