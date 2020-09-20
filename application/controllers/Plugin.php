<?php
use MY\PluginManager;

class PluginController extends MY\Controller_Abstract
{
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

        $content = PluginManager::getInstance()->getData($GLOBALS['req_id'], $GLOBALS['req_name']);

        if ($content) {
            echo $content;
        }

        return FALSE;
    }

    public function listJsonAction()
    {
        //@todo 根据权限设定查询条件
        $where = [];
        $attrs = [
            'select' => 'id,bundle_id,scope,enable,name,version,author,update_time',
            'order_by' => 'id ASC'
        ];
        $list = M('plugin')->select($where, $attrs);

        unset($item);

        R('title', '插件列表');

        return response($list);
    }

    public function saveAction()
    {
        $rules = [
            'id' => 'int',
            'content|源码' => 'any',
        ];

        $result = $this->form_validation->check($rules, $this);

        if ($result === FALSE) {
            return response_error(CODE_ERR_PARAM, $this->form_validation->errors());
        }

        try {
            $info = PluginManager::parsePluginContent($result['content']);
        } catch (Exception $e) {
            return response_error(CODE_ERR_PARAM, $e->getMessage());
        }

        $result = array_merge($result, $info);

        $id = @$result['id'];
        unset($result['id']);

        $m = M('plugin');
        $origin = null;

        if ($id) {
            $origin = $m->find($id);
            if (!$origin) {
                return response_error(CODE_ERR_NOT_FOUND);
            }
            if ($origin['bundle_id'] != $result['bundle_id']) {
                return response_error(CODE_ERR_PARAM, "插件类名不允许变更");
            }
            $where = ['id' => $id];
            $ok = $m->update($where, $result);
        } else {
            if ($m->selectCount(['bundle_id' => $result['bundle_id']]) > 0) {
                return response_error(CODE_ERR_PARAM, "插件已被定义");
            }
            $id = $m->insert($result, TRUE);

            $ok = !!$id;
        }

        if ($ok) {
            $plugin = $m->find($id);

            if ($origin && $origin['scope'] != $plugin['scope'] && $plugin['enable']) {
                Config::enablePlugin($plugin);
            }

            try {
                PluginManager::writePluginSourceFile($plugin['bundle_id'], $plugin['content']);
            } catch (Exception $e) {
                return response_error(CODE_ERR_SYSTEM, $e->getMessage());
            }

            R('title', '插件保存:' . d($plugin['name'], $plugin['bundle_id']));

            return response($plugin);
        } else {
            return response_error(CODE_ERR_SYSTEM);
        }

        return response_error(CODE_ERR_PARAM, 'test', $result);

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
            'id' => 'string',
        ]);

        $id = @$GLOBALS['req_id'];

        $data = [];

        if ($id) {
            $data['plugin'] = M('plugin')->find($id);
        } else {
            $data['plugin'] = [
                'content' => self::DEFAULT_TPL,
            ];
        }
        #$p = PluginManager::getInstance()->getPlugin($clazz, true);

        return response($data);
    }

    private function checkOperationID()
    {
        param_post([
            'id' => 'UINT',
        ]);

        $id = $GLOBALS['post_id'];

        if (empty($id)) {
            return response_error(CODE_ERR_PARAM);
        }

        return $id;
    }

    private function checkOperationInstance()
    {
        $id = $this->checkOperationID();

        if ($id === false) {
            return false;
        }

        $plugin = M('plugin')->find($id);

        if (empty($plugin)) {
            return response_error(CODE_ERR_PARAM, '插件不存在');
        }

        return $plugin;
    }

    public function enableAction()
    {
        $plugin = $this->checkOperationInstance();

        if ($plugin === false) {
            return false;
        }

        R('title', '启用插件:' . $plugin['bundle_id']);

        $ok = M('plugin')->update(['id' => $plugin['id']], [
            'enable' => 1,
        ]);

        if (!$ok) {
            return response_error(CODE_ERR_SYSTEM);
        }

        Config::enablePlugin($plugin);

        return response($plugin);
    }

    public function disableAction()
    {
        $plugin = $this->checkOperationInstance();

        if ($plugin === false) {
            return false;
        }

        R('title', '停用插件:' . $plugin['bundle_id']);

        $ok = M('plugin')->update(['id' => $plugin['id']], [
            'enable' => 0,
        ]);

        if (!$ok) {
            return response_error(CODE_ERR_SYSTEM);
        }

        Config::disablePlugin($plugin);

        return response($plugin);
    }

    public function removeAction()
    {
        $plugin = $this->checkOperationInstance();

        if ($plugin === false) {
            return false;
        }

        $ok = M('plugin')->delete(['id' => $plugin['id']]);

        R('title', '插件删除:' . $plugin['bundle_id']);

        if (!$ok) {
            return response_error(CODE_ERR_SYSTEM);
        }

        Config::disablePlugin($plugin);

        return response($plugin);
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

    const DEFAULT_TPL = <<<'EOT'
<?php
/**
 * @name            插件名称
 * @version         1.0.0
 * @author          dataddy
 * @scope           report
 * @email           dataddy.com
 */

namespace PL\MyGroup; //修改MyGroup为你自己的名字空间

// 注意类名不对以Plugin结尾，会被Yaf识别成Yaf插件而导致类找不到
class PluginDefault extends \MY\Plugin_Abstract
{
    // use \MY\Plugin_FieldTplRenderTrait;

    // 插件初始化
    public function pluginInit($dispatcher, $manager)
    {
        //@todo
        //\MY\Data_Template::registerPlugin($this);
    }

    public function install()
    {
        return true;
    }

    public function uninstall()
    {
        return true;
    }
}
EOT;
}
/* End of file Index.php */
