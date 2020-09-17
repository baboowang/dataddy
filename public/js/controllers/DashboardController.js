'use strict';

MetronicApp.controller('DashboardController', ['$rootScope', '$scope', '$http', '$log', 'Notification', '$stateParams', '$modal', '$timeout', '$compile', '$interval',
    function($rootScope, $scope, $http, $log, Notification, $stateParams, $modal, $timeout, $compile, $interval) {

    $scope.activities = [];
    $scope.reports = [];
    $scope.layouts = [];
    $scope.rows = [];
    $scope.chat_enable = false;

    var fillWidgets = function (widgets) {
        var rows = [];
        $.each(widgets, function(index, widget) {
            var last_row_index = rows.length - 1;
            widget.cnt_id = 'widget-' + index;
            widget.refresh_progress = 0;
            widget.config = widget.config || {};
            var size = 0;
            $.each(rows[last_row_index] || [], function (i, w) { size += w.config.size || 1; });
            size += widget.config.size || 1;
            if (last_row_index < 0 || size > 2) {
                last_row_index = rows.push([]) - 1;
            }
            rows[last_row_index].push(widget);
        });
        $scope.rows = rows;
        setTimeout(function() {
            $.each(widgets, function (i, widget) {
                $.each(widget.widgets || [], function (index, sub_widget) {
                    if (sub_widget.chart) {
                        render_chart(widget.cnt_id + '-' + index, sub_widget);
                    }
                });
            });
        }, 0);
    };

    var refresh_data = function() {
        get_json('/dashboard/activity.json', function(data){
            $scope.activities = data.activities || [];
            $scope.reports = data.reports || [];
            $scope.chat_enable = data.chat_enable;
            if ($scope.chat_enable) {
                startChatService(data.chat);
                setTimeout(function() {
                    Metronic.initSlimScroll('.scroller');
                }, 0);
            }
        });
        get_json('/dashboard/data.json', function(data) {
            $scope.page = data;
            if (data.config && data.config.layout) {
                fillWidgets(data.config.layout);
            }
        });
    };

    $scope.chat_data = {
        messages : [],
    };

    var startChatService = (function () {
        var init = false;
        var chat_options;
        var refreshing = false;
        var from_id = null;
        var fetch_message_timers = [ 3, 5, 10 ];

        var pull_message = function (cb) {
            if (refreshing) {
                return;
            }
            refreshing = true;
            get_json(chat_options.data_url, {from_id : from_id || 0}, function (ret) {
                cb && cb(ret);
                refreshing = false;
                if (ret.messages && ret.messages.length > 0) {
                    from_id = ret.from_id;
                    [].push.apply($scope.chat_data.messages, ret.messages);
                    setTimeout(function () {
                        $('#chats .scroller').slimScroll({scrollBy: '100000px'});
                    }, 0);
                }
            });
        };
        var send_message = function (message, $elem) {
            if ($elem) loading($elem, true);
            get_json(chat_options.send_url, {message:message}, function (ret) {
                if ($elem) {
                    $elem.val('');
                }
                pull_message();
            }, $elem);
        };

        $scope.sendChatMessage = function () {
            var $input = $('#chat-message-input'),
                message = $.trim($input.val());

            if (!message) {
                return;
            }

            send_message(message, $input);
        };

        return function (_chat_options) {
            if (init) return;
            init = true;
            chat_options = _chat_options;
            pull_message();
            var pull_timespan_when_has_message = 3;
            var pull_timespan_when_idle = [ 3, 5, 10, 15, 30, 60, 300 ];
            var next_pull_seconds = pull_timespan_when_has_message;
            var idle_count = 0;
            var second_tick = 0;
            chat_message_timer = setInterval(function () {
                ++second_tick;
                if (--next_pull_seconds <= 0) {
                    pull_message(function(ret) {
                        if (ret && ret.messages && ret.messages.length > 0) {
                            idle_count = 0;
                            next_pull_seconds = pull_timespan_when_has_message;
                        } else {
                            ++idle_count;
                            next_pull_seconds = pull_timespan_when_idle[
                                Math.max(idle_count - 1, pull_timespan_when_idle.length - 1)
                            ];
                        }
                    });
                }
            }, 1000);
        };
    })();
    var refresh_timer = null;
    var chat_message_timer = null;

    $scope.refreshWidget = function(widget, elem) {
        if (widget.refresh_state.refreshing) {
            return;
        }
        widget.refresh_state.refreshing = true;
        if (elem) {
            loading(elem, true);
        }
        setTimeout(function() {
            get_json('/report/widget', {id : widget.report_id}, function (ret) {
                widget.refresh_progress = 0;
                widget.title = ret.title;
                widget.widgets = ret.widgets;
                widget.refresh_state.last_time = +new Date;
                widget.refresh_state.refreshing = false;
                $.each(widget.widgets || [], function (index, sub_widget) {
                    if (sub_widget.chart) {
                        render_chart(widget.cnt_id + '-' + index, sub_widget);
                    }
                });
            }, elem);
        }, (Math.round((Math.random()*100))%3 + 1)*1000);
    };

    $scope.$on('$viewContentLoaded', function() {
        // initialize core components
        Metronic.initAjax();

        refresh_data();

        refresh_timer = setInterval(function () {
            for (var i = 0; i < $scope.rows.length; i++) {
                $.each($scope.rows[i], function(j, widget) {
                    if (widget.type !== 'report') return;
                    if (!widget.refresh_state) {
                        widget.refresh_state = {
                            refreshing : false,
                            last_time : 0,
                            interval : (widget.config.refresh_interval*1) || 300
                        };
                    }
                    var keep_time = (+new Date) - widget.refresh_state.last_time;
                    if (keep_time < widget.refresh_state.interval * 1000) {
                        widget.refresh_progress = Math.min(100, (keep_time / (widget.refresh_state.interval * 1000) * 100));
                        $('#' + widget.cnt_id + ' .refresh-progress').css({
                            width: widget.refresh_progress + '%'
                        });
                        return;
                    }
                    $scope.refreshWidget(widget);
                });
            }
        }, 1000);
    });
    // set sidebar closed and body solid layout mode
    //
    $scope.$on('$destroy', function() {
        Metronic.destroySlimScroll('.scroller');

        if (refresh_timer) {
            window.clearInterval(refresh_timer);
        }
        if (chat_message_timer) {
            window.clearInterval(chat_message_timer);
        }
    });

    $scope.changeWidgetPosition = function(widget, position) {
        var widgets = [], index = 0, target_index = -1;
        $.each($scope.rows, function(i, row) {
            $.each(row, function(j, widget_item) {
                widgets.push(widget_item);
                if (widget_item === widget) {
                    target_index = index;
                }
                index++;
            });
        });

        if (target_index < 0) return;

        widgets.splice(target_index, 1);
        var to_index = -1;
        switch (position) {
            case 'up' :
                to_index = target_index - 1;
                break;

            case 'down' :
                to_index = target_index + 1;
                break;

            case 'top' :
                to_index = 0;
                break;

            case 'bottom' :
                to_index = widgets.length;
                break;
        }

        if (to_index < 0) {
            to_index = 0;
        } else if (to_index > widgets.length) {
            to_index = widgets.length;
        }

        widgets.splice(to_index, 0, widget);

        fillWidgets(widgets);

        var report_ids = [];
        $.each(widgets, function(i, widget) {
            if (widget.report_id) {
                report_ids.push(widget.report_id);
            }
        });
        if (report_ids.length > 0) {
            get_json('/dashboard/sortWidgets', {report_ids: report_ids.join(',')}, function() {

            });
        }
    };

    $scope.removeWidget = function(widget) {
        if (widget.report_id) {
            get_json('/dashboard/removeWidget', {report_id: widget.report_id}, function() {
                refresh_data();
            });
        }
    };

    var render_chart = (function(){
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

        function render_amchart(chart_cnt_id, index, options, widget)
        {
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
                var value_column_index = i;

                graph.valueField = 'data-' + value_column_index;

                graph_value_fields.push([graph.valueField, value_field]);

                options.graphs[i] = graph;
            });

            $.each(invalid_graph_index, function(i, j) {
                options.graphs.splice(j - i, 1);
            });

            var chart_data = widget.chart.data;

            if (options.type == 'serial' && typeof options.dataDateFormat == 'undefined') {
                var time_format = chart_data.category_axis[0]
                    .replace(/\d{4}-\d{2}-\d{2}/, 'YYYY-MM-DD')
                    .replace(/\d{2}:\d{2}:\d{2}/, 'JJ:NN:SS')
                    .replace(/\d{2}:\d{2}/, 'JJ:NN')
                    .replace(/^\d{10}$/, 'JJ');

                $log.debug('CHART[' + chart_cnt_id + '] time_format:' + chart_data.category_axis[0] + '=>' + time_format);

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
                    $log.debug('CHART[' + chart_cnt_id + '] set dataDateFormat auto:' + time_format);
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

            $.each(chart_data.category_axis, function(index) {
                var data_item = {}, c;
                if (options.type == 'serial') {
                    data_item[options.categoryField] = chart_data.category_axis[index];
                }

                if (options.type == 'pie') {//如果是饼图的时候，对数据做

                    var _key = chart_data.values[_keyIndex[1]][index];
                    var _val = '' + chart_data.values[_valIndex[1]][index];
                    var _val = 1 * (_val.replace(/[,%]/g, '') || 0) || 0;

                    if (typeof _dataProvider[_key] == 'undefined') {
                        _dataProvider[_key] = 0;
                    }
                    _dataProvider[_key] += _val;

                } else {
                    for (var i = 0; i < graph_value_fields.length; i++) {
                        var c = graph_value_fields[i];
                        if (!chart_data.values[c[1]]) console.log(c);
                        var s = '' + chart_data.values[c[1]][index];
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

            var chart_id = chart_cnt_id + '-' + index;

            setTimeout(function() {
                if ($('#' + chart_id).length == 0) {
                    $('#' + chart_cnt_id).append('<div class="chart" id="' + chart_id +  '"></div>');
                }
                $log.debug('chart option:', options);
                AmCharts.makeChart(chart_id, options);

                $('#' + chart_id).find('a[href*="amcharts"]').css('opacity', .1);
            }, 0);
        }

        return function (chart_cnt_id, widget) {
            var options_arr = widget.chart.options;

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
                    $log.error("CHART[" + chart_cnt_id + "]配置不是个对象");
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
                render_amchart(chart_cnt_id, i, amchart_option, widget);
            });
        };
    })();

    $scope.dtOptions = function(tid) {
        var $table = $('table[id="' + tid + '"]');

        if ($table.data('dt_options')) {

            return $table.data('dt_options');
        }

        var table_options = $table.data('options') || {};
        var options = table_options.dt ? table_options.dt : {};
        var vw = Math.max(document.documentElement.clientWidth, window.innerWidth || 0);
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
        options = get_dtoption('report', options);
        options.sDom = "t";
        $table.data('dt_options', options);
        return options;
    };
}]);
