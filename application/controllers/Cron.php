<?php
class CronController extends MY\Controller_Abstract {

    public function init()
    {
        parent::init();

        $this->form_validation = new \GG\FormValidation();
    }

    public function listJsonAction()
    {
        $cronlist = [];
        $where = [
            'crontab' => [
                '!=' => ''
            ]
        ];
        $attrs = [
            'select' => 'id,crontab,name'
        ];
        $ret = M('menuitem')->select($where,$attrs);
        foreach ($ret as $item){
            $nitem = [];
            $cron = preg_replace('@^#@', '', $item['crontab']);
            $nitem['cron'] = $cron;
            $nitem['report_id'] = $item['id'];
            $nitem['report_name'] = $item['name'];
            $nitem['enable'] = $cron == $item['crontab'];
            $cronlist[] = $nitem;
        }

        R('title', 'CRON列表');

        return response($cronlist);
    }

    public function detailJsonAction()
    {
        param_request([
            'id' => 'UNIT',
        ]);
        $cron_id = @$GLOBALS['req_id'];

        $data = [];

        if ($cron_id){
            $data['cron'] = M('menuitem')->find($cron_id);
            R('title', 'CRON更新:' . $data['cron']['name']);
        }

        return response($data);
    }
}
