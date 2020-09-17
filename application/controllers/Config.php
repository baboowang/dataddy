<?php
class ConfigController extends MY\Controller_Abstract {

    public function init()
    {
        parent::init();

        $this->form_validation = new \GG\FormValidation();
    }

    public function listJsonAction()
    {
        //@todo 根据权限设定查询条件
        $where = [ 'namespace' => 'system' ];
        $attrs = [
            'select' => 'id,name,remark',
            'order_by' => 'id ASC'
        ];
        $config_list = M('config')->select($where, $attrs);

        R('title', '配置列表');
        return response($config_list);
    }

    public function detailJsonAction()
    {
        param_request([
            'id' => 'UINT',
        ]);

        $config_id = @$GLOBALS['req_id'];

        $data = [];

        if ($config_id) {
            $data['config'] = M('config')->find($config_id);
            R('title', '配置编辑:' . $data['config']['name']);
        } else {
            R('title', '配置新增');
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
            return $this->_responseError(CODE_ERR_PARAM);
        }

        $config = M('config')->find($id);

        if (empty($config)) {
            return $this->_responseError(CODE_ERR_PARAM, '你要删除的config不存在');
        }

        R('title', '配置删除:' . $config['name']);

        M('config')->delete(array('id' => $id));

        return response($config);
    }

    public function saveAction()
    {
        $rules = [
            'id' => 'int',
            'name|名称' => 'trim|required|alpha_dash|unique_row[config.name]',
            'remark|备注' => 'trim|strip_tags|required',
            'value|值' => 'json',
        ];

        $result = $this->form_validation->check($rules, $this);

        if ($result === FALSE) {

            return $this->_responseError(CODE_ERR_PARAM, $this->form_validation->errors());
        }

        $id = @$result['id'];
        unset($result['id']);

        $result['namespace'] = 'system';

        $m = M('config');
        $ok = TRUE;

        if ($id) {
            $where = array('id' => $id);

            $ok = $m->update($where, $result);
        } else {
            $id = $m->insert($result, TRUE);

            $ok = !!$id;
        }

        R('title', '配置保存:' . $result['name']);

        if ($ok) {
            return response([ 'id' => $id ]);
        } else {
            return response_error(CODE_ERR_SYSTEM);
        }
    }
}
/* End of file Index.php */
