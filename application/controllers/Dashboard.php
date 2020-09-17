<?php
use MY\PluginManager;

class DashboardController extends MY\Controller_Abstract {
    public function dataJsonAction()
    {
        $user = R('user');
        $dashboard_model = new DashboardModel($user['id']);
        $dashboard = $dashboard_model->getOrCreateUserDashboard();
        if (!$dashboard) {
            return response([]);
        }

        $layouts = $dashboard['config']['layout'] ?? [];
        foreach ($layouts as $i => $layout) {
            if ($layout['type'] === 'report') {
                if (!$this->permission->check("report.{$layout['report_id']}", 'r')) {
                    unset($layouts[$i]);
                }
            }
        }
        $dashboard['config']['layout'] = array_values($layouts);

        return response($dashboard);
    }

    public function activityJsonAction()
    {
        $user = R('user');

        $data = [];

        $rows = M('statistic')->select([
            'access_user' => $user['nick']
        ], [
            'limit' => 500,
            'order_by' => 'id DESC',
        ]);
        $activities = [];
        $max_activities = 20;
        $reports = [];
        $max_reports = 20;
        $map = [];
        $report_stat = [];
        $max_cond = 3;
        $last_url = '';

        foreach ($rows as $row) {
            $title = $row['page_name'];
            $time = $row['start_time'];
            $url = $row['page_url'];

            if (empty($title)) continue;

            if (date('Y-m-d', strtotime($time)) == date('Y-m-d')) {
                $short_time = date('H:i', strtotime($time));
            } else {
                $short_time = date('m-d H:i', strtotime($time));
            }

            $real_url = preg_replace('@(?<=[?&])_\w+=[^&]+&?@', '', $url);
            if (count($activities) < $max_activities && $last_url != $real_url) {
                $last_url = $real_url;

                $activity = [
                    'icon' => 'fa-bar-chart-o',
                    'title' => $title,
                    'time' => $short_time,
                ];

                if (preg_match('@^/report/index@', $url)) {
                    $url_info = parse_url($url);
                    $params = [];
                    parse_str($url_info['query'], $params);
                    if (isset($params['id'])) {
                        $activity['action_name'] = '查看报表';
                        $action_url = "#/report/{$params['id']}";
                        unset($params['id']);
                        foreach (array_keys($params) as $name) {
                            if (preg_match('@^_@', $name)) {
                                unset($params[$name]);
                            }
                        }

                        if ($params) {
                            $action_url .= '?query=' . urlencode(http_build_query($params));
                        }

                        $activity['action_url'] = $action_url;
                    }
                }

                $activities[] = $activity;
            }

            if (preg_match('@^/report/index@', $url)) {
                $url_info = parse_url($url);
                $params = [];
                parse_str($url_info['query'], $params);
                if (!isset($params['id'])) continue;
                $id = $params['id'];
                unset($params['id']);
                if (!isset($report_stat[$id])) {
                    $report_stat[$id] = [
                        'id' => $id,
                        'count' => 0,
                        'total_time' => 0,
                        'url' => "#/report/{$id}",
                        'name' => array_last(preg_split('@:@u', $title, 2)),
                        'last_access_time' => $time,
                        'last_time' => $short_time,
                        'conds' => [],
                    ];
                }
                $report_stat[$id]['count']++;
                $report_stat[$id]['total_time'] += $row['use_time'];

                $cond = [];
                foreach (array_keys($params) as $name) {
                    if (preg_match('@^_@', $name)) {
                        continue;
                    }

                    //日期，变更为相对时间
                    if (is_string($params[$name]) && preg_match('@^\d{4}-\d{2}-\d{2}@', $params[$name])) {
                        $rel_days = floor((strtotime($time) - strtotime($params[$name])) / 86400);
                        $cond[$name] = date('Y-m-d', strtotime("-{$rel_days}days"));
                    } else {
                        $cond[$name] = $params[$name];
                    }
                }
                if ($cond) {
                    ksort($cond);
                    @$report_stat[$id]['conds'][http_build_query($cond)]++;
                }
            }
        }

        usort($report_stat, function($a, $b){
            $g = 0.2;
            $houra = round((time() - strtotime($a['last_access_time'])) / 3600);
            $hourb = round((time() - strtotime($b['last_access_time'])) / 3600);
            $av = $a['count'] / pow($houra+2, $g);
            $bv = $b['count'] / pow($hourb+2, $g);
            return $bv - $av > 0 ? 1 : -1;
        });

        foreach ($report_stat as $report) {
            if (count($reports) >= $max_reports) break;
            $report['avg_time'] = round($report['total_time'] / $report['count'], 2);
            $conds = $report['conds'];
            if ($conds) {
                uasort($conds, function($a, $b) { return $b - $a; });
                $new_conds = [];
                foreach ($conds as $cond => $count) {
                    $params = [];
                    parse_str($cond, $params);
                    $name = [];
                    foreach ($params as $key => $value) {
                        $name[] = "<span class='text-info'>{$key}</span>=<span class='text-danger'>{$value}</span>";
                    }
                    $new_conds[] = [ 'name' => implode("&", $name), 'url' => "#/report/{$report['id']}?query=" . urlencode($cond) ];
                    if (count($new_conds) >= $max_cond) break;
                }
                $report['conds'] = $new_conds;
            }
            $reports[] = $report;
        }

        $data['activities'] = $activities;
        $data['reports'] = $reports;
        $data['chat_enable'] = PluginManager::getInstance()->isRegistered('DDY\\Chat');
        if ($data['chat_enable']) {
            $data['chat'] = [
                'send_url' => \PL\DDY\Chat::$send_url,
                'data_url' => \PL\DDY\Chat::$data_url,
            ];
        }
        return response($data);
    }

    public function removeWidgetAction()
    {
        return $this->removeReportAction();
    }

    public function removeReportAction()
    {
        param_request([
            'report_id' => 'UINT'
        ]);

        $report_id = $GLOBALS['req_report_id'];
        if (!$report_id) {
            return response_error(CODE_ERR_PARAM);
        }

        $user = R('user');
        $dashboard_model = new DashboardModel($user['id']);
        $dashboard = $dashboard_model->getOrCreateUserDashboard();
        if ($dashboard) {
            if ($dashboard_model->removeWidgetFromDashboard($dashboard, $report_id)) {
                if ($dashboard_model->saveDashboard($dashboard)) {
                    return response('ok');
                }
            } else {
                return response_error(CODE_ERR_PARAM, 'Dashboard未包含该报表');
            }
        }

        return response_error(CODE_ERR_SYSTEM);
    }

    public function addWidgetAction()
    {
        return $this->addReportAction();
    }

    public function addReportAction()
    {
        $user = R('user');

        param_request([
            'report_id' => 'UINT'
        ]);

        $report_id = $GLOBALS['req_report_id'];
        if (!$report_id) {
            return response_error(CODE_ERR_PARAM);
        }

        if (!$this->permission->check("report.{$report_id}", 'r')) {
            return response_error(CODE_ERR_DENY);
        }

        $report = M('menuItem')->find($report_id);
        $settings = my_json_decode($report['settings'] ?: '{}', true) ?: [];
        $widget_config = $settings['widget'] ?? [];
        $dashboard_model = new DashboardModel($user['id']);
        $dashboard = $dashboard_model->getOrCreateUserDashboard();
        if ($dashboard) {
            $error = '';
            if ($dashboard_model->addWidgetToDashboard($dashboard, $report_id, $widget_config, $error)) {
                if ($dashboard_model->saveDashboard($dashboard)) {
                    return response('ok');
                }
            } else {
                return response_error(CODE_ERR_PARAM, $error);
            }
        }

        return response_error(CODE_ERR_SYSTEM);
    }

    public function sortWidgetsAction()
    {
        param_request([
            'report_ids' => 'STRING'
        ]);

        $report_ids = array_filter(array_map('intval', explode(',', $GLOBALS['req_report_ids'] ?? '')));

        if (!$report_ids) {
            return response_error(CODE_ERR_PARAM);
        }

        $dashboard_model = new DashboardModel($this->data['myuid']);
        $dashboard = $dashboard_model->getOrCreateUserDashboard();
        if (!$dashboard) {
            return response_error(CODE_ERR_SYSTEM);
        }

        $error = '';
        if ($dashboard_model->sortWidgets($dashboard, $report_ids, $error)) {
            if ($dashboard_model->saveDashboard($dashboard)) {
                return response('ok');
            }
        } else {
            return response_error(CODE_ERR_PARAM, $error);
        }
    }
}
/* End of file <`2:filename`>.php */
