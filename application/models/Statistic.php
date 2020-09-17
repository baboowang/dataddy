<?php
class StatisticModel extends \GG\Db\Model\Base {
    public function __construct()
    {
        parent::__construct(
            \GG\Config::get('database.table.prefix', '') . 'statistic',
            'dataddy'
        );
    }

}
/* End of file filename.php */
