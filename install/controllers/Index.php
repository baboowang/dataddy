<?php
class Index extends Action{

    public $checkParams = array();

    public function __construct($viewPath, $ext = '.php') {
        parent::__construct($viewPath, $ext);
        $this->assign('step', 'system');
        $this->assign('nextModule', 'system');
    }

    protected function initTitle(){
        $this->assign('subTitle', '安装条款');
    }

    public function index(){
        $this->output();
    }

}