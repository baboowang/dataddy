<?php
namespace MY\Filter;

class CombineFilter extends \MY\Filter_Abstract {
    private $combine = [];

    public function filterInit()
    {
        foreach ($this->params as $name => $value) {
            if ($name === $value) {
                $this->combine[] = $name;
            }
        }

        $this->params['macro'] = TRUE;
        $this->params['replace_empty'] = TRUE;
    }

    public function getDefaultValue()
    {
        return $this->getValue();
    }

    public function getValue($check_quote = FALSE)
    {
        $value = '';

        foreach ($this->combine as $name) {
            if (isset($this->data[$name])) {
                $value .= $this->data[$name];
            }
        }

        if ($check_quote && !$this->param('raw')) {
            $value = \GG\Db\Sql::es($value, TRUE);
        }

        return $value;
    }

    public function display(&$names) {}

    public function view()
    {
        return '';
    }
}
