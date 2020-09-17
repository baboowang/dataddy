<?php
namespace MY\Filter;

class NumberFilter extends \MY\Filter_Abstract {

    public function filterInit()
    {
        $this->params['raw'] = TRUE;
    }

    public function validate($value)
    {
        if ($value === '') return TRUE;

        if ($this->param('multiple')) {
            $value = preg_split('@\s*,\s*@', $value);
        } else {
            $value = [ $value ];
        }

        foreach ($value as $item) {
            if (!is_numeric($item)) {
                $this->error = '%s不是数字';

                return FALSE;
            }

            if ($this->param('min') && $item < $this->param('min')) {
                $this->error = '%s最小值为' . $this->param('min');

                return FALSE;
            }

            if ($this->param('max') && $item > $this->param('max')) {
                $this->error = '%s最大值为' . $this->param('max');

                return FALSE;
            }
        }

        return TRUE;
    }

    public function view()
    {
        static $index = 0;

        $id = 'f-nb-' . ($index++);

        $type = 'numeric';
        $type_options = [
            'rightAlign' => false,
            'digits' => 0,
            'radixPoint' => '',
        ];

        if ($this->param('decimal')) {
            unset($type_options['digits']);
            unset($type_options['radixPoint']);
        }

        $custom_options = '|placeholder|digits|digitsOptional|radixPoint|radixFocus|groupSize|groupSeparator|autoGroup|allowPlus|allowMinus|integerDigits|integerOptional|prefix|suffix|rightAlign|decimalProtect|min|max|step|';

        foreach ($this->params as $name => $value) {
            if ($name && strpos($custom_options, $name) !== FALSE) {
                if (is_numeric($value)) {
                    $value = 1 * $value;
                }
                $type_options[$name] = $value;
            }
        }
        if ($this->param('multiple')) {
            $type = 'Regex';

            if ($this->param('decimal')) {
                $type_options = [ 'regex' => '-?[0-9]\\d*(\\.\\d*)?(?:\\s*,\\s*-?[0-9]\\d*(\\.\\d*)?)*' ];
            } else {
                $type_options = [ 'regex' => '-?[0-9]\\d*(?:\\s*,\\s*-?[0-9]\\d*)*' ];
            }
        }

        $type_options = json_encode($type_options);

        $script = <<<EOT
<script>
\$("#$id").inputmask('$type', $type_options);
</script>
EOT;
        $current_value = $this->getValue();

        if (is_array($current_value)) {
            $current_value = implode(',', $current_value);
        }

        return sprintf(
            '<input type="text" class="form-control" name="%s" placeholder="%s" value="%s" id="%s" />%s',
            $this->name,
            $this->label,
            h(d(@$_GET[$this->name], $current_value)),
            $id,
            $script
        );
    }
}
