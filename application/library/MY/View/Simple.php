<?php
namespace MY;

/**
 * View_Simple
 *
 * @version //autogen//
 * @copyright Copyright (c) 2015 Adeaz Inc. All rights reserved.
 * @author Baboo.wg
 *
 * 配置tpl_vars
 * __inherit_file : 指定继承文件
 * __inherit_depth : 指定继承深度
 */
class View_Simple extends \Yaf\View\Simple {

    const INHERIT_DEPTH_DEFAULT = -1;

    public function render($tpl, $tpl_vars = NULL)
    {
        return $this->_render($tpl, $tpl_vars);
    }

    protected function _getConfig($name, $tpl_vars = array(), $default = NULL)
    {
        $name = "__$name";

        if ($tpl_vars && isset($tpl_vars[$name])) {

            return $tpl_vars[$name];
        }

        if ($this->_tpl_vars && isset($this->_tpl_vars[$name])) {

            return $this->_tpl_vars[$name];
        }

        return $default;
    }

    protected function _render($tpl, $tpl_vars = array())
    {
        $view_path = $this->getScriptPath();
        $view_ext = \Yaf\Application::app()->getConfig()->get('application.view.ext');
        $view_ext = $view_ext ?: 'phtml';
        $segments = explode('/', $this->_getConfig('inherit_file', $tpl_vars, $tpl));

        $content = parent::render($tpl, $tpl_vars);

        $inherit_depth = $this->_getConfig('inherit_depth', $tpl_vars, self::INHERIT_DEPTH_DEFAULT);

        $tpl_depth = $inherit_depth == self::INHERIT_DEPTH_DEFAULT ? count($segments) : $inherit_depth;

        if (\Yaf\Registry::get('is_ajax') && $inherit_depth == self::INHERIT_DEPTH_DEFAULT) {
            $tpl_depth = 0;
        }

        while (TRUE) {
            if ($tpl_depth <= 0 || empty($segments)) {

                break;
            }
            $tpl = array_pop($segments);
            $path = $view_path . '/' . implode('/', $segments);
            $base_tpl = $path . "/__base.{$view_ext}";
            if (file_exists($base_tpl)) {
                $tpl_vars['__content'] = $content;
                $view = implode('/', $segments) . ($segments ? '/' : '')
                      . "__base.{$view_ext}";
                $content = parent::render($view, $tpl_vars);
            }

            if (count($segments) == 0) {

                break;
            }
            --$tpl_depth;
        }

        if (isset($_SERVER['HTTP_PARTIAL'])) {
            $content_id = $_SERVER['HTTP_PARTIAL'];
            if (preg_match("@content-id=\"{$content_id}\">(.+?)<!--END {$contentId}-->@msi", $content, $ma)) {
                $content = $ma[1];
            } else {
                $content = '';
            }
        }

        return $content;
    }
}
/* End of file <`2:filename`>.php */
