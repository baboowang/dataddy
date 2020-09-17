<?php
namespace MY\Filter;

class TimeRangeFilter extends TimeFilter
{
    protected $from_filter;
    protected $to_filter;

    public function filterInit()
    {

        $this->empty_value = [ '', '' ];
        $this->is_required = TRUE;

        $name = $this->name;

        $default = explode(',', $this->default);

        $params = $this->params;
        $params['macro'] = TRUE;

        $this->from_filter = new TimeFilter([
            'name' => "from_{$name}",
            'type' => 'time',
            'default' => $default ? $default[0] : '',
            'params' => $params,
            'data' => $this->data,
        ]);
        $this->from_filter->filterInit();

        $this->to_filter = new TimeFilter([
            'name' => "to_{$name}",
            'type' => 'time',
            'default' => $default ? (count($default) == 1 ? $default[0] : $default[1]) : '',
            'params' => $params,
            'data' => $this->data,
        ]);
        $this->to_filter->filterInit();

        $this->params['macro'] = TRUE;
        $this->params['replace_empty'] = TRUE;

        if (!isset($this->params['range'])) {
            $this->params['range'] = 30;
        }

        $this->error = $this->from_filter->error() . $this->to_filter->error();
    }

    public function getName()
    {
        return [ 'from_' . $this->name, 'to_' . $this->name ];
    }

    public function getDefaultValue()
    {
        return [ $this->from_filter->getDefaultValue(), $this->to_filter->getDefaultValue() ];
    }

    protected function _isempty($value)
    {
        return !$value || (parent::_isempty($value[0]) && parent::_isempty($value[1]));
    }

    protected function _isset($value)
    {
        return $value && !parent::_isempty($value[0]) && !parent::_isempty($value[1]);
    }

    public function validate($value)
    {
        if (!$this->_isset($value)) {
            $this->error = '%s未设置';

            return FALSE;
        }

        if (!$this->from_filter->validate($value[0])) {
            $this->error = $this->from_filter->error();

            return FALSE;
        }

        if (!$this->to_filter->validate($value[1])) {
            $this->error = $this->to_filter->error();

            return FALSE;
        }

        if (isset($this->params['range'])) {
            if ((strtotime($value[1]) - strtotime($value[0])) / 86400 > $this->params['range']) {
                $this->error = '%s日期范围限定在' . h($this->params['range']) . '天';

                return FALSE;
            }
        }

        return TRUE;
    }

    protected function _format($value)
    {
        return [$this->from_filter->_format($value[0]), $this->to_filter->_format($value[1])];
    }

    public function getMacroData()
    {
        $value = $this->getValue();

        $macro = [
            $this->from_filter->getName() => $this->param('raw') ? $value[0] : \GG\Db\Sql::es($value[0], TRUE),             
            $this->to_filter->getName() => $this->param('raw') ? $value[1] : \GG\Db\Sql::es($value[1], TRUE),             
        ];

        return $macro;
    }

    protected function _getValue()
    {
        return [$this->from_filter->_getValue(), $this->to_filter->_getValue()];
    }

    protected function _labelView() { return ''; }

    public function view()
    {
        static $index = 0;

        $id = 'f-trange-' . ($index++);

        $view = '<div class="input-group datetime-picker" id="' . $id . '">';
        $view .= $this->from_filter->view();
        $view .= '<span class="input-group-addon">至</span>';
        $view .= $this->to_filter->view();
        $view .= '</div>';

        $view .= <<<HTML
<script>
\$('#{$id} [name^=from_]').on('changeDate', function(){
    $('#{$id} [name^=to_]').datetimepicker('show');
});
</script>
HTML;
        return $view;
    }
}
