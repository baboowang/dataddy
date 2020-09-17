'use strict';

MetronicApp.controller('ReportController', ['$rootScope', '$scope', '$http', '$log', 'Notification', '$stateParams', '$modal', '$timeout', '$compile', '$interval',
    function($rootScope, $scope, $http, $log, Notification, $stateParams, $modal, $timeout, $compile, $interval) {

    $scope.params = $stateParams;

    $scope.edit_mode = false;
    $scope.writeable = false;
    $scope.autorefresh = false;
    $scope.seconds = 0;
    $scope.subject = '';
    $scope.receiver = '';
    $scope.stop_autorefresh = false;
    window.$log = $log;

    $.fn.datepicker.defaults.language = 'cn';
    $.fn.datepicker.defaults.format = 'yyyy-mm-dd';
    $.fn.datepicker.dates.cn = {
        days: ["日", "一", "二", "三", "四", "五", "六", "日"],
        daysShort: ["日", "一", "二", "三", "四", "五", "六", "日"],
        daysMin: ["日", "一", "二", "三", "四", "五", "六", "日"],
        months: ["一月", "二月", "三月", "四月", "五月", "六月", "七月", "八月", "九月", "十月", "十一月", "十二月"],
        monthsShort: ["一月", "二月", "三月", "四月", "五月", "六月", "七月", "八月", "九月", "十月", "十一月", "十二月"],
        today: "今天",
        clear: "清除"
    };

    window.set_table_options = function(tid, options) {
        var $table = $('table[id="rtable-' + tid + '"]');

        $table.data('options', options);

        if ($table.find('tr.child')) {
            var $trs = $table.find('tr').get();
            for (var i = 0; i < $trs.length; i++) {
                if (!$($trs[i]).is('.child') && $($trs[i+1]).is('.child')) {
                    $($trs[i]).find('td:first').prepend('<a href="javascript:;" onclick="toggle_child_row(this)"><i class="fa fa-plus"></i></a>&nbsp;');
                }
            }
            $table.find('tr.child').hide();
        }

        if (options.dt === false || options.merge_cell) {
            $table.closest('.portlet').find('.portlet-title .actions').prepend('<a class="btn btn-xs green download-csv" href="javascript:;">下载CSV</a>');
            return;
        }

        var $avg = $table.find('tbody.avg tr.avg'),
            $sum = $table.find('tbody.sum tr.sum'),
            col_map = {};

        $table.find('thead th').each(function(i, th){
            col_map[$.trim($(th).text())] = i;
        });

        if ($avg.length || $sum.length) {
            var first = true;

            $table.on('draw.dt', function(event, $dt){

                if (first) {
                    first = false;
                    return;
                }

                var sum = {}, avg = {};
                var $items = $table.find('tbody.items tr[role=row]:not(.summary)');

                $.each(options.fields, function(name, field_config) {
                    if (field_config.count) {
                        var col_index = col_map[field_config['header'] || name] || name;

                        if (!/^\d+$/.test(col_index)) {
                            $log.error('TABLE[' + tid + ']找不到列[' + name + ']');
                            return;
                        }

                        sum[col_index] = 0;

                        $items.each(function(){
                            sum[col_index] += 1 * (parseInt($.trim($(this).find('td:eq(' + col_index + ')').text()).replace(/,/g, '')) || 0);
                        });
                    }
                });

                $.each(sum, function(index, value) {
                    var force_to_int = !is_float(value);

                    if ($sum.length) {
                        $sum.find('td:eq(' + index + ')').html(fnum(value, force_to_int));
                    }

                    if ($avg.length) {
                        avg[index] = $items.length == 0 ? 0 : value / $items.length;
                        $avg.find('td:eq(' + index + ')').html(fnum(avg[index], force_to_int));
                    }
                });

                $.each([[$sum, sum], [$avg, avg]], function(i, t) {
                    if (!t[0].length) return;
                    $.each(options.fields, function(name, field_config) {
                        if (field_config.def) {
                            var exp = field_config.def.replace(/\{(.+?)\}/g, function(all, name){
                                var col_index = col_map[name] || name;

                                if (!/^\d+$/.test(col_index)) {
                                    $log.error('TABLE[' + tid + ']找不到列[' + name + ']');
                                    return;
                                }

                                return t[1][col_index] || 0;
                            });

                            $log.debug('execute exp:' + exp);

                            var temp = '-';
                            try {
                                eval('temp=' + exp);
                            } catch (e) {
                                $log.error('执行表达式错误：' + exp);
                            }

                            if (temp != '-') {
                                temp = fnum(temp, !is_float(temp));
                            }

                            var col_index = col_map[name] || name;

                            if (!/^\d+$/.test(col_index)) {
                                $log.error('TABLE[' + tid + ']找不到列[' + name + ']');
                                return;
                            }

                            t[0].find('td:eq(' + col_index + ')').html(temp);
                        }
                    });
                });
            });
        }

        if (options.edit) {
            DataddyEditor($log, report_id, tid, $table, options.edit, $stateParams);
        }
    };

    $scope.dtOptions = function(tid) {
        var $table = $('table[id="rtable-' + tid + '"]');

        if ($table.data('dt_options')) {

            return $table.data('dt_options');
        }

        var table_options = $table.data('options') || {};
        var options = table_options.dt ? table_options.dt : {};
        var vw = Math.max(document.documentElement.clientWidth, window.innerWidth || 0);
        if ($table.find('tbody.items tr').length > 20 && vw > 1000) {
            options.hasFixedHeader = true;
            options.fixedHeaderOptions = {
                offsetTop : $('.navbar').height()
            };
        }

        var is_time = /时间|日期|time/.test($table.find('thead th:eq(0)').text());
        if (is_time && !options.order) {
            var $trs = $table.find('tbody.items tr');
            if ($trs.length > 1 &&
                $trs.eq(0).find('td:eq(0)').text() != $trs.eq(1).find('td:eq(0)').text()
            ) {
                options.order = [
                    [0, 'desc']
                ];
            }
        }

        if (!options.order) { options.order = []; }

        options.retrieve = true;

        $table.data('dt_options', get_dtoption('report', options));

        return $table.data('dt_options');
    };

    var report_id = $stateParams.id;

    var origin_stop_autorefresh = null;

    var report_loading = function(flag) {
        loading($('.page-content'), flag);
    };

    var load_report = function(ext_param) {
        var param = 'id=' + report_id;

        if ($stateParams.query) {
            param += '&' + $stateParams.query;
        }

        if (ext_param) {
            param += '&' + ext_param;
        }

        report_loading(true);

        var cb = template_cb(function(cb){
            $log.debug("Template callback");
            $scope.$apply(function(){cb($scope);});
        });

        var $report_cnt = $('#report-cnt'),
            $page_cnt = $('.page-content'),
            w = parseInt($report_cnt.css('width'), 10),
            h = Math.max(parseInt($(document.body).css('height'), 10) - $report_cnt.offset().top - $page_cnt.offset().top - 100, 1000);

        param += '&_cw=' + w + '&_ch=' + h;

        $report_cnt.load('/report/index?_cb=' + cb + '&_r=' + Math.random(), param, function(){

            $('.fixedHeader').remove();

            report_loading(false);

            $('#report-cnt select').select2();
            $('#report-cnt .form-search').submit(function(event) {
                event.preventDefault();
                $rootScope.$state.go('report', { id : report_id, query : $(this).serialize() + '&_r=' + Math.random() });
            });
            $('#report-cnt .refresh-btn').submit(function(event) {
                event.preventDefault();
                load_report();
            });
            $('#report-cnt .tooltips').tooltip({container:'body'});
            $('#report-cnt .add-to-dashboard').click(function(event) {
                event.preventDefault();
                get_json('/dashboard/addReport', {
                    report_id : report_id,
                }, function (ret) {
                    notify('success', '添加报表至Dashboard成功');
                })
            });
            $compile($('#report-cnt').contents())($scope);

            if (origin_stop_autorefresh !== null) {
                $scope.stop_autorefresh = origin_stop_autorefresh;
                origin_stop_autorefresh = null;
            }

            if ($scope.autorefresh) {
                $scope.seconds = $scope.autorefresh;
                var delay = false;
                AutoRefresh(function(focus){
                    if ($scope.stop_autorefresh) return;
                    $scope.$apply(function(){

                        if ($scope.seconds > 0) {
                            $scope.seconds--;
                        } else {
                            $scope.seconds = $scope.autorefresh;
                        }

                        if ($scope.seconds == 0 || (focus && delay)) {
                            if (focus) {
                                delay = false;
                                load_report();
                                $log.debug('refresh');
                            } else {
                                $log.debug('bulr ignore refresh');
                                delay = true;
                            }
                        }
                    });
                });
            } else {
                AutoRefresh(null);
            }
        });
    };

    $scope.toggle_autorefresh = function() { $scope.stop_autorefresh = !$scope.stop_autorefresh; };

    $scope.$on('$viewContentLoaded', function() {
        // initialize core components
        Metronic.initAjax();

        load_report();
    });

    $scope.edit = function() {
        $scope.edit_mode = !$scope.edit_mode;

        if ($scope.edit_mode) {
            $('.fixedHeader').hide();
            origin_stop_autorefresh = $scope.stop_autorefresh;
            $scope.stop_autorefresh = true;
            $rootScope.$broadcast('menu_form_request', {
                id : report_id
            });
        } else {
            $('.fixedHeader').show();
            if (origin_stop_autorefresh !== null) {
                $scope.stop_autorefresh = origin_stop_autorefresh;
                origin_stop_autorefresh = null;
            }
        }
    };

    var pscope = $scope;

    $scope.refresh = function () {
        load_report();
    };
    $scope.show_sql = function() {
        $modal.open({
          template: '<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-hidden="true" ng-click="$dismiss()"></button><h4 class="modal-title">SQL</h4></div><div class="modal-body form"><ui-codemirror ui-codemirror-opts="sqlEditorOptions" ng-model="sql" ui-refresh="refresh"></ui-codemirror></div><div class="modal-footer"><button type="button" class="btn btn-default" ng-click="$dismiss()">关 闭</button></div>',
          size: 'lg',
          resolve: {
          },
          controller : function($scope) {
              $scope.sqlEditorOptions = {
                mode : 'text/x-mysql',
                lineNumbers: true,
                matchBrackets: true,
                indentWithTabs: true,
                readOnly : true,
                indentUnit: 4
              };
              $timeout(function() {
                  $scope.sql = pscope.sql;
                  $scope.refresh = true;
              }, 0);
          }
        });
    };

    $scope.send_mail = function() {
        var param = 'id=' + report_id;

        if ($stateParams.query) {
            param += '&' + $stateParams.query;
        }

        var pscope = $scope;
        $modal.open({
            templateUrl: '/views/report_email.html',
            size: '',
            resolve: {
            },
            controller : function($scope, $modalInstance) {
                $scope.receiver = pscope.receiver;
                $scope.subject = pscope.subject;
                $scope.ok = function () {
                    loading($('.sent-email'), true);
                    $http.get('/report/mail?_r=' + Math.random() + '&' +param+'&subject=' + $scope.subject + '&receiver=' + $scope.receiver).success(function(ret) {
                        loading($('.sent-email'), false);
                        if (ret && ret.code == 0) {
                            Notification.success(ret.message);
                            $modalInstance.close();
                            return;
                        }

                        Notification.error("发送邮件失败：" + (ret ? ret.message : ''));
                    });
                };

            }
        });

    };

    var destroy_handlers = [];
    destroy_handlers.push($rootScope.$on('menu_form_cancel', function() {
        $scope.edit_mode = false;
    }));

    destroy_handlers.push($rootScope.$on('menu_update', function() {
        $log.debug("menu update");
        $scope.edit_mode = false;
        load_report('_disable_cache=1');
    }));

    $scope.$on('$destroy', function() {
        $.each(destroy_handlers, function(i, h) {
            h();
        });
        $('.fixedHeader').remove();
        $log.debug('on destory');
    });

    $('#report-form-cnt .remove').click(function(event) {
        event.stopPropagation();
        $scope.$apply(function(){
            $scope.edit_mode = false;
        });
    });
}]);

var render_chart = (function(){

    function _table(report_id) {
        if (typeof report_id == 'object') return report_id;

        return $('#rtable-' + report_id);
    }

    function get_column_index(report_id, name, default_index)
    {
        if (/^\d+$/.test(name)) return name;

        var $ths = _table(report_id).find('thead tr:first th');

        for (var i = 0; i < $ths.length; i++) {
            if ($.trim($ths.eq(i).text()) == name) {
                return i;
            }
        }

        return typeof(default_index) == 'undefined' ? -1 : default_index;
    }

    var chart_tpl = {};
    chart_tpl['serial'] = {
        "type": "serial",
        "theme": "light",
        "categoryField": "time",
        "categoryAxis": {
            "parseDates": true,
            "minorGridEnabled": true
        },
        "chartCursor": {
            "pan": true,
            "valueLineEnabled": true,
            "valueLineBalloonEnabled": true,
            "cursorAlpha": 0,
            "valueLineAlpha": 0.2
        },
        "legend": {
            "useGraphSettings": true,
            "position": "top"
        },
        "balloon": {
            "borderThickness": 1,
            "shadowAlpha": 0
        },
        "export": {
            "enabled": true,
            "libs": {
                "path": "/assets/global/plugins/amcharts/amcharts/plugins/export/libs/"
            }
        },
        "graphs" : [],
        "dataProvider" : []
    };

    chart_tpl['pie']= {
        "type" : "pie",
        "startDuration": 0,
        "theme": "light",
        "addClassNames": true,
        "legend":{
            "position":"right",
            "marginRight":100,
            "autoMargins":false
        },
        "innerRadius": "30%",
        "defs": {
            "filter": [{
                "id": "shadow",
                "width": "200%",
                "height": "200%",
                "feOffset": {
                    "result": "offOut",
                    "in": "SourceAlpha",
                    "dx": 0,
                    "dy": 0
                },
                "feGaussianBlur": {
                    "result": "blurOut",
                    "in": "offOut",
                    "stdDeviation": 5
                },
                "feBlend": {
                    "in": "SourceGraphic",
                    "in2": "blurOut",
                    "mode": "normal"
                }
            }]
        },
        "export": {
            "enabled": true,
            "libs": {
                "path": "/assets/global/plugins/amcharts/amcharts/plugins/export/libs/"
            }
        },
        "titleField": "data-0",
        "valueField": "data-1",
        "dataProvider" : []
    };

    function render_amchart(report_id, index, options)
    {
        var $table = _table(report_id);

        options = $.extend(true, {}, chart_tpl[options.type || 'serial'] || chart_tpl['serial'], options);

        var graph_value_fields = [];
        var invalid_graph_index = [];

        $.each(options.graphs, function(i, graph) {
            graph = $.extend(true, {
                "bullet": "round",
                "bulletBorderAlpha": 1,
                "bulletColor": "#FFFFFF",
                "bulletSize": 5,
                "hideBulletsCount": 50,
                "lineThickness": 2,
                //"lineColor": "#e1ede9",
                "type": "smoothedLine",
                //"dashLength": 5,
                "title": graph.valueField,
                "useLineColorForBulletBorder": true,
                "balloonText": "[[title]]<br/><b style='font-size: 130%'>[[value]]</b>"
            }, graph);

            var value_field = graph.valueField;
            var value_column_index = get_column_index($table, value_field);
            if (value_column_index == -1) {
                $log.error("CHART[" + report_id + "] 列[" + value_field + "]找不到");
                invalid_graph_index.push(i);
                return;
            }

            graph.valueField = 'data-' + value_column_index;

            graph_value_fields.push([graph.valueField, value_column_index]);

            options.graphs[i] = graph;
        });

        $.each(invalid_graph_index, function(i, j) {
            options.graphs.splice(j - i, 1);
        });

        var $rows = $table.find('tbody.items tr');

        if (options.type == 'serial' && typeof options.dataDateFormat == 'undefined') {
            var time_format = $rows.eq(0).find('td:first').text()
                .replace(/\d{4}-\d{2}-\d{2}/, 'YYYY-MM-DD')
                .replace(/\d{2}:\d{2}:\d{2}/, 'JJ:NN:SS')
                .replace(/\d{2}:\d{2}/, 'JJ:NN')
                .replace(/^\d{10}$/, 'JJ');

            $log.debug('CHART[' + report_id + '] time_format:' + $rows.eq(0).find('td:first').text() + '=>' + time_format);

            if (/^[YMDJNS: -]+$/.test(time_format)) {
                if (/NN/.test(time_format)) {
                    options.categoryAxis.minPeriod = 'mm';
                } else if (/JJ/.test(time_format)) {
                    options.categoryAxis.minPeriod = 'hh';
                } else {
                    options.categoryAxis.minPeriod = 'DD';
                }

                //if (time_format == 'MM-DD JJ:NN') time_format = 'JJ:NN';
                options.dataDateFormat = time_format;
                options.chartCursor.categoryBalloonDateFormat = time_format;
                $log.debug('CHART[' + report_id + '] set dataDateFormat auto:' + time_format);
                //time_format = time_format.replace(/(YYYY-|:SS)/g, '');
                //if (time_format == 'MM-DD JJ:NN') time_format = 'JJ:NN';
                $.map(options.graphs, function(graph) {
                    graph.dateFormat = time_format;
                });
            } else {
                options.categoryField = "_x";
                options.categoryAxis = {
                    "gridPosition": "start"
                };
            }
        }

        if (options.type == 'pie') {
            var _dataProvider = {},
                _keyIndex = graph_value_fields[0],
                _valIndex = graph_value_fields[1],
                _dataProviderFields = [
                    _keyIndex[0],
                    _valIndex[0]
                ];
            options.valueField = graph_value_fields[1][0];
            options.titleField = graph_value_fields[0][0];
        }

        $rows.each(function() {
            var $cells = $(this).find('td');
            var data_item = {}, c;
            if (options.type == 'serial') {
                data_item[options.categoryField] = $.trim($cells.eq(0).text());
            }

            if (options.type == 'pie') {//如果是饼图的时候，对数据做

                var _key = $.trim($cells.eq(_keyIndex[1]).text());
                var _val = $.trim($cells.eq(_valIndex[1]).text());
                var _val = 1 * (_val.replace(/[,%]/g, '') || 0) || 0;

                if (typeof _dataProvider[_key] == 'undefined') {
                    _dataProvider[_key] = 0;
                }
                _dataProvider[_key] += _val;

            } else {
                for (var i = 0; i < graph_value_fields.length; i++) {
                    c = graph_value_fields[i];
                    var s = $.trim($cells.eq(c[1]).text());
                    var n = 1 * (s.replace(/[,%]/g, '') || 0);
                    data_item[c[0]] = isNaN(n) ? s : n;
                }

                options.dataProvider.push(data_item);
            }
        });

        //检测数据是否是按时间升序存储的，否则图表显示会存在问题
        options.dataProvider.sort(function(a, b) {
            if (a[options.categoryField] < b[options.categoryField]) {
                return -1;
            }
            if (a[options.categoryField] > b[options.categoryField]) {
                return 1;
            }
            return 0;
        });

        if (options.type == 'pie') {
            if (_dataProvider) {
                var isFloat = false;
                if (typeof options.is_float != 'undefined') {
                    isFloat = !!options.is_float;
                }
                for(var i in _dataProvider) {
                    var data_item = {};
                    data_item[_dataProviderFields[0]] = i;
                    if (isFloat) {
                        _dataProvider[i] = _dataProvider[i] . toFixed(options.is_float)*1;
                    }
                    data_item[_dataProviderFields[1]] = _dataProvider[i];
                    options.dataProvider.push(data_item);
                }
            }
        }

        var chart_cnt_id = 'rchart-' + report_id + '-' + index;

        $('#rchart-' + report_id).append('<div class="chart" id="' + chart_cnt_id +  '"></div>');

        $log.debug('chart option:', options);

        AmCharts.makeChart(chart_cnt_id, options);

        $('#' + chart_cnt_id).find('a[href*="amcharts"]').css('opacity', .1);
    }

    return function (report_id, options_arr) {
        $log.debug("render chart " + report_id, options);

        if (!(typeof options_arr == 'object' && typeof options_arr.length !== 'undefined')) {
            options_arr = [ options_arr ];
        }

        for (var i = 0; i < options_arr.length; i++) {
            var options = options_arr[i];

            var columns = '';

            if (typeof options == 'string') {
                columns = options;
                options = {};
            }

            if (typeof options != 'object') {
                $log.error("CHART[" + report_id + "]配置不是个对象");
                return;
            }

            if (!options.graphs) {
                options.graphs = [];
            }

            if (options.fields) { columns = options.fields; }

            if (columns) {
                $.each(columns.split(/,/), function(i, value_field) {
                    options.graphs.push({
                        valueField : value_field
                    });
                });
            }

            options_arr[i] = options;
        }

        $.each(options_arr, function(i, amchart_option) {
            render_amchart(report_id, i, amchart_option);
        });
    };
})();

function toggle_child_row(elem)
{
    var $elem = $(elem), $icon = $elem.find('i'), action = 'show';

    if ($icon.is('.fa-plus')) {
        $icon.removeClass('fa-plus').addClass('fa-minus');
        action = 'show';
    } else {
        $icon.removeClass('fa-minus').addClass('fa-plus');
        action = 'hide';
    }

    var $tr = $elem.closest('tr').next();

    while ($tr && $tr.length && $tr.is('.child')) {
        $tr[action]();
        $tr = $tr.next();
    }
}


function DataddyEditor($log, report_id, tid, $table, options, $stateParams)
{
    var column_map = {}, fields = [], field_map = {},
        pk_column_index = typeof options.pk != 'undefined' ? options.pk : 0;

    $table.find('thead th').each(function(i){
        var label = $.trim($(this).text());
        column_map[label] = i;
    });

    if (Object.prototype.toString.call(pk_column_index) !== '[object Array]') {
        pk_column_index = [ pk_column_index ];
    }

    for (var i = 0; i < pk_column_index.length; i++) {
        if (!/^\d+$/.test(pk_column_index[i])) {
            if (typeof column_map[pk_column_index[i]] == 'undefined') {
                $log.error('TABLE[' + tid + '] 列[' + pk_column_index[i] + ']找不到');
                return;
            }

            pk_column_index[i] = column_map[pk_column_index[i]];
        }
    }

    $.each(options.columns, function(label, field){
        if (typeof column_map[label] == 'undefined') {
            $log.error('TABLE[' + tid + '] 列[' + label + ']找不到');
            options.error = true;
            return false;
        }

        field.label = label;
        field.type = field.type || 'text';
        field.name = field.name || label;

        field_map[column_map[label]] = field;

        fields.push(field);
    });

    var edit_elem = null, origin;

    $table.find('tbody.items').on('click', 'td', function(event) {
        //event.preventDefault();

        var $t = $(this),
            $columns = $t.parent().find('td'),
            index = $columns.index($t),
            field = field_map[index]
            ;

        if (!field || edit_elem) return;

        origin = $t.text();

        var $input;

        if (field.type == 'select') {
            $input = $('<select/>');
            $.each(field.options, function(i, option) {
                $input.append('<option value="' + option.value + '">' + option.label + '</option>');
            });
        } else {
            $input = $('<input type="text" class="form-control input-sm" value=""/>');
        }
        $t.empty().append($input);
        $t.find(':input').val(origin)[0].focus();

        edit_elem = this;

        $input.blur(function(){
            var new_val = $input.val();
            if (new_val == origin || options.temp) {
                edit_elem = null;
                $t.empty().text(new_val);
                if (new_val != origin) {
                    $table.trigger('edit.change', [ $t ]);
                }
                return;
            }

            $input.prop('disabled', true);
            notify('info', '保存中...');

            var pk = $.map(pk_column_index, function(i){
                var $e = $columns.filter(':eq(' + i + ')');
                return $.trim($e.attr('eid') || $e.text());
            }).join(',');

            var data = {
                row_id : pk,
                dataddy_state : options.dataddy_state
            };
            data[field.name] = new_val;

            var param = 'id=' + report_id;
            if ($stateParams.query) {
                param += '&' + $stateParams.query;
            }
            $.post('/report/save?' + param, data, function(ret) {
                edit_elem = null;
                if (ret && ret.code == 0) {
                    notify('success', '保存成功');
                    $t.empty().text(new_val);
                    $table.trigger('edit.change', [ $t ]);
                } else {
                    notify('error', ret && ret.message || '保存失败');
                    $t.empty().text(origin);
                }
            }, 'json');
        });
    });
}
