<?php
namespace MY\Filter;

class DateRangeFilter extends DateFilter
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
        $params['daterange'] = TRUE;

        $this->from_filter = new DateFilter([
            'name' => "from_{$name}",
            'type' => 'date',
            'default' => $default ? $default[0] : '',
            'params' => $params,
            'data' => $this->data,
        ]);
        $this->from_filter->filterInit();

        $this->to_filter = new DateFilter([
            'name' => "to_{$name}",
            'type' => 'date',
            'default' => $default ? (count($default) == 1 ? $default[0] : $default[1]) : '',
            'params' => $params,
            'data' => $this->data,
        ]);
        $this->to_filter->filterInit();

        $this->params['macro'] = TRUE;
        $this->params['replace_empty'] = TRUE;

        if (!isset($this->params['range']) && !$this->param('month')) {
            $this->params['range'] = 31;
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
        return [$this->from_filter->getValue(), $this->to_filter->getValue()];
    }

    protected function _labelView() { return ''; }

    public function view()
    {
        static $index = 0;

        $id = 'f-drange-' . ($index++);

        $view = '<div class="input-group date-picker input-daterange" id="' . $id . '">';
        $view .= $this->from_filter->view();
        $view .= '<span class="input-group-addon">至</span>';
        $view .= $this->to_filter->view();
        $view .= '</div>';

        $end_date = date('Y-m-d');
        if ($this->param('end')) {
            $end_date = date('Y-m-d', strtotime($this->param('end')));
        }

        $options = [
            'autoclose' => TRUE,
            'orientation' => 'left',
            'endDate' => $end_date,
        ];

        if ($this->param('month')) {
            $options['format'] = 'yyyy-mm';
            $options['minViewMode'] = 1;
        }

        $options = json_encode($options);

        $view .= <<<HTML
<script>
\$('#{$id}').datepicker({$options});
\$('#{$id} [name^=from_]').on('changeDate', function(){
    $('#{$id} [name^=to_]').datepicker('show');
});
</script>
HTML;
        return $view;
    }
}
