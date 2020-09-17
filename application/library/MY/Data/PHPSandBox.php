<?php
namespace MY;

class Data_PHPSandBox
{

    public static function box()
    {
        /** @var \PHPSandbox\PHPSandbox $sandbox */
        $sandbox = new \PHPSandbox\PHPSandbox;
        #$sandbox->error_level = E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_WARNING & ~E_NOTICE;
        #$sandbox->restore_error_level = FALSE;
        $sandbox->set_option([
            'allow_functions',
            'allow_variables',
            'allow_escaping',
            'allow_closures',
        ], true);
        $sandbox->setFuncValidator(function($function_name, \PHPSandbox\PHPSandbox $sandbox)
        {
            $allow_functions = [
                'print',
                'var_dump',
                'json_encode',
                'json_decode',
                'count',
                'array',
                'sizeof',
                'is_array',
                'is_bool',
                'is_numeric',
                'is_string',
                'trim',
                'date',
                'time',
                'strtotime',
                'printf',
                'sprintf',
                'number_format',
                'implode',
                'explode',
                'substr',
                'preg_match',
                'preg_match_all',
                'preg_split',
                'preg_replace',
                'parse_url',
                'parse_str',
                'http_build_query',
                'round',
                'intval',
                'ceil',
                'floor',
                'rand',
                'abs',
                'usort',
                'uasort',
                'uksort',
                'sort',
                'asort',
                'arsort',
                'ksort',
                'krsort',
                'min',
                'max',
                'request_param',
                'get_param',
                'post_param',
                'extract',
                'in_array',
                'mb_strlen',
                'mb_substr',
                'md5',
                'base64_encode',
                'base64_decode',
                'h',
            ];
            if(in_array($function_name, $allow_functions)) {

                return true;
            }
            if(($functions = \GG\Config::get('allow_functions'))) {
                if(is_array($functions)) {
                    if(in_array($function_name, $functions)) {

                        return true;
                    }
                }
            }
            $result = preg_match('@^(ddy|array|str|url)@', $function_name);

            return $result;
        });
        $sandbox->setErrorHandler(function($errno, $errstr, $errfile, $errline, $errcontext)
        {
            //"$errline:$errstr";
            //exit;
            if(preg_match('@Undefined index@', $errstr)) {
                return;
            }
            throw new \Exception("PHP ERROR:$errstr:$errfile:$errline");
        });
        $sandbox->setExceptionHandler(function($e)
        {
            //echo $e;
            //exit;
            throw $e;
        });
        $sandbox->setValidationErrorHandler(function($error)
        {
            if($error->getCode() == \PHPSandbox\Error::PARSER_ERROR) {
                //echo $error;
                //exit;
            }
            throw $error;
        });
        $sandbox->capture_output = true;

        return $sandbox;
    }
}
