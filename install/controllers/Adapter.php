<?php
class Adapter extends Action{

    public $checkParams = array();
    public $initParams = array();
    /*
     * val:配置文件名|值处理模式(1:加引号, 2:转化为true/false字符串,3:加前缀, 4:加引号,加前缀)
     */
    public $nameMap = array(
        'plugins' => 'plugins',
        'sso_page' => 'sso.page',
        'log_level' => 'log.level',
        'vim_mode' => 'editor.vim_mode|2',
        'num_format' => 'report.number_format|2',
        'cookie_expire' => 'cookie.expire',
        'mail_host' => 'mail.host',
        'mail_port' => 'mail.port',
        'mail_username' => 'mail.username',
        'mail_password' => 'mail.password',
    );

    public function __construct($viewPath, $ext = '.php') {
        parent::__construct($viewPath, $ext);
        $this->assignConfigParams();
        //$this->assign('title', '3. 软件初始化配置');
        $this->assign('nextModule', $this->getAppHost());
        $this->assign('isOver', 1);
        $this->assign('plugins', $this->getSysPlugins());
    }

    protected function initTitle(){
        $this->assign('subTitle', '3. 软件初始化配置');
    }

    public function getSysPlugins(){
        $dir = PLUGINS_PATH;
        $plugins = PluginsUtils::getPluginsList();
        return $plugins;
    }

    public function index(){
        file_put_contents(INSTALL_STEP, 'adapter');
        $this->output();
    }

    public function assignConfigParams(){
        $allCheck = $this->allCheck;
        $config['INIT_CONFIG'] = $allCheck['INIT_CONFIG'];
        $this->assigns($config);
    }

    public function userParams(){

        $rules = array(
            'plugins|系统插件' => 'callback_com_plugins',
            'sso_page|单点登陆网址' => 'uri|callback_sso_web',
            'log_level|日志级别' => 'required',
            'vim_mode|开启vim主题样式' => 'required',
            'num_format|开启数字格式化' => 'required',
            'cookie_expire|Cookie保存周期' => 'required|is_natural_no_zero',
            'mail_host|邮箱服务器' => 'required',
            'mail_port|端口' => 'required|is_natural_no_zero|greater_than[10]|less_than[65535]',
            'mail_username|邮箱账号' => 'required|validate_email',
            'mail_password|邮箱密码' => 'required',
            'alarm_type|报警渠道' => 'alpha',
            'alarm_url|报警发送URL' => 'uri',
        );
        FormValidate::setRules($rules);
        if(!FormValidate::run()){
            $this->_fail(FormValidate::getErrors());
        }else{
            $data = FormValidate::getValidateData();
            $this->initParams = $data;
            if (!empty($data['alarm_type']) && !empty($data['alarm_url'])) {
                $this->nameMap['alarm_url'] = "notify.{$data['alarm_type']}.url";
            }
            $this->writeParamsToInit($data, $this->nameMap);
            if(file_exists(APP_CONF_PATH)) @unlink(APP_CONF_PATH);
            copy(USER_INSTALL_PATH, APP_CONF_PATH);
            unlink(USER_INSTALL_PATH);
            file_put_contents(INSTALL_STEP, 'all_install');
            $this->_succ('ok');
        }
    }

    public static function sso_web($name, $label, &$val){
        if(!empty($_POST['plugins'])){
            $plugins = $_POST['plugins'];
        }else{
            return TRUE;
        }

        $page = trim($_POST['sso_page']);
        if(empty($plugins)) return TRUE;
        if(!is_array($plugins)) $plugins = array($plugins);
        if(in_array('Adeaz\Sso', $plugins) && empty($page)){

            FormValidate::$errs[$name] = $label . '不能为空';
            return FALSE;
        }

        return TRUE;

    }

    public static function com_plugins($name, $label, &$val){
        if(empty($val)){
            $val = '' ;
            return TRUE;
        }

        if(!is_array($val)) $val = array($val);

        $val = implode(',', $val);

        return TRUE;

    }

    public static function checkLogDir($name, $label, &$val){
        $val = preg_replace('#\\\#', '/', $val);
        if(strpos($val, '/') !== 0){
            $val = '/'. $val;
        }
        $dir = APPLICATION_PATH . $val;

        if(!is_dir($dir)) {
            FormValidate::$errs[$name] = $label . '不存在';
            return FALSE;
        }

        $fp = @fopen($dir . '/test.txt', 'w');

        if(!$fp){
            FormValidate::$errs[$name] = $label . '不存在或者目录没有写权限';
            return FALSE;
        }

        @fclose($fp);
        @unlink($dir.'/test.txt');

        return TRUE;
    }


    public function testMailServer(){
        set_time_limit(6);
        $rules = array(
            'mail_host|邮箱服务器' => 'required',
            'mail_port|端口' => 'required|is_natural_no_zero|greater_than[10]|less_than[65535]',
            'mail_username|邮箱账号' => 'required|validate_email',
            'receive_mail|接收邮箱' => 'required|validate_email',
            'mail_password|邮箱密码' => 'required',
        );
        FormValidate::setRules($rules);
        if(!FormValidate::run()){
            $this->_fail(FormValidate::getErrors());
        }else {
            $data = FormValidate::getValidateData();
            $subject = 'DDY测试邮件';
            $msg = '这是一份测试邮件，DDY安装程序测试邮件服务器是否畅通,无需回复。';
            $config = array(
                'host' => $data['mail_host'],
                'port' => $data['mail_port'],
                'username' => $data['mail_username'],
                'password' => $data['mail_password']
            );

            $res = ins_send_mail($data['receive_mail'], $subject, $msg, $config);

            if(!$res){
                $this->_fail('发送邮件失败');
            }

            if($res['code'] === 0){
                $this->_succ('发送成功:' . $res['message']);
            }else{
                $this->_fail('发送邮件失败:' . $res['message']);
            }

        }
    }
}
