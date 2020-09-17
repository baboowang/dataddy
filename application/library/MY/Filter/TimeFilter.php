<?php
namespace MY\Filter;

class TimeFilter extends \MY\Filter_Abstract {
    
    public function filterInit()
    {
        if (!$this->param('format')) {
            $this->params['format'] = 'Y-m-d H:i';

            if ($this->param('hour')) {
                $this->params['format'] = 'Y-m-d H:00';
            }
        }
    }

    protected function _format($value)
    {
        $format = $this->param('oformat', $this->param('format'));

        return date($format, strtotime($value));
    }

    public function getDefaultValue()
    {
        if ($this->default) {
            return date($this->param('format'), strtotime($this->default));
        }

        return '';
    }

    public function validate($val)
    {
        if ($val != date($this->param('format'), strtotime($val))) { 
            $this->error = '%s格式不正确';

            return FALSE;
        }

        if ($this->params && isset($this->params['limit'])) {
            $limitDate = date('Y-m-d', strtotime('-' . $this->params['limit'] . ' days'));
            if (date('Y-m-d', strtotime($val)) < $limitDate) {
                $this->error = '%s需要小于' . $limitDate;

                return FALSE;
            }
        }

        return TRUE;
    }

    public function view()
    {
        static $index = 0;

        $id = 'f-time-' . ($index++);
        
        $options = [
            'format' => 'yyyy-mm-dd hh:ii',
            'endDate' => date('Y-m-d H:i'),
            'minView' => 0,
            'autoclose' => true,
        ];

        if ($this->param('limit')) {
            $options['startDate'] = date('Y-m-d', strtotime('-' . $this->params['limit'] . ' days'));
        }

        if ($this->param('hour')) {
            $options['minView'] = 1;
        } else if ($this->param('step')) {
            $options['minuteStep'] = $this->param('step') * 1;
        }

        $options = json_encode($options); 
        $script = <<<HTML
<script>
\$('#{$id}').datetimepicker({$options});
</script>
HTML;
        return sprintf(
            '<input type="text" class="form-control datetime-picker" name="%s" placeholder="%s" value="%s" id="%s"/>%s',
            $this->name,
            $this->label,
            h(d(@$_GET[$this->name], $this->getDefaultValue())),
            $id,
            $script
        );
    }
}
