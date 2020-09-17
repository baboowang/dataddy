<?php
/*
 * @name 测试示例插件
 * @desc 本插件只为初次使用软件的用户，示范页面过滤插件的开发和使用，不参与业务使用，可删除
 */
namespace PL\Test;

class TestFilter extends \MY\Filter_Abstract
{
    protected static $data_url;

    public function pluginInit($dispatcher, $manager)
    {
        \MY\FilterFactory::register('testObj', __CLASS__);

        self::$data_url = $manager->registerData($this, 'testObj.json', 'testObjJson');
    }

    public function testObjJson()
    {
        $dbName = trim(Config::get('db_singles.dataddy.db_name'));
        $m = ddy_model("{$dbName}.test_obj");

        if ($_GET['q']) {
            $where = [
                'name' => ['like' => '%'.$_GET['q'].'%'],
                'id' => ['like' => '%'.$_GET['q'].'%'],
                \GG\Db\Sql::LOGIC => 'OR'
            ];
        }

        # @todo
        if ($this->report_options && isset($this->report_options['permission'])) {
            $permission = $this->report_options['permission'];
            $user = R('user');
        }

        $rows = $m->select($where, [ 'select' => 'id,name', 'order_by' => 'id DESC', 'limit' => '100' ]);

        if ($rows) {

            $data = json_encode($rows);

            return $this->returnData($data);
        }


        return [];
    }

    private function returnData($data) {
        $etag = md5($data);
        header("Etag: ". $etag);
        if (trim(d(@$_SERVER['HTTP_IF_NONE_MATCH'], '')) == $etag) {
            header("HTTP/1.1 304 Not Modified");
            exit;
        }

        return $data;

    }

    protected function _getValue()
    {
        $val = trim(parent::_getValue(), ', ');

        if (preg_match('@^\d+(?:\s*,\s*\d+)*$@', $val)) {

            return $val;
        }

        if ($val) {
            $dbName = trim(Config::get('db_singles.dataddy.db_name'));
            $m = ddy_model("{$dbName}.test_obj");
            $where = [];
            $tokens = preg_split('@\s+@u', $val, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($tokens as $token) {
                $where[] = [ 'name' => ['like' => '%' . $token . '%'] ];
            }
            if (count($where) > 1) {
                $where[\GG\Db\Sql::LOGIC] = 'AND';
            } else {
                $where = $where[0];
            }

            $attrs = ['limit' => $this->param('max', 1000), 'select' => 'id'];
            $rows = $m->select($where, $attrs);

            $val = implode(',', array_get_column($rows, 'id'));
        }

        return $val;
    }

    public function filterInit()
    {
        $m = \MY\PluginManager::getInstance();

        $this->params['raw'] = TRUE;
    }

    public function validate($slotid)
    {
        if ($slotid === '') return TRUE;

        if (!preg_match('@^\d+(\s*,\s*\d+)*$@', $slotid)) {
            $this->error = '测试组件名称输入错误';

            return FALSE;
        }

        if ($this->param('single') && count(explode(',', $slotid)) > 1) {
            $this->error = '只允许单值';

            return FALSE;
        }

        return TRUE;
    }

    protected function _labelView() { return ''; }

    public function view()
    {
        static $index = 0;

        $id = 'f-testobj-' . ($index++);

        $data_url = self::$data_url;

        $script = <<<HTML
<script>
!function (data_url) {

    function getDataSources(data_url) {
        var source = new Bloodhound({
            limit: 100,
            datumTokenizer: function (datum) {
                var test = Bloodhound.tokenizers.whitespace(datum.id);
                test = test.concat(Bloodhound.tokenizers.whitespace(datum.name))
                $.each(test, function (k, v) {
                    i = 0;
                    while ((i + 1) < v.length) {
                        test.push(v.substr(i, v.length));
                        i++;
                    }
                })
                return test;

            },
            queryTokenizer: function (str) {
                str = str.replace(/^.+,([^,]*)$/, '$1');
                return str ? str.split(/\s+/) : [];
            },
            remote: {
                url: data_url + '&q=%QUERY'
            }
        });

        source.initialize();

        var src = {
            displayKey: 'id',
            source: source.ttAdapter(),
            templates: {
                suggestion: Handlebars.compile([
                    '{{id}} <span style="font-size:10px">{{name}}</span>',
                ].join(''))
            }
        };

        return src;
    }

    function initializeTypeahead(dataSources)
    {
        var input = $('#{$id}').typeahead(null, dataSources);

        input.data('ttTypeahead').input.setInputValue = function (value, silent) {
            var last_value = this.\$input.val();

            if (last_value != value) {
                last_value = last_value.replace(/[^,]*$/, '');
                if (last_value.split(/,/).indexOf(value) == -1) {
                    this.\$input.val(last_value + value + ',');
                }
            }
            silent ? this.clearHint() : this._checkInputValue();
        };
    }

    var dataSources = [];
    dataSources.push(getDataSources(data_url));
    initializeTypeahead(dataSources);


}('{$data_url}');

</script>
HTML;

        return sprintf(
            '<input type="text" class="form-control typeahead" name="%s" placeholder="%s" value="%s" id="%s"/>%s',
            $this->name,
            $this->label,
            h(d(@$_GET[$this->name], $this->getValue())),
            $id,
            $script
        );
    }
}
