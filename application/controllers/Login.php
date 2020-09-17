<?php

class LoginController extends MY\Controller_Abstract
{

    private $sso;

    const RURI_COOKIE = 'ruri';
    const USE_SYSTEM_LOGIN = 'use_system_login';

    public function init()
    {
        parent::init();

        $this->data['sso'] = $this->sso = \MY\PluginManager::getInstance()->sso_plugin;

        $this->form_validation = new \GG\FormValidation();
    }

    public function indexAction()
    {
        if ($this->_checkLogin()) {
            redirect(empty($_GET['redirect_uri']) ? '/' : $_GET['redirect_uri']);
            return false;
        }

        if (empty($_COOKIE[self::USE_SYSTEM_LOGIN])) {

            if (!empty($_GET['redirect_uri'])) {
                setcookie(self::RURI_COOKIE, $_GET['redirect_uri'], 0, '/', $_SERVER['HTTP_HOST']);
            }

            if ($this->sso) {
                $redirect_uri = get_current_protocol() . "://" . $_SERVER['HTTP_HOST'] . '/login/auth';
                return redirect($this->sso->getLoginUrl($redirect_uri));
            }
        }
    }

    public function systemAction()
    {
        echo $this->render('index');

        return false;
    }

    public function authJsonAction()
    {
        $rules = [
            'id' => 'int',
            'username|用户名' => 'trim|required',
            'password|密码' => 'required',
        ];

        $result = $this->form_validation->check($rules, $this);
        if ($result === false) {
            return response_error(CODE_ERR_PARAM, $this->form_validation->errors());
        }

        $user = M('user')->selectOne(['username' => $result['username']]);
        if (!$user) {
            return response_error(CODE_ERR_AUTH, '用户名不存在');
        }
        if (!UserModel::password_verify($result['username'], $result['password'], $user['password'])) {
            return response_error(CODE_ERR_AUTH, '用户名或密码错误');
        }

        $this->setUserInfo($user);
        setcookie(self::USE_SYSTEM_LOGIN, "1", time() + 86400 * 30);
        return response([]);

    }

    public function authAction()
    {
        if (!empty($_COOKIE[self::RURI_COOKIE]) && empty($_GET['redirect_uri'])) {
            $_GET['redirect_uri'] = $_COOKIE[self::RURI_COOKIE];
            setcookie(self::RURI_COOKIE, '', time() - 3600 * 24 * 365, '/', $_SERVER['HTTP_HOST']);
        }

        param_request(['redirect_uri' => 'STRING']);

        $authInfo = $this->sso->auth();

        if (!$authInfo) {

            return $this->error('登录失败');
        }

        $requriedField = ['username', 'nick', 'email', 'mobile', 'avatar'];

        foreach ($requriedField as $field) {
            if (!array_key_exists($field, $authInfo)) {

                return $this->error("SSO认证缺少[{$field}]字段");
            }
        }

        $userInfo = M('user')->selectOne(array(
            'username' => $authInfo['username'],
        ));

        if (empty($userInfo)) {
            $userInfo = UserModel::addUser($authInfo, array_merge($requriedField, ['avatar_small']));
            if (empty($userInfo)) {

                return $this->error("新增用户出错");
            }
        }

        $this->setUserInfo($userInfo, $authInfo);

        if (!empty($_COOKIE[self::USE_SYSTEM_LOGIN])) {
            setcookie(self::USE_SYSTEM_LOGIN, '', time() - 3600);
        }

        $redirectUri = '';

        if (!empty($authInfo['redirect_uri'])) {
            $redirectUri = $authInfo['redirect_uri'];
        } else {
            $redirectUri = !empty($GLOBALS['req_redirect_uri']) ? $GLOBALS['req_redirect_uri'] : '/';
        }

        redirect($redirectUri);
    }

    private function setUserInfo($userInfo, $authInfo = [])
    {
        M('user')->update(array('id' => $userInfo['id']), array(
            'last_login_time' => '&/CURRENT_TIMESTAMP',
        ));

        $session = \GG\Session::getInstance();
        $expire = \GG\Config::get("cookie.expire", 3600);
        $session->setUserID($userInfo['id'], false, $expire, '');
    }

    public function logoutAction()
    {
        $redirect_uri = get_current_protocol() . "://" . $_SERVER['HTTP_HOST'] . '/';

        $session = \GG\Session::getInstance();
        $session->clearUserID();

        if ($this->sso) {
            $logout_url = $this->sso->getLogoutUrl($redirect_uri);
            return redirect($logout_url);
        }

        redirect('/');
    }

    public function getUserInfoAction()
    {
        return $this->sso->getUserInfo();
    }
}
/* End of file Index.php */
