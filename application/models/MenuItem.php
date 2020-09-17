<?php
class MenuItemModel extends \GG\Db\Model\Base {
    public function __construct()
    {
        parent::__construct(
            \GG\Config::get('database.table.prefix', '') . 'menuitem',
            'dataddy'
        );

        $this->addEventHandler(new \GG\Db\DataVersion(new DataVersionModel(), 'id', [
          'safe_code', 'modify_time', 'sort',
          'dev_version_time', 'dev_content', 'dev_safe_code', 'dev_uid',
        ]));
    }

}
/* End of file filename.php */
