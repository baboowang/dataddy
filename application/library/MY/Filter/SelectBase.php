<?php
namespace MY;

abstract class Filter_SelectBase extends Filter_Abstract
{
    protected static $data_urls;
    protected $use_cache = true;
    protected $text_with_id = false;
    protected $value_type = 'int';
    protected $use_ajax_data = false;

    protected $value_column = 'id';
    protected $text_column = 'name';
    protected $filter_conditions = [];

    private $options = null;

    abstract protected function getObjectName();

    protected function getDataFileName()
    {
        return $this->getObjectName() . '.json';
    }

    public function pluginInit($dispatcher, $manager)
    {
        FilterFactory::register($this->getObjectName(), get_class($this));
        $data_url = $manager->registerData(
            $this,
            $this->getDataFileName(),
            'responseSearch'
        );
        self::setDataUrl($this, $data_url);
    }

    private static function setDataUrl($ins, $url)
    {
        self::$data_urls[get_class($ins)] = $url;
    }

    private static function getDataUrl($ins)
    {
        return self::$data_urls[get_class($ins)] ?? null;
    }

    abstract function getModel();

    public function responseSearch()
    {
        param_request([
            'term' => 'STRING',
            'page' => 'UINT',
        ]);

        $term = $GLOBALS['req_term'] ?? '';
        $page = $GLOBALS['ref_page'] ?? 1;

        $data = $this->searchItems([
            'term' => $term,
            'page' => $page,
        ]);

        $response = [
            'results' => $data,
            'pagination' => [
                'more' => false,
            ],
        ];

        return json_encode($response);
    }

    protected function searchItems($params = [])
    {
        $page_size = $params['page_size'] ?? 20;
        $value_column = $this->value_column;
        $text_column = $this->text_column;

        $where = $this->filter_conditions;
        $attrs = [
            'select' => "$value_column AS id, $text_column AS text",
            'order_by' => "$value_column ASC",
            'limit' => $page_size,
        ];

        if ($params['page'] ?? 0) {
            $attrs['offset'] = ($params['page'] - 1) * $page_size;
        }

        if ($params['value'] ?? '') {
            $where[$value_column] = $params['value'];
        }

        if ($params['term'] ?? '') {
            $sub_where = [];
            if ($this->value_type == 'int') {
                if (is_numeric($params['term'])) {
                    $sub_where[$value_column] = $params['term'];
                }
            } else {
                $sub_where[$value_column] = $params['term'];
            }
            $sub_where[$text_column] = ['like' => "{$params['term']}%"];
            $sub_where['__logic'] = 'or';
            if ($where) {
                $where = [
                    $where,
                    $sub_where,
                    '__logic' => 'AND',
                ];
            } else {
                $where = $sub_where;
            }
        }

        $items = $this->getModel()->select($where, $attrs);

        return $items ?: [];
    }

    private function getOptions()
    {
        if (!is_null($this->options)) {
            return $this->options;
        }

        if ($this->use_cache) {
            $content = $this->getRunningTimeData($this->getDataFileName());

            if ($content) {
                $this->options = json_decode($content, true);
                return $this->options;
            }
        }

        $options = $this->searchItems([
            'page_size' => 200,
        ]);

        $this->options = $options = array_column($options, 'text', 'id');

        if ($this->use_cache && $this->options) {
            $this->writeRunningTimeData(
                $this->getDataFileName(),
                json_encode($this->options)
            );
        }

        return $options;
    }

    private function getSpecifiedOptions($values)
    {
        $items = $this->searchItems([
            'value' => $values,
        ]);

        return array_column($items, 'text', 'id');
    }

    public function getDefaultValue()
    {
        if (!$this->param('multiple')) {

            return $this->default;
        }

        if ($this->default === '') {
            return [];
        }

        return explode(',', $this->default);
    }

    public function filterInit()
    {
        if ($this->value_type == 'int') {
            $this->params['raw'] = true;
        }
    }

    public function validate($value)
    {
        if (is_array($value) ? empty($value) : $value === '') {

            return true;
        }

        if (!$this->param('multiple')) {
            $value = [ $value ];
        }

        $options = $this->getOptions();
        foreach ($value as $item) {
            if (!isset($options[$item])) {
                $this->error = '%s不是合法的值';

                return false;
            }
        }

        return true;
    }

    //protected function _labelView() { return ''; }

    public function view()
    {
        $is_multiple = $this->param('multiple');
        $name = $this->name . ($is_multiple ? '[]' : '');
        $multiple = $is_multiple ? ' multiple="multiple"' : '';
        $current_value = $this->getValue();

        if (!$is_multiple && $current_value) {
            $current_value = [ $current_value ];
        }

        $min_width = $this->param('minwidth', 150);

        $attrs = '';
        $options = [];
        if ($this->use_ajax_data) {
            $data_url = static::getDataUrl($this);
            $attrs = ' data-url="' . $data_url . '"';
            if ($current_value) {
                $options = $this->getSpecifiedOptions($current_value);
            }
        } else {
            $options = $this->getOptions();
        }
        $html = "<select name=\"{$name}\" {$multiple} {$attrs} class=\"chosen\" style=\"min-width:{$min_width}px\">";
        if (is_array($options)) {
            foreach ($options as $id => $name) {
                $html .= sprintf(
                    "<option value=\"%s\" %s>%s</option>",
                    h($id),
                    in_array($id, $current_value) ? 'selected="selected"' : '',
                    h(($this->text_with_id ? "[{$id}]" : '') . $name)
                );
            }
        }
        $html .= '</select>';

        return $html;
    }
}
