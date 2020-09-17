<?php
namespace MY;

class Controller_Abstract extends \Yaf\Controller_Abstract {

    protected $_page_path = array();

    protected $_version_table = NULL;

    protected $send_by_mail = false;
    protected $mail_config = array();

    public $data = array();
    public $permission;

    public $filecache;

    public function init()
    {
        # 权限控制见 plugins/Dataddy.php
        $is_login = $this->_checkLogin();
        $this->data['page_path'] = &$this->_path_path;
        $this->permission = R('permission');
        $this->filecache = new \GG\Cache\FileCache(APPLICATION_PATH . '/application/cache');
    }

    protected function _checkLogin()
    {
        if ($me = R('user')) {
            $this->data['myuid'] = $GLOBALS['uid'] = $me['id'];
            $this->data['me'] = $me;

            return TRUE;
        }

        return FALSE;
    }

    protected function _getVersionList($table_name, $pk)
    {
        $attrs = [
            'select' => 'd.id, d.create_time, d.user_id, d.db_name, d.table_name, d.data, d.pk, u.nick',
            'order_by' => 'create_time desc',
            'limit' => 10
        ];
        $where = [
            'db_name' => \GG\Config::get('db_singles.dataddy.db_name'),
            'table_name' => \GG\Config::get('database.table.prefix', '') . $table_name,
            'pk' => $pk
        ];
        $db_model = new \GG\Db\Model\Base('data_version as d left join user u on d.user_id = u.id ', 'dataddy');
        $rows = $db_model->select($where, $attrs);

        return $rows;
    }

    protected function _getUserInfo($uid)
    {

    }

    protected function _setTitle($title, $sub_title = NULL)
    {
        $this->_view->title = $title;

        if (!is_null($sub_title)) {
            $this->_setSubTitle($sub_title);
        }
    }

    protected function _setSubTitle($sub_title)
    {
        $this->_view->sub_title = $sub_title;
    }

    protected function _appendPagePath(...$path_list)
    {
        foreach ($path_list as $path) {
            if (!is_array($path)) {
                $path = array('text' => $path);
            }
            array_push($this->_page_path, $path);
        }
    }

    protected function _response($data, $code = CODE_SUCC, $message = '')
    {
        return response($data, $code, $message);
    }

    protected function _responseError($code, $message = '')
    {
        return response_error($code, $message);
    }

    public function error($error, $ext_content = '')
    {
        if ($error == '404') {
            //@header('HTTP/1.1 404 Not Found', TRUE, 404);
            $this->forward('error', 'notFound');
        } else if (is_string($error)) {
            //@header('HTTP/1.1 400 Bad Request', TRUE, 400);
            $this->forward('error', 'index', [ 'err_msg' => $error, 'ext_content' => $ext_content ]);
        } else {
            //@header('HTTP/1.1 500 Internal Server Error', TRUE, 500);
            $this->forward('error', 'index', [ 'exception' => $error, 'ext_content' => $ext_content ]);
        }

        return FALSE;
    }

    public function render($tpl, array $tpl_vars = NULL)
    {
        if (is_null($tpl_vars)) {
            $tpl_vars = array();
        }

        $tpl_vars = array_merge($tpl_vars, $this->data);

        $content = parent::render($tpl, $tpl_vars);

        if ($this->send_by_mail) {
            if (!empty($this->mail_config['receiver'])) {
                $receiver = $this->receiverNormalize($this->mail_config['receiver']);
                if (empty($receiver)) {

                    return response_error(CODE_ERR_PARAM, "没有指定的收件人");
                }
                $subject = @$this->mail_config['subject'];
                $ret = send_mail($receiver, $subject, $content);

                return response(array(), $ret['code'],  $ret['message']);
            } else {

                return response_error(CODE_ERR_PARAM, "请先配置mail.receiver");
            }
        } else {
            return $content;
        }
    }

    private function receiverNormalize($receiver)
    {
        if (is_string($receiver)) {
            $receiver = explode(",", $receiver);
        }
        if (!is_array($receiver)) return '';
        $result = array();
        $username = array();
        foreach ($receiver as $r) {
            if (strpos($r, '@') === false) {
                $username[] = $r;
            } else {
                $result[] = $r;
            }
        }
        $user = M('user')->select(array('username' => array("in", $username)), array('email'));
        $emails = array_get_column($user, 'email');

        return array_merge($result, $emails);
    }

    protected function _getPostParams($names, $def = array(), $strip = TRUE)
    {
        foreach ($names as $name) {
            if ( ! isset($def[$name])) {
                if ($strip) {
                    $def[$name] = $GLOBALS['PARAM_STRING'] | $GLOBALS['PARAM_STRIPTAGS'];
                } else {
                    $def[$name] = $GLOBALS['PARAM_STRING'];
                }
            }
        }
        $params = array();
        param_post($def, '', $params);

        return $params;
    }

    protected function _getGetParams($names, $def = array(), $strip = TRUE)
    {
        foreach ($names as $name) {
            if ( ! isset($def[$name])) {
                if ($strip) {
                    $def[$name] = $GLOBALS['PARAM_STRING'] | $GLOBALS['PARAM_STRIPTAGS'];
                } else {
                    $def[$name] = $GLOBALS['PARAM_STRING'];
                }
            }
        }
        $params = array();
        param_get($def, '', $params);

        return $params;
    }

    public function versionDataJsonAction()
    {
        param_request([
            'id' => 'UINT',
        ]);

        $id = $GLOBALS['req_id'];

        if (!$this->_version_table || !$id) {

            return response_error(CODE_ERR_PARAM);
        }

        $row = M('dataVersion')->selectOne(['table_name' => $this->_version_table, 'id' => $id]);

        if (!$row) {
            return response_error(CODE_ERR_PARAM);
        }

        $row['data'] = json_decode($row['data'], TRUE);

        return response($row['data']);
    }

    public function versionListJsonAction()
    {
        param_request([
            'id' => 'UINT',
        ]);

        $id = $GLOBALS['req_id'];

        if (!$this->_version_table || !$id) {
            return response([]);
        }

        $attrs = [
            'select' => 'd.id, d.version_id, UNIX_TIMESTAMP(d.create_time) * 1000 As create_time,d.modify_fields,d.user_id, u.nick',
            'order_by' => 'create_time desc',
            'limit' => 30
        ];
        $where = [
            'table_name' => $this->_version_table,
            'pk' => $id
        ];
        $db_model = new \GG\Db\Model\Base('data_version as d left join user u on d.user_id = u.id ', 'dataddy');
        $rows = $db_model->select($where, $attrs);

        foreach ($rows as &$row) {
            $modify_fields = explode(',', $row['modify_fields']);
            if (count($modify_fields) > 1) {
                $row['modify_fields'] = $modify_fields[0] . '..';
            }
        }
        unset($row);

        return response($rows);
    }
}
/* End of file filename.php */
