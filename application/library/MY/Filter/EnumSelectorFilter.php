<?php
namespace MY\Filter;

class EnumSelectorFilter extends \MY\Filter_Abstract {
    protected $options;

    public function filterInit()
    {
        $this->options = [];

        foreach ($this->params as $name => $value) {
            if (is_string($value)) {
                $this->options[$name] = $value;
            }
        }

        parent::filterInit();
    }

    public function getDefaultValue()
    {
        if (!$this->param('multiple')) return $this->default;

        if ($this->default === '') return [];

        return explode(',', $this->default);
    }

    public function validate($value)
    {
        if (is_array($value) ? empty($value) : $value === '') return TRUE;

        if (!$this->param('multiple')) {
            $value = [ $value ];
        }

        foreach ($value as $item) {
            if (!isset($this->options[$item])) {
                $this->error = '%s不是合法的值';

                return FALSE;
            }
        }

        return TRUE;
    }

    public function view()
    {
        $is_multiple = $this->param('multiple');
        $name = $this->name . ($is_multiple ? '[]' : '');
        $multiple = $is_multiple ? ' multiple="multiple"' : '';
        $current_value = $this->getValue();

        if (!$is_multiple) {
            $current_value = [ $current_value ];
        }

        $min_width = $this->param('minwidth', 150);

        $html = "<select name=\"{$name}\" {$multiple} class=\"chosen\" style=\"min-width:{$min_width}px\">";
        if (is_array($this->options)) {
            foreach ($this->options as $value => $key) {
                $html .= sprintf(
                    "<option value=\"%s\" %s>%s</option>",
                    h($value),
                    in_array($value, $current_value) ? 'selected="selected"' : '',
                    h($key)
                );
            }
        }
        $html .= '</select>';

        return $html;
    }
}
