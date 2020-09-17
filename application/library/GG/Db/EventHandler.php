<?php
namespace GG\Db;

class EventHandler
{
    public function beforeInsert ($model, &$data) {}

    public function afterInsert ($model, $data, $lastId) {}

    public function beforeUpdate ($model, &$where, $data) {}

    public function afterUpdate ($model, $where, $data) {}

    public function beforeReplace ($model, &$data, &$replace) {}

    public function afterReplace ($model, $data, $replace) {}

    public function beforeDelete ($model, &$where) {}

    public function afterDelete ($model, $where) {}
}
