<?php
namespace MY\Filter;

class TextFieldFilter extends \MY\Filter_Abstract {

    public function view() {

        return sprintf(
            '<input type="text" class="form-control" name="%s" placeholder="%s" value="%s"/>',
            $this->name,
            $this->label,
            h(d(@$_GET[$this->name], $this->getValue()))
        );
    }
}
