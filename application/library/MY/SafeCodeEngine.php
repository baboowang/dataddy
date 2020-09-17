<?php
namespace MY;
class SafeCodeEngine {
    public function execute($code)
    {
        ob_start();
        eval($code);
        return ob_get_clean();
    }
}
