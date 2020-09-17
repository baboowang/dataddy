<?php
class TrustTicketModel extends \GG\Db\Model\Base {
    public function __construct()
    {
        parent::__construct(
            \GG\Config::get('database.table.prefix', '') . 'trust_ticket',
            'dataddy'
        );
    }

    public function getTicket($server_ip, $client_ip, $user)
    {
        $where = [
            'username' => $user,
            'client_ip' => $client_ip,
            'server' => $server_ip,
            'expire_time' => [ '>' => date('Y-m-d H:i:s', time() + 3600*4) ]
        ];
        $exists_row = $this->selectOne($where);
        if ($exists_row) {
            $ticket = $exists_row['ticket'];
        } else {
            $user_info = UserModel::getInstance()->selectOne(['username' => $user]);
            if (!$user_info) {
                return false;
            }
            $secret_key = Config::get('secret.key');
            $ticket = md5($secret_key . $client_ip . $user . uniqid());
            $this->insert([
                'username' => $user,
                'uid' => $user_info['id'],
                'server' => $server_ip,
                'ticket' => $ticket,
                'client_ip' => $client_ip,
                'expire_time' => date('Y-m-d H:i:s', time() + 3600*8),
            ]);
        }

        return $ticket;
    }

    public function getTicketInfo($ticket, $client_ip)
    {
        $where = [
            'ticket' => $ticket,
        ];

        $info = $this->selectOne($where);

        if (!$info || /*$info['client_ip'] != $client_ip ||*/ $info['expire_time'] <= date('Y-m-d H:i:s')) {
            return false;
        }

        return [
            'uid' => $info['uid'],
            'username' => $info['username'],
        ];
    }
}
/* End of file filename.php */
