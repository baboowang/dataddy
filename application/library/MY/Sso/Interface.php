<?php
namespace MY;

interface Sso_Interface {

    public function getLoginUrl($redirectUri);

    public function auth();

    public function getLink();

    /**
     *
     * 通过第三方获取当前用户信息
     * @return mixed array("username", "nickname", "email")
     */
    public function getUserInfo();

    public function getLogoutUrl($redirectUri);
}
