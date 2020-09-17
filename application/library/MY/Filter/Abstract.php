<?php
namespace MY;

abstract class Filter_Abstract extends Plugin_Abstract {

    protected $name = '';
    protected $type;
    protected $params = [];
    protected $label = '';
    protected $default = '';
    protected $data;
    protected $report_options = [];

    protected $error;
    protected $is_required = FALSE;

    protected $empty_value = '';

    private $value_cache = NULL;

    public function __construct($options)
    {
        foreach ($options as $name => $value) {
            $this->$name = $value;
        }
    }

    public function pluginInit($dispatcher, $manager)
    {
    }

    public function filterInit()
    {
        $default = $this->getDefaultValue();

        if (!$this->_isempty($default)) {

            $this->validate($default);
        }
    }

    public function param($name, $default = FALSE)
    {
        return $this->params && isset($this->params[$name]) ? $this->params[$name] : $default;
    }

    public function getMacroData()
    {
        if ($this->param('macro')) {
            return [ $this->name => $this->getValue(TRUE) ];
        }

        return [];
    }

    public function error()
    {
        if ($this->error) {

            return sprintf($this->error, $this->label);
        }

        return '';
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDefaultValue()
    {
        return $this->default;
    }

    protected function _getValue()
    {
        return $this->data && isset($this->data[$this->name]) ? $this->data[$this->name] : $this->empty_value;
    }

    protected function _isset($value)
    {
        return !$value;
    }

    public function isempty()
    {
        return $this->_isempty($this->getValue());
    }

    protected function _isempty($value)
    {
        return is_null($value) || $value === '';
    }

    protected function _format($value)
    {
        return $value;
    }

    public function getValue($check_quote = FALSE)
    {
        $value = '';

        if (is_null($this->value_cache)) {

            $value = $this->_getValue();

            if ($this->_isempty($value)) {
                $value = $this->getDefaultValue();
            }

            if (!$this->validate($value)) {

                $value = $this->empty_value;
            }

            $this->value_cache = $value;
        } else {
            $value = $this->value_cache;
        }

        if (!$this->_isempty($value)) {
            $value = $this->_format($value);
        }

        if ($check_quote) {
            $quote = !$this->param('raw') && !$this->param('bare');
            if (is_array($value)) {
                if (!$this->param('raw')) {
                    $new_value = [];
                    foreach ($value as $ivalue) {
                        $new_value[] = \GG\Db\Sql::es($ivalue, $quote);
                    }
                    $value = $new_value;
                }
                $value = implode(',', $value);
            } else {
                if (!$this->param('raw')) {
                    $value = \GG\Db\Sql::es($value, $quote);
                }
            }

            if ($value == '' && $quote) {
                $value = "''";
            }
        }

        return $value;
    }

    public function getReplaceValue()
    {
        if ($this->param('macro')) {
            return '';
        }

        return $this->getValue(TRUE);
    }

    public function validate($val)
    {
        if ($this->is_required) {
            if (!$this->_isset($val)) {
                $this->error = '%s未设置';

                return FALSE;
            }
        }

        return TRUE;
    }

    public function display(&$names)
    {
        $has_error = $this->error ? ' has-error' : '';

        echo '<div class="form-group' . $has_error . '">';
        echo $this->_labelView();

        if ($has_error) {
            $error = h(strip_tags($this->error()));
            echo '<div class="input-icon right" style="display:inline-block">';
            echo '<i class="fa fa-exclamation tooltips" data-original-title="',
                $error,
                '" data-container="body"></i>';
        }

        echo $this->view();

        if ($has_error) {
            echo '</div>';
        }

        echo '</div>';

        $name = $this->getName();

        if (is_array($name)) {
            $names = array_merge($names, $name);
        } else {
            $names[] = $name;
        }
    }

    public abstract function view();

    protected function _labelView()
    {
        if ($this->label) {
            return sprintf("<label>%s：</label>", $this->label);
        }

        return '';
    }
}
