<?php
namespace MY\Filter;

class DateFilter extends \MY\Filter_Abstract {

    public function filterInit()
    {
        $this->params['format'] = 'Y-m-d';

        if ($this->param('month')) {
            $this->params['format'] = 'Y-m';
        }
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

        $id = 'f-date-' . ($index++);

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

        $script = "<script>\$('#{$id}').datepicker({$options})</script>";

        if ($this->param('daterange')) {
            $script = '';
        }

        return sprintf(
            '<input type="text" class="form-control date-picker" name="%s" placeholder="%s" value="%s" id="%s"/>%s',
            $this->name,
            $this->label,
            h(d(@$_GET[$this->name], $this->getDefaultValue())),
            $id,
            $script
        );
    }
}
