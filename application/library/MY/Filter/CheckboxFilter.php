<?php
namespace MY\Filter;

class CheckboxFilter extends \MY\Filter_Abstract {
    static $ID_COUNT = 0;

    protected $empty_value = FALSE;

    
    public function filterInit()
    {
        $this->params['macro'] = TRUE;
    }

    public function _getValue()
    {
        $value = parent::_getValue();

        return !(!$value || in_array(strtolower($value), ['no', 'off', '0', 'false']));
    }

    public function getDefaultValue()
    {
        return !$this->_isEmpty($this->default);
    }

    protected function _labelView() { return ''; } 

    public function view() {
        
        $id = 'filter-check-' . (++self::$ID_COUNT);

        $value = $this->getValue();

        $checked = $value ? 'checked="checked"' : '';

        $html = <<<EOT
<div class="md-checkbox-inline">
    <div class="md-checkbox">
        <input type="checkbox" name="%s", id="$id" class="md-check" $checked/>
        <label for="$id">
        <span></span>
        <span class="check"></span>
        <span class="box"></span>
        %s</label>
    </div>
</div>
EOT;
        return sprintf(
            $html,
            $this->name,
            $this->label
        );
    }
}
