<?php
namespace GG;

class DocBlock {

    public $docblock,
        $description = null,
        $all_params = array();

    function __construct($docblock) {
        if( !is_string($docblock) ) {
            throw new DataddyException("DocBlock expects first parameter to be a string");
        }

        $this->docblock = $docblock;
        $this->parse_block();
    }

    public function __get($param_name) {
        return $this->$param_name();
    }

    public function __call($param_name, $values = null) {
        if( $param_name == "description" ) {
            return $this->description;
        }else if( isset($this->all_params[$param_name]) ) {
            $params = $this->all_params[$param_name];

            if( count($params) == 1 ) {
                return $params[0];
            }else {
                return $params;
            }
        }

        return null;
    }


    private function parse_block() {
        foreach(preg_split("/(\r?\n)/", $this->docblock) as $line){
            if( preg_match('/^(?=\s+?\*[^\/])(.+)/', $line, $matches) ) {
                $info = $matches[1];
                $info = trim($info);
                $info = preg_replace('/^(\*\s+?)/', '', $info);

                if( $info[0] !== "@" ) {
                    $this->description .= "\n$info";
                    continue;
                }else {
                    preg_match('/@(\w+)/', $info, $matches);
                    $param_name = $matches[1];

                    $value = str_replace("@$param_name ", '', $info);

                    if( !isset($this->all_params[$param_name]) ) {
                        $this->all_params[$param_name] = array();
                    }

                    $this->all_params[$param_name][] = $value;
                    continue;
                }
            }
        }
    }
}