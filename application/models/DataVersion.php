<?php
class DataVersionModel extends \GG\Db\Model\Base {
    public function __construct()
    {
        parent::__construct(
            \GG\Config::get('database.table.prefix', '') . 'data_version',
            'dataddy'
        );
    }
}
/* End of file filename.php */
