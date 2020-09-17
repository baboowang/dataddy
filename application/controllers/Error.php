<?php
class ErrorController extends MY\Controller_Abstract {

    public function notFoundAction()
    {
    }

    public function indexAction()
    {
        $err_msg = $this->getRequest()->getParam('err_msg');
        $exception = $this->getRequest()->getParam('exception');
        $ext_content = $this->getRequest()->getParam('ext_content');

        $this->data['err_msg'] = $err_msg;
        $this->data['ext_content'] = $ext_content;
        $this->data['exception'] = $exception;
    }
}

