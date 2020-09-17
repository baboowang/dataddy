<?php
class DashboardModel extends \GG\Db\Model\Base {
    private $uid;

    public function __construct($uid)
    {
        parent::__construct(
            \GG\Config::get('database.table.prefix', '') . 'dashboard',
            'dataddy'
        );

        $this->uid = $uid;
    }

    public function getDefaultDashboard($with_global = false)
    {
        $where = [
            'uid' => [$this->uid],
        ];
        $attrs = [
            'order_by' => 'uid DESC',
        ];

        if ($with_global) {
            $where['uid'][] = 0;
        }

        return $this->selectOne($where, $attrs);
    }

    public function getOrCreateUserDashboard()
    {
        $dashboard = $this->getDefaultDashboard();
        if (!$dashboard) {
            $dashboard = [
                'uid' => $this->uid,
                'title' => '我的Dashboard',
            ];
            if ($dashboard_id = $this->insert($dashboard, true)) {
                $dashboard['id'] = $dashboard_id;
            }
        }

        $dashboard['config'] = empty($dashboard['config']) ? [] : json_decode($dashboard['config'], true);
        return empty($dashboard['id']) ? null : $dashboard;
    }

    public function removeWidgetFromDashboard(&$dashboard, $report_id)
    {
        $layouts = $dashboard['config']['layout'] ?? [];

        $result = false;
        foreach ($layouts as $i => $layout) {
            if ($layout['type'] === 'report' && $layout['report_id'] == $report_id) {
                $result = true;
                unset($layouts[$i]);
            }
        }

        $dashboard['config']['layout'] = array_values($layouts);

        return $result;
    }

    public function addWidgetToDashboard(&$dashboard, $report_id, $report_config = [], &$error_msg = '')
    {
        $layouts = $dashboard['config']['layout'] ?? [];

        foreach ($layouts as $layout) {
            if ($layout['type'] === 'report' && $layout['report_id'] == $report_id) {
                $error_msg = '该报表已添加过';
                return false;
            }
        }

        $layouts[] = [
            'type' => 'report',
            'report_id' => (int)$report_id,
            'config' => (object)$report_config,
        ];

        $dashboard['config']['layout'] = $layouts;

        return true;
    }

    public function saveDashboard($dashboard)
    {
        if ($dashboard['id'] ?? false) {
            $update = [];
            foreach (['title', 'config'] as $field) {
                if (isset($dashboard[$field])) {
                    $update[$field] = $dashboard[$field];
                }
            }

            if (is_array($update['config'] ?? false)) {
                $update['config'] = json_encode($update['config']);
            }

            return $this->update(['id' => $dashboard['id']], $update);
        }

        return false;
    }

    public function sortWidgets(&$dashboard, $report_ids, &$error_msg = '')
    {

        if (!($dashboard['id'] ?? false)) {
            $error_msg = "Dashboard不存在";
            return false;
        }

        $widgets = $dashboard['config']['layout'] ?? [];

        $origin_reports = [];
        $origin_indexes = [];
        foreach ($widgets as $i => $widget) {
            if (($widget['report_id'] ?? false) && in_array($widget['report_id'], $report_ids)) {
                $origin_reports[$widget['report_id']] = $widget;
                $origin_indexes[] = $i;
            }
        }
        $origin_report_ids = array_keys($origin_reports);
        usort($origin_report_ids, function ($a, $b) use ($report_ids) {
            return array_search($a, $report_ids) - array_search($b, $report_ids);
        });
        foreach ($origin_indexes as $index) {
            $widgets[$index] = $origin_reports[array_shift($origin_report_ids)];
        }

        $dashboard['config']['layout'] = array_filter($widgets);

        return true;
    }
}
/* End of file filename.php */
