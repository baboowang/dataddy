<?php
class System extends Action{

    public function __construct($viewPath, $ext = '.php') {
        parent::__construct($viewPath, $ext);
        $this->assign('title', '1. 系统环境检测');
        $this->assign('nextModule', 'user');
    }

    protected function initTitle(){
        $this->assign('subTitle', '1. 系统环境检测');
    }

    public function index(){
        $this->copyInitTpl();
        $this->checkSystemParams();
        $this->checkPhpModuleParams();
        $this->checkPhpDirRight();

        file_put_contents(INSTALL_STEP, 'system');
        $this->output();
    }

    public function copyInitTpl() {
        if(!file_exists(USER_INSTALL_PATH_TPL)){
            $this->_fail('初始化配置模板不存在');
        }

        //if (file_exists(USER_INSTALL_PATH)) unlink(USER_INSTALL_PATH);
        if (file_exists(USER_INSTALL_PATH)) {
            return;
        }

        if (!copy(USER_INSTALL_PATH_TPL, USER_INSTALL_PATH)) {
            $this->_fail('初始化配置文件失败');
        }

        if (!$fp = fopen(USER_INSTALL_PATH, 'r')) {
            $this->_fail('打开配置文件失败');
        }
    }

    public function checkPhpDirRight(){
        $checks = $this->allCheck;
        $tmp = $checks['DIR_RIGHT'];
        $params = &$tmp['params'];
        foreach($params as $key => &$val){
            $fun = 'get' . $key . 'Dir';
            if(method_exists($this, $fun)){
                $this->$fun($val);
            }else{
                $this->checkDir($key, $val);
            }
        }

        $this->assign('checkDirRight', $tmp);
    }

    public function checkSystemParams(){
        $checks = $this->allCheck;
        $tmp = $checks['SYSTEM'];
        $params = &$tmp['params'];
        foreach($params as $key => &$val){
            $fun = 'get' . $key . 'Info';
            if(method_exists($this, $fun)){
                $this->$fun($val);
            }
        }

        $this->assign('checkParams', $tmp);
    }

    public function checkDir($dir, &$val){
        $flag = TRUE;
        if(is_dir($dir)){
            $fp = @fopen($dir . '/dataddy_cron.out', 'w');
            if(!$fp){
                $errInfo = '目录不可写';
                $flag =  FALSE;
            }
            @fclose($fp);
        }

        if($flag){
            $val['current_p']  = '可写';
            $val['path']  = $dir . '/dataddy_cron.out';
            $val['is_valid'] = 1;
            $val['info'] = '检测通过';
            $this->writeParamsToInit($data = NULL, array('cron.output' => $dir . '/dataddy_cron.out'), FALSE);
        }else{
            $val['current_p']  = '不可写';
            $this->getCheckInfo($val, '目录不可写,但不影响软件使用', '目录不可写');
        }

    }

    public function getAPPLICATIONDir(&$val){
        $logPath = INSTALL_PATH . DS . 'logs';
        $errInfo = '';
        $flag = TRUE;
        if(is_dir($logPath)){
            $fp = @fopen($logPath . '/test.txt', 'w');
            if(!$fp){
                $errInfo = '目录不可写';
                $flag =  FALSE;
            }
            @fclose($fp);
            @unlink($logPath . '/test.txt');
        }else{
            if(!@mkdir($logPath, 0777)){
                $errInfo = '目录不可写';
                $flag =  FALSE;
            }
        }

        if($flag){
            $val['current_p']  = '可写';
            $val['path']  = $logPath;
            $val['is_valid'] = 1;
            $val['info'] = '检测通过';
            $this->writeParamsToInit($data = NULL, array('log.dir' => 'APPLICATION_PATH "/logs"'), FALSE);
        }else{
            $val['current_p']  = '不可写';
            $this->getCheckInfo($val, '目录不可写,但不影响软件使用', '目录不可写');
        }
    }

    public function getPHPDir(&$val){

        $phpPath = NULL;
        $initKey = 'cron.php_path';

        $phpPath = $this->getParamsToInit($initKey);
        if(!$phpPath || !$this->checkPHPExecute($phpPath)) {
            while (TRUE) {
                $fpm_dir = dirname(PHP_BINARY);
                $check_path = $fpm_dir . DS . 'php';
                if (is_executable($check_path)) {
                    $phpPath = $check_path;
                    break;
                }

                if (basename($fpm_dir) === 'sbin') {
                    $check_path = dirname($fpm_dir) . DS . 'bin' . DS . 'php';
                    if (is_executable($check_path)) {
                        $phpPath = $check_path;
                        break;
                    }
                }

                if (isset($_SERVER['_']) && $_SERVER['_'] && is_executable($_SERVER['_'])) {
                    $phpPath = $_SERVER['_'];
                    break;
                }

                $paths = array();
                if (isset($_SERVER['PATH'])) {
                    $paths = explode(':', $_SERVER['PATH']);
                } else if (isset($_SERVER['Path'])) {
                    $paths = explode(';', $_SERVER['Path']);
                }
                if ($paths) {
                    foreach ($paths as $path) {
                        if (preg_match('/php/i', $path)) {
                            if (is_file($path . '/php') || is_file($path . '/php.exe')) {
                                $phpPath = $path . DS . 'php';
                                break;
                            }
                        }
                    }
                }
                break;
            }
        }

        if($phpPath){
            $val['path'] = $phpPath;
            if($this->checkPHPExecute($phpPath)) {
                $val['current_p'] = '可执行';
                $val['is_valid'] = 1;
                $val['info'] = '检测通过';
                $this->writeParamsToInit(NULL, array('cron.php_path' => $phpPath), FALSE);
            }else{
                $val['current_p']  = '不可执行';
                $this->getCheckInfo($val, 'PHP不可执行,但不影响软件使用', 'PHP不可执行,请手动输入');
            }
        }else{
            $val['current_p']  = '未找到';
            $this->getCheckInfo($val, '未找到PHP安装路径,但不影响软件使用', '未找到PHP安装路径,请手动输入');
        }
    }

    public function checkPHPExecute($phpPath){
        try{
            $output = array();
            exec( $phpPath . ' -v', $output);
            if(!empty($output)){
                foreach($output as $line){
                    if(preg_match('/php/i', $line)){
                        return TRUE;
                    }
                }
            }
        }catch (Exception $e) {

        }

        return FALSE;
    }

    public function checkPhpModuleParams(){
        $checks = $this->allCheck;
        $tmp = array();
        $tmp = $checks['PHP_MODULAR'];
        $params = &$tmp['params'];

        $loadedExt = array_flip(get_loaded_extensions());

        foreach($params as $key => &$val){
            if(isset($loadedExt[$key])){
                $ext = new ReflectionExtension($key);
                $val['current_p']  = $ext->getVersion();
                $this->chkPhpModuleVer($val);
            }else{
                $this->getCheckInfo($val, '未安装, 但不影响软件使用。', '未安装');
            }
        }

        $this->assign('checkPhpModules', $tmp);
    }

    public function chkPhpModuleVer(&$val){
        if(!empty($val['claim_p'])){
            if (version_compare($val['current_p'], $val['claim_p'], '>='))
            {
                $val['is_valid'] = 1;
                $val['info'] = '版本符合';
            }else{
                $this->getCheckInfo($val, '版本低， 但不影响软件使用。', '版本低');
            }
        }else{
            $val['is_valid'] = 1;
            $val['info'] = '版本无要求';
        }
    }

    public function getOSInfo(&$val){
        $os = strtolower(PHP_OS);
        if (preg_match('/win/',$os)) {
            $os ='Windows';
        } else if (preg_match('/linux/',$os)) {
            $os ='Linux';
        } else if (preg_match('/darwin/',$os)) {
            $os ='Mac';
        }

        $val['current_p'] = $os;

        if($os == $val['claim_p']){
            $val['is_valid'] = 1;
            $val['info'] = '完全符合软件要求';
        }else{
            $this->getCheckInfo($val);
        }
    }

    public function getClientOSInfo(&$val){
        $os='';
        $Agent=$_SERVER['HTTP_USER_AGENT'];

        if(preg_match('/Windows/',$Agent)){

            $winMap = array(
                '95' => 'Windows 95',
                '4.90' => 'Windows ME',
                '98' => 'Windows 98',
                'NT 5.0' => 'Windows 2000',
                'NT 5.1' => 'Windows XP',
                'NT 6.0' => 'Windows Vista',
                'NT 6.1' => 'Windows 7',
            );

            foreach($winMap as $key => $win){
                if(strpos($Agent, $key)){
                    $os = $win;
                }else{
                    $os = '其他windows系统';
                }
            }

        }else if(preg_match('/Linux/',$Agent)){
            $os ='Linux';
        }else if(preg_match('/Unix/',$Agent)){
            $os ='Unix';
        }else if(preg_match('/Sun/',$Agent)){
            $os = 'Sun';
        }else if(preg_match('/Ibm/',$Agent)){
            $os = 'IBM';
        }elseif(preg_match('/Mac/',$Agent) && preg_match('PC',$Agent)){
            $os = 'Macintosh';
        }elseif(preg_match('/PowerPC/',$Agent)){
            $os = 'PowerPC';
        }elseif(preg_match('/AIX/',$Agent)){
            $os = 'AIX';
        }elseif(preg_match('/HPUX/',$Agent)){
            $os = 'HPUX';
        }elseif(preg_match('/NetBSD/',$Agent)){
            $os = 'NetBSD';
        }elseif(preg_match('/BSD/',$Agent)){
            $os = 'BSD';
        }elseif(preg_match('/OSF1/',$Agent)){
            $os = 'OSF1';
        }elseif(preg_match('/IRIX/',$Agent)){
            $os = 'IRIX';
        }elseif(preg_match('/FreeBSD/',$Agent)){
            $os = 'FreeBSD';
        }elseif($os==''){
            $os = '不确定';
        }

        $val['current_p'] = $os;

        if($os == $val['claim_p']){
            $val['is_valid'] = 1;
            $val['info'] = '完全符合软件要求';
        }else{
            $this->getCheckInfo($val);
        }


    }

    public function getPHPInfo(&$val){
        $val['current_p'] = PHP_VERSION;

        if (version_compare(PHP_VERSION, $val['claim_p'], '>='))
        {
            $val['is_valid'] = 1;
            $val['info'] = '完全符合软件要求';
        }else{
            $this->getCheckInfo($val);
        }
    }

    public function getCheckInfo(&$val, $sucInfo = '不符合要求, 但不影响软件使用。', $failInfo = '软件无法正常运行'){
        if($val['strict'] == -1 || $val['strict'] == 0){
            $val['is_valid'] = 1;
            $val['info'] = $sucInfo;
        }else{
            $this->checkResult = 0;
            $val['is_valid'] = 0;
            $val['info'] = $failInfo;
        }
    }

    public function modifyPHP(){
        if(!isset($_REQUEST['PHP'])){
            $this->_fail('没有修改参数');
        }

        $data = $_REQUEST['PHP'];
        $map = array(
            'cron.php_path' => $data
        );

        if(!$this->writeParamsToInit(NULL, $map, FALSE, TRUE)){
            $this->_fail('修改失败');
        }

        if(!$this->checkPHPExecute($data)){
            $this->_fail('指定PHP不可执行');
        }

        $this->_succ('修改成功');

    }

}
