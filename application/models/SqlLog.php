<?php
class SqlLogModel extends \GG\Db\Model\Base {
    const STATUS_INIT = 0;
    const STATUS_DONE = 1;
    const STATUS_ERROR = 2;

    private $basic_info = [];
    private $sql_time = [];

    public function __construct($basic_info = [])
    {
        $this->basic_info = $basic_info;
        parent::__construct(
            \GG\Config::get('database.table.prefix', '') . 'sql_log',
            'dataddy'
        );
    }

    public function startSql($sql, $dsn, &$error = '')
    {
        $key = md5($sql);
        $this->sql_time[$key] = [
            'start_time' => microtime(true),
        ];
        $insert_item = $this->basic_info + [
            'dsn' => $dsn,
            'sql_content' => $sql,
            'start_time' => date('Y-m-d H:i:s'),
        ];
        $id = $this->insert($insert_item, true);
        $this->sql_time[$key]['id'] = $id;

        return true;
    }

    public function endSql($sql, $error_msg = false)
    {
        $key = md5($sql);

        if (!isset($this->sql_time[$key])) {
            return;
        }

        $use_time = (int)round((microtime(true) - $this->sql_time[$key]['start_time']) * 1000);
        if ($this->sql_time[$key]['id']) {
            $this->update([
                'id' => $this->sql_time[$key]['id']
            ], [
                'end_time' => date('Y-m-d H:i:s'),
                'use_time' => $use_time,
                'status' => $error_msg ? self::STATUS_ERROR : self::STATUS_DONE,
            ]);
        }
    }
}
/* End of file filename.php */
