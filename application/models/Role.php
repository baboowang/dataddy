<?php
class RoleModel extends \GG\Db\Model\Base {
    public function __construct()
    {
        parent::__construct(
            \GG\Config::get('database.table.prefix', '') . 'role',
            'dataddy'
        );
        $this->addEventHandler(new \GG\Db\DataVersion(new DataVersionModel()));
    }
}
/* End of file filename.php */
