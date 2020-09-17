<?php
class UserModel extends \GG\Db\Model\Base {
    public function __construct()
    {
        parent::__construct(
            \GG\Config::get('database.table.prefix', '') . 'user',
            'dataddy'
        );
        $this->addEventHandler(new \GG\Db\DataVersion(new DataVersionModel(), 'id', ['last_login_time']));
    }

    public static function randPassword($length = 8)
    {
        $password = rand_str($length);

        $special_chars = '+=-@#~,.[]()!%^*$/';
        $special_char = $special_chars{rand(1, strlen($special_chars)) - 1};
        $password{rand(1, $length) - 1} = $special_char;

        return $password;
    }

    public static function hash($username, $password)
    {
        return password_hash(base64_encode(hash_hmac("sha512", $password, $username . \GG\Config::get("secret.key"), true)),  PASSWORD_DEFAULT, ['cost' => 12]);
    }

    public static function password_verify($username, $password, $passwordHash)
    {
        return password_verify(base64_encode(hash_hmac("sha512", $password, $username . \GG\Config::get("secret.key"), true)), $passwordHash);
    }

    public static function addUser($userInfo, $fields)
    {
        $user = new UserModel();
        array_partial_copy($userInfo, $newUser, $fields);
        $newUser['password'] = UserModel::hash($newUser['username'], UserModel::randPassword());
        $newUser['roles'] = '';
        $newUser['is_admin'] = 0;
        $ret = $user->insert($newUser);

        return $ret;
    }
}
/* End of file filename.php */
