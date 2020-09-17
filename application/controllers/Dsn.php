<?php
class DsnController extends MY\Controller_Abstract {

    public function init()
    {
        parent::init();

        $this->form_validation = new \GG\FormValidation();
    }

    public function listJsonAction()
    {
        //@todo 根据权限设定查询条件
        $where = [];
        $attrs = [
            'select' => 'id,name,remark,dsn',
            'order_by' => 'id ASC'
        ];
        $dsn_list = M('dsn')->select($where, $attrs);

        $ref_list = M('menuItem')->select([
            'type' => 'sql',
        ], [
            'select' => 'dsn,count(*) as ct',
            'group_by' => 'dsn'
        ]);

        array_change_key($ref_list, 'dsn');

        foreach ($dsn_list as &$item) {
            $item['ref_count'] = d(@$ref_list[$item['name']], 0);
            $item['dsn'] = aes_decrypt($item['dsn'], \GG\Config::get('secret.key'));
            $item['dsn'] = preg_replace('@password=([^;]+)@', 'password=******', $item['dsn']);
            $item['dsn'] = preg_replace('/\/\/(\w+):([^@]+)@/', '//\1:******@', $item['dsn']);
        }

        R('title', 'DSN列表');

        return response($dsn_list);
    }

    public function detailJsonAction()
    {
        param_request([
            'id' => 'UINT',
        ]);

        $dsn_id = @$GLOBALS['req_id'];

        $data = [];

        if ($dsn_id) {
            $data['dsn'] = M('dsn')->find($dsn_id);

            if ($data['dsn']) {
                $data['dsn']['dsn'] = aes_decrypt($data['dsn']['dsn'], \GG\Config::get('secret.key'));
            }

            R('title', 'DSN更新:' . $data['dsn']['name']);
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

        $dsn = M('dsn')->find($id);

        if (empty($dsn)) {
            return response_error(CODE_ERR_PARAM, '你要删除的DSN不存在');
        }

        M('dsn')->delete(array('id' => $id));

        R('title', 'DSN删除:' . $dsn['name']);

        return response($dsn);
    }

    public function checkDsn($dsn, &$err_msg)
    {
        #user:pass@protocol(proto_opts)/database
        /*if (!preg_match('@\w+:.+\@\w+\(.+?\)/\w+$@', $dsn)) {*/
            //$err_msg = 'DSN格式不正确';

            //return FALSE;
        /*}*/

        return TRUE;
    }

    public function saveAction()
    {
        $rules = [
            'id' => 'int',
            'name|名称' => 'required|trim|alpha_dash|unique_row[dsn.name]',
            'remark|备注' => 'required|trim|strip_tags',
            'dsn|dsn' => 'required|trim|cb_checkDsn',
        ];

        $result = $this->form_validation->check($rules, $this);

        if ($result === FALSE) {

            return response_error(CODE_ERR_PARAM, $this->form_validation->errors());
        }

        $id = @$result['id'];
        unset($result['id']);

        $result['dsn'] = aes_encrypt($result['dsn'], \GG\Config::get('secret.key'));

        $m = M('dsn');
        $ok = TRUE;

        if ($id) {
            $where = array('id' => $id);

            $ok = $m->update($where, $result);
        } else {
            $result['create_account'] = $this->data['me']['username'];
            $id = $m->insert($result, TRUE);

            $ok = !!$id;
        }

        R('title', 'DSN保存:' . $result['name']);

        if ($ok) {
            return response([ 'id' => $id ]);
        } else {
            return response_error(CODE_ERR_SYSTEM);
        }
    }
}
/* End of file Index.php */
