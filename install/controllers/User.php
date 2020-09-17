<?php
class User extends Action{

    public $checkParams = array();
    public $initParams = array();

    CONST USER_DB_NAME = '__USER_DB_NAME__';
    /*
     * val:配置文件名|值处理模式(1:加引号, 2:转化为true/false字符串,3:加前缀, 4:加引号,加前缀)
     */
    public $nameMap = array(
        'host' => 'db_physical.default.write.host',
        'port' => 'db_physical.default.write.port',
        'name' => 'db_singles.dataddy.db_name',
        'user' => 'db_physical.default.db_user',
        'password' => 'db_physical.default.db_pwd',
        #'admin_account' => 'admin.account',
        #'admin_password' => 'admin.password'
    );

    public function __construct($viewPath, $ext = '.php') {
        parent::__construct($viewPath, $ext);
        $this->assignUserParams();
        $this->assign('nextModule', 'adapter');
    }

    protected function initTitle(){
        $this->assign('subTitle', '2. 数据库及管理员');
    }

    public function assignUserParams(){
        $user = $this->allCheck;
        unset($user['SYSTEM']);
        unset($user['PHP_MODULAR']);
        $this->assigns($user);
    }

    public function index(){
        file_put_contents(INSTALL_STEP, 'user');
        $this->output();
    }

    public function userParams(){
        $rules = array(
            'host|数据库服务器' => 'required|valid_domain',
            'port|端口' => 'required|is_natural_no_zero|greater_than[1001]|less_than[65535]',
            'name|数据库名称' => 'required|alpha_dash',
            'user|数据库用户名' => 'required|alpha_dash',
            'password|数据库密码' => 'required|alpha_dash',
            'admin_account|管理员账号' => 'required|alpha_dash|min_than[4]|max_than[13]',
            'admin_password|管理员密码' => 'required|safe_password|min_than[8]|max_than[13]'
        );
        FormValidate::setRules($rules);
        if(!FormValidate::run()){
            $this->_fail(FormValidate::getErrors());
        }else{
            $data = FormValidate::getValidateData();
            $this->initParams = $data;
            $this->initUserData($data);
        }
    }

    public function initUserData($data){
        //尝试连接数据库服务器
        if( ! $conn = $this->tryConDatabase($data['host'], $data['port'], $data['user'], $data['password'], '')){
            $this->_fail('连接数据库服务器失败');
        }else{

            //检测用户数据库是否存在
            $dbExits = $this->checkDbExits($data['name'], $conn);
            if ($dbExits && !FORCE_USE_DB) {
            //    $this->_fail($data['name'] . '数据库已经存在');
            }

            if (!$dbExits) {
                if(! @mysqli_query($conn, 'Create database ' . $data['name'])){

                    $this->_fail($data['name'] . '创建数据库失败');

                }
            }

            $this->initDataBase($data);
            $this->writeParamsToInit($data, $this->nameMap);
            file_put_contents(INSTALL_STEP, 'adapter');
            $this->_succ('数据库及管理员数据初始化成功');

        }
    }

    public function tryConDatabase($host, $port = '3306', $user, $pwd, $dbName){
        return create_link($host, $port, $user, $pwd, $dbName);
    }

    public function checkDbExits($db, $link){
        $rows = mysqli_query($link, 'SHOW DATABASES');
        while($row =  mysqli_fetch_array($rows)){
            if ( $db == $row['Database']){
                return TRUE;
            }
        }

        return FALSE;
    }

    public function initDataBase($data){
        if(!file_exists(MYSQL_INSTALL_PATH) || !file_exists(MYSQL_INSTALL_EG_DATA_PATH)){
            $this->_fail('数据库初始化文件不存在');
        }

        $host = $data['host'];
        $port = $data['port'];
        $user = $data['user'];
        $pwd = $data['password'];
        $dbName = $data['name'];

        $admin = $data['admin_account'];
        $userPwd = $this->getPassword($data['admin_password'], $admin);

        $link = create_link($host, $port, $user, $pwd, $dbName);
        $content = str_replace(SELF::USER_DB_NAME, $dbName, file_get_contents(MYSQL_INSTALL_PATH));
        $dns = $this->getEncryptDns($host, $port, $user, $pwd, $dbName);
        $defaultDns = <<<SQL
        INSERT dsn (id, name, remark, dsn, create_account) VALUES
  (1, 'default', '默认', '{$dns}', 'admin')
SQL;
        $content .= "{$defaultDns};";

        $now = date('Y-m-d H:i:s');
        $admin = <<<SQL
        INSERT user (id, username, password, nick, roles, is_admin, last_login_time) VALUES
        (1, '{$admin}', '{$userPwd}', '管理员', '1', 1, '{$now}')
SQL;

        $content .= "{$admin};";

        $content .= file_get_contents(MYSQL_INSTALL_EG_DATA_PATH);//引入模拟数据table

        $content .= $this->ddy_create_income_data();

        $rs = mysqli_multi_query($link, $content);
        if(!$rs){
            $testConn = create_link($host, $port, $user, $pwd, '');
            mysqli_query($testConn, 'DROP DATABASE ' . $dbName);
            $this->_fail('初始化数据库失败');
        }else{
            return TRUE;
        }
    }

    public function getEncryptDns($host, $port = 3306, $user, $pwd, $dbName){

        $dns = "{$user}:{$pwd}@tcp({$host}:{$port})/{$dbName}";

        return ins_aes_encrypt($dns, Config::get("secret.key"));
    }

    public function getPassword($password, $username){
        return password_hash(base64_encode(hash_hmac("sha512", $password, $username . Config::get("secret.key"), true)),  PASSWORD_DEFAULT, ['cost' => 12]);
    }


    /*
     * 生成报表模拟数据
     */
    function ddy_create_income_data($obj = NULL, $start = NULL){

        $sql = 'INSERT INTO `test_income_report` (`date`, `obj_id`, `ad_id`, `request`, `click`, `business_type`, `income`, `cost`, `impression`, `play`) VALUES ';
        $allObj = $obj ? $obj : array(1, 2, 3, 4, 5, 6, 7, 8);

        //为了便于用户查看示例，所以生成尽可能长时间的模拟数据.默认生成[一周之前, 未来一个月]的数据。
        $start = $start ? $start : date('Y-m-d', strtotime('-7 days'));
        $end = date('Y-m-d', strtotime('+30 days'));

        $data = array();
        $business_types = array(
            'baidu', 'tencent', 'taobao'
        );

        $index = 0;
        foreach($allObj as $obj) {

            $_start = $start;
            $flag = array_rand_user(1, 10);
            if ($flag > 7) continue;

            while($_start <= $end) {

                $flag = array_rand_user(1, 10);
                if ($flag <= 7) {

                    $ad_id = array_rand_user(10000, 11000);
                    $request = array_rand_user(1000, 1500);
                    $impression = array_rand_user($request - 200, $request);
                    $play = array_rand_user($impression - 50, $impression);
                    $click = array_rand_user($impression - 200, $play);
                    $_index = $index % 3;
                    $business = $business_types[$_index];
                    $income = round(array_rand_user(100, 500) / 100, 2);
                    $cost = round(array_rand_user(100, 200) / 100, 2);

                    $data[] = "('"  . implode("','", array($_start, $obj, $ad_id, $request, $click, $business, $income, $cost, $impression, $play)) . "')";
                }

                $index++;
                $_start = date('Y-m-d', strtotime('+1 days', strtotime($_start)));
            }
        }

        return $sql . implode(",", $data) . ';';
    }

}
