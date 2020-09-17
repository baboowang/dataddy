<?php
use MY\PluginManager;

class PluginController extends MY\Controller_Abstract {

    public function init()
    {
        parent::init();
        $this->form_validation = new \GG\FormValidation();
    }

    public function dataAction()
    {
        param_request([
            'id' => 'STRING',
            'name' => 'STRING',
        ]);

        if (!$GLOBALS['req_id'] || !$GLOBALS['req_name']) {
            $this->error('404');

            return FALSE;
        }

        $content = \MY\PluginManager::getInstance()->getData($GLOBALS['req_id'], $GLOBALS['req_name']);

        if ($content) {
            echo $content;
        }

        return FALSE;
    }

    public function listJsonAction()
    {
        $plugins = PluginManager::getInstance()->getPlugins();

        return response($plugins);
    }

    public function saveAction()
    {
        $rules = [
            'id' => 'int',
            'className|插件名称' => 'required',
            'source|源码' => 'any',
        ];

        $result = $this->form_validation->check($rules, $this);

        if ($result === FALSE) {
            return response_error(CODE_ERR_PARAM, $this->form_validation->errors());
        }

        if (PluginManager::getInstance()->isRegistered($result['className'])) {
            PluginManager::getInstance()->updatePlugin($result['className'], $result['source']);
            return response([]);
        } else {
            return response_error(CODE_ERR_PARAM, '未注册的插件');
        }
    }

    public function detailJsonAction()
    {
        param_request([
            'clazz' => 'string',
        ]);

        $clazz = @$GLOBALS['req_clazz'];
        $p = PluginManager::getInstance()->getPlugin($clazz, true);

        return response($p);
    }


    public function testAction()
    {
        $manager = PluginManager::getInstance();
        var_dump($manager->isRegistered('DDY\\Chat'));
        #$plugin = new \MY\Plugin_Chat([]);
        #var_dump($plugin->pluginInit(null, PluginManager::getInstance()));
        echo '';
        return false;
    }

}
/* End of file Index.php */
