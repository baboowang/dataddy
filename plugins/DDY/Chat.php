<?php
namespace PL\DDY;
use GG\Config;
use GG\Db\Model\Base;
use MY\Plugin_Abstract;
use UserModel;

class Chat extends Plugin_Abstract {

    const MIN_SUBMIT_SECONDS = 5;

    public static $send_url;
    public static $data_url;

    public function pluginInit($dispatcher, $manager)
    {
        self::$send_url = $manager->registerData($this, 'message', 'sendMessage');
        self::$data_url = $manager->registerData($this, 'messages', 'getMessages');
    }

    private function getMessageTable()
    {
        $table_prefix = Config::get('database.table.prefix', '');

        return $table_prefix . 'chat_message';
    }

    private function getMessageModel()
    {
        return new Base($this->getMessageTable(), 'dataddy');
    }

    public function getMessages()
    {
        param_request([
            'from_id' => 'UINT',
        ]);

        $model = $this->getMessageModel();
        $where = [
        ];
        $attrs = [
            'order_by' => 'id DESC',
            'select' => 'id,uid,message,create_at',
            'limit' => 100,
        ];
        if ($GLOBALS['req_from_id']) {
            $where['id'] = ['>' => $GLOBALS['req_from_id']];
            $attrs['order_by'] = 'id ASC';
        }

        $rows = $model->select($where, $attrs);

        if (!$GLOBALS['req_from_id']) {
            $rows = array_reverse($rows);
        }
        $current_user = R('user');
        $current_uid = $current_user['id'];

        if ($rows) {
            $uids = array_unique(array_column($rows, 'uid'));
            $users = UserModel::getInstance()->select([
                'id' => $uids
            ], [
                'select' => 'id,nick,avatar,avatar_small',
            ]);
            array_change_key($users, 'id');
            foreach ($rows as &$row) {
                $user = $users[$row['uid']];
                $row['rtime'] = \short_date($row['create_at']);
                $row['me'] = $row['uid'] == $current_uid;
                $row['user'] = [
                    'nick' => $user['nick'],
                    'avatar' => $user['avatar_small'] ?: $user['avatar'],
                ];
                unset($row['create_at']);
            }
            unset($row);
        }
        $response = [
            'messages' => $rows,
            'from_id' => $rows ? $rows[count($rows) - 1]['id'] : null
        ];

        return response($response);
    }

    public function sendMessage()
    {
        param_request([
            'message' => 'STRING',
        ]);
        $user = R('user');

        $uid = $user['id'] ?? null;
        $message = $GLOBALS['req_message'];

        if (!$uid || !$message) {
            return response_error(CODE_ERR_PARAM);
        }

        $model = $this->getMessageModel();

        $insert = [
            'uid' => $uid,
            'message' => $message,
        ];

        $last_message = $model->selectOne([
            'uid' => $uid,
        ], [
            'order_by' => 'id DESC',
        ]);

        if ($last_message) {
            if ($last_message['message'] == $message) {
                return response_error(CODE_ERR_DENY, '不要重复提交哦~');
            }
            $interval = time() - strtotime($last_message['create_at']);
            if ($interval < self::MIN_SUBMIT_SECONDS) {
                return response_error(CODE_ERR_DENY, '你说话太快了..');
            }
        }
        if ($model->insert($insert)) {
            return response('ok');
        }

        return response(CODE_ERR_SYSTEM);
    }

    public function install()
    {
        $message_table = $this->getMessageTable();

        $sql = "
            CREATE TABLE IF NOT EXISTS {$message_table} (
                id int not null auto_increment primary key,
                topic_id int not null default 0,
                uid int not null default 0,
                message varchar(1024) null,
                create_at timestamp not null default current_timestamp
            ) engine=innodb default charset utf8mb4
        ";

        $model = $this->getMessageModel();

        if ($model->execute($sql)) {
            return true;
        }

        return false;
    }
}
