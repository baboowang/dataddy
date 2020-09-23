/***
Metronic AngularJS App Main Script
***/

/* Metronic App */
var MetronicApp = angular.module("MetronicApp", [
    "ui.router",
    "ui.bootstrap",
    "oc.lazyLoad",
    "ngSanitize",
    "mwl.confirm",
    "ui-notification",
    "datatables",
    "datatables.bootstrap",
    "datatables.fixedheader"
]);

/* Configure ocLazyLoader(refer: https://github.com/ocombe/ocLazyLoad) */
MetronicApp.config(['$ocLazyLoadProvider', '$logProvider', 'NotificationProvider', '$httpProvider', function($ocLazyLoadProvider, $logProvider, NotificationProvider, $httpProvider) {
    $ocLazyLoadProvider.config({
        // global configs go here
    });

    $logProvider.debugEnabled(true);

    NotificationProvider.setOptions({
    });

    $httpProvider.defaults.transformRequest = function(obj){
        var str = [];
        for(var p in obj){
            str.push(encodeURIComponent(p) + "=" + encodeURIComponent(obj[p]));
        }
        return str.join("&");
    }

    $httpProvider.defaults.headers.post = {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With' : 'XMLHttpRequest'
    };

    $.extend(true, $.fn.DataTable.TableTools.classes, {
        "container": "btn-group tabletools-dropdown-on-portlet",
        "buttons": {
            "normal": "btn btn-sm default",
            "disabled": "btn btn-sm default disabled"
        },
        "collection": {
            "container": "DTTT_dropdown dropdown-menu tabletools-dropdown-menu"
        }
    });

}]);

MetronicApp.filter('regex', function() {
  return function(input, field, regex) {
      var patt = new RegExp(regex);
      var out = [];
      for (var i = 0; i < input.length; i++){
          if(patt.test(input[i][field]))
              out.push(input[i]);
      }
    return out;
  };
});

MetronicApp.filter('in_set', function() {
    return function(input, field, val) {
        var out = [];
        val = val || '';

        for (var i = 0; i < input.length; i++) {
            if (val == '' || input[i][field].split(/,/).indexOf(val) != -1) {
                out.push(input[i]);
            }
        }

        return out;
  };
});

MetronicApp.filter('fnum', function() {
    return function (val) {
        return fnum(val);
    }
});

MetronicApp.filter('rawHtml', ['$sce', function($sce){
  return function(val) {
    return $sce.trustAsHtml(val);
  };
}]);

MetronicApp.directive('pageBreadcrumb', function() {
    return {
        restrict : 'A',
        replace : true,
        scope : {
            paths : '=pageBreadcrumb'
        },
        template :
        '<div class="page-bar ng-scope">' +
                '<ul class="page-breadcrumb">' +
                '<li>' +
                    '<i class="fa fa-home"></i>' +
                    '<a href="#/dashboard">Home</a>' +
                    '<i class="fa fa-angle-right"></i>' +
                '</li>' +
                '<li ng-repeat="path in paths">' +
                    '<a href="{{path.url}}">{{path.name}}</a>' +
                    '<i ng-if="!$last" class="fa fa-angle-right"></i>' +
                '</li>' +
            '</ul>' +
        '</div>',
        link: function(scope, element, attrs) {
        }
    };
});



MetronicApp.directive('menu', function(){
    return {
        restrict : 'A',
        scope : {
            menu : '='
        },
        replace : true,
        template : '<li ng-repeat="item in menu" menu-item="item" menu-item-index="{{$index}}"></li>',
        link: function(scope, element, attrs) {
        }
    };
});

MetronicApp.directive('menuItem', function($compile) {
    return {
        restrict : 'A',
        replace : true,
        scope : {
            item : '=menuItem',
            index : '@menuItemIndex'
        },
        template : '<li>' +
            '<a href="{{item.url}}" target="{{item.target}}">' +
            '<i class="{{item.icon}}"></i> ' +
            '<span class="title">{{item.name}}</span>' +
            '</a>' +
        '</li>',
        link : function(scope, element, attrs) {

            if (scope.index == 0 && !element.parent().hasClass('sub-menu')) {
                element.addClass('start');
            }

            if (scope.item.submenu) {
                var $submenu = $('<ul class="sub-menu"><li menu="item.submenu"></li></ul>');
                element.find('a').append('<span class="arrow"></span>').after($submenu);

                $compile(element.find('.sub-menu').contents())(scope);
            }

            if (scope.item.type == 'seprator') {
                element.addClass('heading');
                element.empty().append('<h3 class="uppercase">' + scope.item.name + '</h3>');
            }
        }
    };
});

MetronicApp.directive('nestable', function($rootScope, $templateCache){
    return {
        restrict : 'A',
        scope : {
            items : '=nestable',
            remove : '=',
            edit : '=',
            create : '='
        },
        replace : true,
        template : function(tElement, tAttrs) {
            var html = '<ol class="dd-list"><li class="dd-item dd3-item" data-id="{{item.id}}" ng-repeat="item in items" nestable-item="item" type="{type}" remove="remove" create="create" edit="edit"></li></ol>';

            var type;

            if (!tElement.parent().hasClass('dd-item')) {
                var type = tAttrs.type;

                if (!type) {
                    type = 'nestable-' + (Math.random() + '').replace('0.', '');
                }

                html = '<div class="dd" type="' + type + '">' + html + '</div>';

                var item_tpl = $.trim(tElement.html());

                if (item_tpl) {
                    $templateCache.put(type + '-item.html', item_tpl);
                }
            } else {
                var cntElement = tElement;
                while (cntElement.length && !cntElement.hasClass('dd')) {
                    cntElement = cntElement.parent();
                }

                if (cntElement.length) {
                    type = cntElement.attr('type');
                }
            }

            return html.replace('{type}', type);
        },
        link: function(scope, element, attrs) {
            if (element.hasClass('dd')) {
                element.nestable({}).on('change', function(){
                    $rootScope.$broadcast('roles_change', element.nestable('serialize'));
                });
            }
        }
    };
});

MetronicApp.directive('nestableItem', function($compile, $templateCache) {
    return {
        restrict : 'A',
        replace : false,
        scope : {
            item : '=nestableItem',
            remove : '=',
            edit : '=',
            create : '='
        },
        template : function(tElement, tAttrs) {
            var pElement = tElement;

            var type = tAttrs.type;

            var item_tpl = $templateCache.get(type + '-item.html') || '';

            return '<div class="dd-handle dd3-handle"></div><div class="dd3-content">' + item_tpl + '</div>';
        },
        link : function(scope, element, attrs) {

            if (scope.item.children) {
                element.append('<div nestable="item.children" remove="remove" edit="edit" create="create"></div>');
                $compile(element.find('div:last'))(scope);
            }
        }
    };
});

MetronicApp.directive('bootstrapSwitch', [
    function() {
        return {
            restrict: 'A',
            require: '?ngModel',
            link: function(scope, element, attrs, ngModel) {
                element.bootstrapSwitch();

                element.on('switchChange.bootstrapSwitch', function(event, state) {
                    if (ngModel) {
                        scope.$apply(function() {
                            ngModel.$setViewValue(state);
                        });
                    }
                });

                scope.$watch(attrs.ngModel, function(newValue, oldValue) {
                    if (newValue) {
                        element.bootstrapSwitch('state', true, true);
                    } else {
                        element.bootstrapSwitch('state', false, true);
                    }
                });
            }
        };
    }
]);

var SERVER_ERROR = '服务器错误';

function get_json(url, params, success, $loading_elem) {

    if (!success) {
        success = params;
        params = {};
    }

    var $http = angular.element(document.body).injector().get('$http');
    var Notification = angular.element(document.body).injector().get('Notification');

    url += (/\?/.test(url) ? '&' : '?') + $.param(params);
    $http.get(url, params).success(function(ret) {
        if ($loading_elem) {
            loading($loading_elem);
        }
        if (!ret) {
            Notification.error('数据加载失败');
            return;
        }

        if (ret.code != 0) {

            if (ret.code == 1) {
               // window.location = '/login';
                Notification.error('你处于非登录状态，请刷新页面进行登录');
                return;
            }

            Notification.error(ret.message);
            return;
        }

        success(ret.data);
    }).error(function(){
        Notification.error('数据加载失败');
    });
}

function notify(type, message)
{
    var Notification = angular.element(document.body).injector().get('Notification');
    Notification[type](message);
}

function abs(n)
{
    return Math.abs(n);
}

function round(num, n)
{
    n = n || 0;
    return Math.round(num * Math.pow(10, n)) / Math.pow(10, n);
}

function is_float(num)
{
    return /\./.test('' + num);
}

function fnum(num, round)
{
    var num_str;
    if (round) {
        num_str = Math.round(num) + '';
    } else {
        num_str = (Math.round(num * 100) / 100) + '';
    }

    if (num_str === 'NAN') {
        return num;
    }

    return num_str.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function loading(elem, flag)
{
    var cnt = $(elem).closest('.portlet,.page-content');

    if (flag) {
        if (cnt.data('block_t')) {
            clearTimeout(cnt.data('block_t'));
            cnt.data('block_t', null);
        }

        Metronic.blockUI({
            target : cnt,
            animate: true,
            overlayColor: 'none'
        });
        cnt.data('block_time', +new Date);
    } else {
        var delay_time = 500;

        if (cnt.data('time')) {
            var remain_time = (+new Date) - cnt.data('time');

            delay_time -= remain_time;

            if (delay_time < 0) delay_time = 0;
        }

        var t = setTimeout(function() {
            Metronic.unblockUI(cnt);
            cnt.data('block_t', null);
        }, delay_time);

        cnt.data('block_t', t);
    }
}

function get_dtoption(name, option)
{
    function fnClick ( nButton, oConfig, flash ) {
        var text = this.fnGetTableData(oConfig);
        var ext_rows = [];
        $('#' + this.s.dt.sTableId).find('tbody.summary tr').each(function(){
            var ext_row = [];
            $(this).find('td').each(function(){
                ext_row.push(oConfig.sFieldBoundary + $.trim($(this).text()).replace(/"/g, '\\"') + oConfig.sFieldBoundary);
            });
            ext_rows.push(ext_row.join(oConfig.sFieldSeperator));
        });

        if (ext_rows.length) {
            rows = text.split("\n");
            Array.prototype.splice.apply(rows, [1, 0].concat(ext_rows));
            text = rows.join("\n");
        }

        var filename = document.title.match(/^(\S+)/)[0];

        var times = [];

        $('input.date-picker').each(function(){
            times.push($(this).val().replace(/-/g, ''));
        });

        if (times.length) {
            filename += '_' + times.join('~');
        }

        flash.setFileName(filename + '.csv');
        this.fnSetText( flash, text );
    }

    var def = {
        // Internationalisation. For more info refer to http://datatables.net/manual/i18n
        "language": {
            "aria": {
                "sortAscending": ": activate to sort column ascending",
                "sortDescending": ": activate to sort column descending"
            },
            "emptyTable": "没有数据",
            "info": "当前显示 _START_ 到 _END_， 共 _TOTAL_ 条记录",
            "infoEmpty": "未找到记录",
            "infoFiltered": "(filtered1 from _MAX_ total entries)",
            "lengthMenu": "显示 _MENU_ 条",
            "search": "搜索:",
            "zeroRecords": "No matching records found"
        },

        bPaginate : false,

        /*
        "lengthMenu": [
            [20, 50, 100, -1],
            [20, 50, 100, "All"] // change per page values here
        ],

        // set the initial value
        "pageLength": -1,
        */

        "dom": "<'row' <'col-md-12'T>><'row'<'col-md-6 col-sm-12'l><'col-md-6 col-sm-12'f>r><'table-scrollable't><'row'<'col-md-5 col-sm-12'i><'col-md-7 col-sm-12'p>>", // horizobtal scrollable datatable

        // Uncomment below line("dom" parameter) to fix the dropdown overflow issue in the datatable cells. The default datatable layout
        // setup uses scrollable div(table-scrollable) with overflow:auto to enable vertical scroll(see: assets/global/plugins/datatables/plugins/bootstrap/dataTables.bootstrap.js).
        // So when dropdowns used the scrollable div should be removed.
        //"dom": "<'row' <'col-md-12'T>><'row'<'col-md-6 col-sm-12'l><'col-md-6 col-sm-12'f>r>t<'row'<'col-md-5 col-sm-12'i><'col-md-7 col-sm-12'p>>",

        "tableTools": {
            "sSwfPath": "../../../assets/global/plugins/datatables/extensions/TableTools/swf/copy_csv_xls_pdf.swf",
            "aButtons": [{
                "sExtends": "pdf",
                "sButtonText": "PDF"
            }, {
                "sExtends": "csv",
                "sButtonText": "CSV",
                "fnClick" : fnClick
            }, {
                "sExtends": "xls",
                "sButtonText": "Excel",
                "fnClick" : fnClick
            }, {
                "sExtends": "print",
                "sButtonText": "打印",
                "sInfo": '按下 "CTR+P" 打印，按下 "ESC" 退出打印模式',
                "sMessage": "BY DDY.ADEAZ.COM"
            }]
        },

        //hasFixedHeader : true,

        //fixedHeaderOptions : {
        //    offsetTop : $('.navbar').height()
        //},

        fnDrawCallback : function($dt){
            var $table = $('#' + $dt.sTableId);
            $table.closest('div.dataTables_wrapper').find('select').select2();
            $table.find('tbody.items').before($table.find('tbody.summary').remove());
        }
    };

    return $.extend(true, def, option);
}

var template_cb = (function() {
    var cb = {}, i = 0;
    return function template_cb(name, obj)
    {
        if (!obj) {
            if (typeof name == 'function') {
                var cb_name = 'template_cb_' + (i++);
                cb[cb_name] = name;
                return cb_name;
            }
        }

        if (cb[name]) {
            cb[name](obj);
            delete cb[name];
        }
    }
})();
/********************************************
 BEGIN: BREAKING CHANGE in AngularJS v1.3.x:
*********************************************/
/**
`$controller` will no longer look for controllers on `window`.
The old behavior of looking on `window` for controllers was originally intended
for use in examples, demos, and toy apps. We found that allowing global controller
functions encouraged poor practices, so we resolved to disable this behavior by
default.

To migrate, register your controllers with modules rather than exposing them
as globals:

Before:

```javascript
function MyController() {
  // ...
}
```

After:

```javascript
angular.module('myApp', []).controller('MyController', [function() {
  // ...
}]);

Although it's not recommended, you can re-enable the old behavior like this:

```javascript
angular.module('myModule').config(['$controllerProvider', function($controllerProvider) {
  // this option might be handy for migrating old apps, but please don't use it
  // in new ones!
  $controllerProvider.allowGlobals();
}]);
**/

//AngularJS v1.3.x workaround for old style controller declarition in HTML
MetronicApp.config(['$controllerProvider', function($controllerProvider) {
  // this option might be handy for migrating old apps, but please don't use it
  // in new ones!
  $controllerProvider.allowGlobals();
}]);

/********************************************
 END: BREAKING CHANGE in AngularJS v1.3.x:
*********************************************/

/* Setup global settings */
MetronicApp.factory('settings', ['$rootScope', function($rootScope) {
    // supported languages
    var settings = {
        layout: {
            pageSidebarClosed: false, // sidebar menu state
            pageBodySolid: false, // solid body color state
            pageAutoScrollOnLoad: 1000 // auto scroll to top on page load
        },
        layoutImgPath: Metronic.getAssetsPath() + 'admin/layout/img/',
        layoutCssPath: Metronic.getAssetsPath() + 'admin/layout/css/'
    };

    $rootScope.settings = settings;

    return settings;
}]);

/* Setup App Main Controller */
MetronicApp.controller('AppController', ['$scope', '$rootScope', function($scope, $rootScope) {
    $scope.$on('$viewContentLoaded', function() {
        Metronic.initComponents(); // init core components
        //Layout.init(); //  Init entire layout(header, footer, sidebar, etc) on page load if the partials included in server side instead of loading with ng-include directive
    });
}]);

/***
Layout Partials.
By default the partials are loaded through AngularJS ng-include directive. In case they loaded in server side(e.g: PHP include function) then below partial
initialization can be disabled and Layout.init() should be called on page load complete as explained above.
***/

/* Setup Layout Part - Header */
MetronicApp.controller('HeaderController', ['$scope', function($scope) {
    $scope.$on('$includeContentLoaded', function() {
        Layout.initHeader(); // init header
    });
}]);

/* Setup Layout Part - Sidebar */
MetronicApp.controller('SidebarController', ['$scope', '$http', function($scope) {
    $scope.$on('$includeContentLoaded', function() {
        Layout.initSidebar(); // init sidebar
        get_json('/dataddy/menu.json', function(menu){
            $scope.menu = menu;

            var dataSources = [];
            var i, j, data, chunkSize = 10000, tmp1 = [menu], tmp2 = [];

            while (tmp1.length) {
                var list = tmp1.shift();
                for (var i = 0; i < list.length; i++) {
                    if (list[i].submenu) {
                        tmp1.push(list[i].submenu);
                    } else {
                        tmp2.push(list[i]);
                    }
                }
            }
            dataSources.push(getDataSources(tmp2));
            initializeTypeahead(dataSources);
        });
    });

    function getDataSources(data) {
        var source = new Bloodhound({
            local: data,
            limit: 100,
            datumTokenizer: function (datum) {
                var test = Bloodhound.tokenizers.whitespace(datum.id);
                test = test.concat(Bloodhound.tokenizers.whitespace(datum.name))
                $.each(test, function (k, v) {
                    i = 0;
                    while ((i + 1) < v.length) {
                        test.push(v.substr(i, v.length));
                        i++;
                    }
                })
                return test;

            },
            queryTokenizer: function (str) {
                str = str.replace(/^.+,([^,]*)$/, '$1');
                return str ? str.split(/\s+/) : [];
            }
        });

        source.initialize();

        var src = {
            displayKey: 'name',
            source: source.ttAdapter(),
            templates: {
                suggestion: Handlebars.compile([
                    '<p><a href="#/report/{{id}}" style="display:block;color:black;font-size:11px;text-decoration:none"><span class="label label-sm label-default">#{{id}}</span> {{name}}</a></p>',
                ].join(''))
            }
        };

        return src;
    }

    function initializeTypeahead(dataSources)
    {
        var input = $('#menu-search').typeahead(null, dataSources);

        input.data('ttTypeahead').input.setInputValue = function (value, silent) {
            var last_value = this.$input.val();

            if (last_value != value) {
                last_value = last_value.replace(/[^,]*$/, '');
                if (last_value.split(/,/).indexOf(value) == -1) {
                    this.$input.val(last_value + value + ',');
                }
            }
            silent ? this.clearHint() : this._checkInputValue();
        };
    }


}]);

/* Setup Layout Part - Quick Sidebar */
MetronicApp.controller('QuickSidebarController', ['$scope', function($scope) {
    $scope.$on('$includeContentLoaded', function() {
        setTimeout(function(){
            QuickSidebar.init(); // init quick sidebar
        }, 2000)
    });
}]);

/* Setup Layout Part - Theme Panel */
MetronicApp.controller('ThemePanelController', ['$rootScope', '$scope', function($rootScope, $scope) {
    $scope.$on('$includeContentLoaded', function() {
        Demo.init(); // init theme panel

        if ($rootScope.session && $rootScope.session.config && $rootScope.session.config.theme) {
            Demo.load_theme($rootScope.session.config.theme);
        }
    });
}]);

/* Setup Layout Part - Footer */
MetronicApp.controller('FooterController', ['$scope', function($scope) {
    $scope.$on('$includeContentLoaded', function() {
        Layout.initFooter(); // init footer
    });
}]);

/* Setup Rounting For All Pages */
MetronicApp.config(['$stateProvider', '$urlRouterProvider', function($stateProvider, $urlRouterProvider) {
    // Redirect any unmatched url
    $urlRouterProvider.otherwise("/dashboard");

    $stateProvider

        // Dashboard
        .state('dashboard', {
            url: "/dashboard",
            templateUrl: "/views/dashboard.html?v=" + APP_VERSION,
            data: {pageTitle: 'Dashboard'},
            controller: "DashboardController",
            resolve: {
                deps: ['$ocLazyLoad', function($ocLazyLoad) {
                    return $ocLazyLoad.load({
                        name: 'MetronicApp',
                        insertBefore: '#ng_load_plugins_before',
                        files: [
                             '/js/controllers/DashboardController.js?v=' + APP_VERSION
                        ]
                    });
                }]
            }
        })

        .state('role', {
            url : "/role",
            templateUrl : "/views/role.html?v=" + APP_VERSION,
            data : { pageTitle : '角色管理' }
        })

        .state('role.list', {
            url: "/list",
            templateUrl: "/views/role_list.html?v=" + APP_VERSION,
            data: {pageTitle: '角色管理'},
            controller: "RoleController",
            resolve: {
                deps: ['$ocLazyLoad', function($ocLazyLoad) {
                    return $ocLazyLoad.load({
                        name: 'MetronicApp',
                        insertBefore: '#ng_load_plugins_before',
                        files: [
                            '/assets/global/plugins/jquery-nestable/jquery.nestable.css',
                            '/assets/global/plugins/jquery-nestable/jquery.nestable.js',
                            '/js/controllers/RoleController.js?v=' + APP_VERSION
                        ]
                    });
                }]
            }
        })

        .state('role.form', {
            url: "/form?id&parent_id",
            templateUrl: "/views/role_form.html?v=" + APP_VERSION,
            data: {pageTitle: '角色管理'},
            controller: "RoleFormController",
            resolve: {
                deps: ['$ocLazyLoad', function($ocLazyLoad) {
                    return $ocLazyLoad.load([{
                        name: 'ui.select',
                        insertBefore: '#ng_load_plugins_before', // load the above css files before '#ng_load_plugins_before'
                        files: [
                            '/assets/global/plugins/angularjs/plugins/ui-select/select.min.css',
                            '/assets/global/plugins/angularjs/plugins/ui-select/select.min.js'
                        ]
                    }, {
                        name: 'MetronicApp',
                        files: [
                            '/assets/global/plugins/jstree/dist/themes/default/style.min.css',
                            '/assets/global/plugins/jstree/dist/jstree.min.js',
                            '/js/controllers/RoleFormController.js?v=' + APP_VERSION
                        ]
                    }, {
                        name: 'ui.codemirror',
                        insertBefore: '#ng_load_plugins_before', // load the above css files before '#ng_load_plugins_before'
                        files : [
                            '/codemirror/ui-codemirror.js',
                        ]
                    }]);
                }]
            }
        })

        .state('user', {
            url: "/user",
            templateUrl: "/views/user.html?v=" + APP_VERSION,
            data: {pageTitle: '用户管理'}
        })

        .state('user.list', {
            url: "/list?role",
            templateUrl: "/views/user_list.html?v=" + APP_VERSION,
            data: {pageTitle: '用户列表'},
            controller: "UserController",
            resolve: {
                deps: ['$ocLazyLoad', function($ocLazyLoad) {
                    return $ocLazyLoad.load({
                        name: 'MetronicApp',
                        files: [

                            '/js/controllers/UserController.js?v=' + APP_VERSION
                        ]
                    });
                }]
            }
        })

        .state('user.form', {
            url: "/form?id",
            templateUrl: "/views/user_form.html?v=" + APP_VERSION,
            data: {pageTitle: '编辑用户'},
            controller: "UserFormController",
            resolve: {
                deps: ['$ocLazyLoad', function($ocLazyLoad) {
                    return $ocLazyLoad.load([{
                        name: 'ui.select',
                        insertBefore: '#ng_load_plugins_before', // load the above css files before '#ng_load_plugins_before'
                        files: [
                            '/assets/global/plugins/angularjs/plugins/ui-select/select.min.css',
                            '/assets/global/plugins/angularjs/plugins/ui-select/select.min.js'
                        ]
                    }, {
                        name: 'MetronicApp',
                        files: [

                            '/js/controllers/UserFormController.js?v=' + APP_VERSION
                        ]
                    }, {
                        name: 'ui.codemirror',
                        insertBefore: '#ng_load_plugins_before', // load the above css files before '#ng_load_plugins_before'
                        files : [
                            '/codemirror/ui-codemirror.js',
                        ]
                    }]);
                }]
            }
        })

        .state('dsn', {
            url: '/dsn',
            templateUrl : '/views/dsn.html?v=' + APP_VERSION,
            controller: "DsnController",
            data: { pageTitle: 'DSN管理' },
            resolve: {
                deps: ['$ocLazyLoad', function($ocLazyLoad) {
                    return $ocLazyLoad.load({
                        name: 'MetronicApp',
                        files: [
                            '/js/controllers/DsnController.js?v=' + APP_VERSION
                        ]
                    });
                }]
            }
        })

        .state('config', {
            url: '/config',
            templateUrl : '/views/config.html?v=' + APP_VERSION,
            controller: "ConfigController",
            data: { pageTitle: '配置管理' },
            resolve: {
                deps: ['$ocLazyLoad', function($ocLazyLoad) {
                    return $ocLazyLoad.load([{
                        name: 'MetronicApp',
                        files: [
                            '/js/controllers/ConfigController.js?v=' + APP_VERSION
                        ]
                    }, {
                        name: 'ui.codemirror',
                        insertBefore: '#ng_load_plugins_before', // load the above css files before '#ng_load_plugins_before'
                        files : [
                            '/codemirror/ui-codemirror.js',
                        ]
                    }]);
                }]
            }
        })

        .state('menu', {
            url: "/menu?id",
            templateUrl: "/views/menu.html?v=" + APP_VERSION,
            data: {pageTitle: '菜单管理'},
            controller: "MenuController",
            resolve: {
                deps: ['$ocLazyLoad', function($ocLazyLoad) {
                    return $ocLazyLoad.load([{
                        name: 'MetronicApp',
                        files: [
                            '/assets/global/plugins/jstree/dist/themes/default/style.min.css',
                            '/assets/global/plugins/jstree/dist/jstree.min.js',
                            '/js/controllers/MenuFormController.js?v=' + APP_VERSION,
                            '/js/controllers/MenuController.js?v=' + APP_VERSION,
                            '/js/controllers/DataVersionController.js?v=' + APP_VERSION
                        ]
                    }, {
                        name: 'ui.codemirror',
                        insertBefore: '#ng_load_plugins_before', // load the above css files before '#ng_load_plugins_before'
                        files : [
                            '/codemirror/ui-codemirror.js',
                        ]
                    }]);
                }]
            }
        })

        .state('cron', {
            url: "/cron",
            templateUrl: "/views/cron.html?v=" + APP_VERSION,
            data: {pageTitle: 'Cron管理'},
            controller: "CronController",
            resolve: {
                deps: ['$ocLazyLoad', function($ocLazyLoad) {
                    return $ocLazyLoad.load({
                        name: 'MetronicApp',
                        files: [
                            '/assets/global/plugins/jquery-nestable/jquery.nestable.css',
                            '/assets/global/plugins/jquery-nestable/jquery.nestable.js',
                            '/js/controllers/CronController.js?v=' + APP_VERSION
                        ]
                    });
                }]
            }
        })

        .state('plugin', {
            url : "/plugin?id",
            templateUrl : "/views/plugin.html?v=" + APP_VERSION,
            data : { pageTitle : '插件管理' },
            resolve: {
                deps: ['$ocLazyLoad', function($ocLazyLoad) {
                    return $ocLazyLoad.load({
                        name: 'MetronicApp',
                        files: [
                            '/js/controllers/DataVersionController.js?v=' + APP_VERSION
                        ]
                    });
                }]
            }
        })

        .state('plugin.list', {
            url: "/list",
            templateUrl: "/views/plugin_list.html?v=" + APP_VERSION,
            data: {pageTitle: '插件管理'},
            controller: "PluginController",
            resolve: {
                deps: ['$ocLazyLoad', function($ocLazyLoad) {
                    return $ocLazyLoad.load({
                        name: 'MetronicApp',
                        files: [
                            '/js/controllers/PluginController.js?v=' + APP_VERSION
                        ]
                    });
                }]
            }
        })

        .state('plugin.form', {
            url: "/form?clazz",
            templateUrl: "/views/plugin_form.html?v=" + APP_VERSION,
            data: {pageTitle: '编辑插件'},
            controller: "PluginFormController",
            resolve: {
                deps: ['$ocLazyLoad', function($ocLazyLoad) {
                    return $ocLazyLoad.load([{
                        name: 'MetronicApp',
                        files: [
                            '/assets/global/plugins/angularjs/plugins/ui-select/select.min.css',
                            '/assets/global/plugins/angularjs/plugins/ui-select/select.min.js',
                            '/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css',
                            '/assets/global/plugins/bootstrap-datetimepicker/css/bootstrap-datetimepicker.min.css',
                            "/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js",
                            "/assets/global/plugins/bootstrap-datetimepicker/js/bootstrap-datetimepicker.min.js",
                            '/js/controllers/PluginFormController.js?v=' + APP_VERSION
                        ]
                    }, {
                        name: 'ui.codemirror',
                        insertBefore: '#ng_load_plugins_before', // load the above css files before '#ng_load_plugins_before'
                        files : [
                            '/codemirror/ui-codemirror.js',
                        ]
                    }]);
                }]
            }
        })

        .state('report', {
            url: "/report/:id?query",
            templateUrl: "/views/report.html?v=" + APP_VERSION,
            data: {pageTitle: '报表'},
            controller: "ReportController",
            resolve: {
                deps: ['$ocLazyLoad', function($ocLazyLoad) {
                    return $ocLazyLoad.load([{
                        name: 'MetronicApp',
                        files: [
                            '/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css',
                            '/assets/global/plugins/bootstrap-datetimepicker/css/bootstrap-datetimepicker.min.css',

                            "/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js",
                            "/assets/global/plugins/bootstrap-datetimepicker/js/bootstrap-datetimepicker.min.js",
                            '/js/controllers/MenuFormController.js?v=' + APP_VERSION,
                            '/js/controllers/ReportController.js?v=' + APP_VERSION,
                            '/js/controllers/DataVersionController.js?v=' + APP_VERSION
                        ]
                    },{
                        name: 'ui.codemirror',
                        insertBefore: '#ng_load_plugins_before', // load the above css files before '#ng_load_plugins_before'
                        files : [
                            '/codemirror/ui-codemirror.js',
                        ]
                    }]);
                }]
            }
        });
}]);

MetronicApp.filter('propsFilter', function() {
    return function(items, props) {
        var out = [];

        if (angular.isArray(items)) {
            items.forEach(function(item) {
                var itemMatches = false;

                var keys = Object.keys(props);
                for (var i = 0; i < keys.length; i++) {
                    var prop = keys[i];
                    var text = props[prop].toLowerCase();
                    if (item[prop].toString().toLowerCase().indexOf(text) !== -1) {
                        itemMatches = true;
                        break;
                    }
                }

                if (itemMatches) {
                    out.push(item);
                }
            });
        } else {
            // Let the output be the input untouched
            out = items;
        }

        return out;
    };
});


/* Init global settings and run the app */
MetronicApp.run(["$rootScope", "settings", "$state", "$http", "$location", "Notification", function($rootScope, settings, $state, $http, $location, Notification) {
    $rootScope.$state = $state; // state to be accessed from view
    $rootScope.paths = [];

    $http.get('/dataddy/session.json').success(function(ret) {
        if (typeof ret != 'object' || typeof ret.code == 'undefined') {
            Notification.error("获取登录信息失败");
            return;
        }

        if (ret.code == 0) {
            $rootScope.session = ret.data;

            if ($rootScope.session && $rootScope.session.config && $rootScope.session.config.theme) {
                Demo.load_theme($rootScope.session.config.theme);
            }

        } else if (ret.code == 1) {
            $(document.body).hide().after('<span>未登录，跳转中...</span>');
            location.href = '/login?redirect_uri=' + encodeURIComponent($location.absUrl());
        } else {
            Notification.error(ret.message);
        }
    });

    $.ajaxSetup({'cache':true});
}]);

!function(){
    var focus = true;

    $(window).blur(function(){ focus = false; }).focus(function(){ focus = true; });

    window.AutoRefresh = function(){
        var handler = null;

        window.setInterval(function(){
            if (handler != null) {
                handler(window.__debug_focus || focus);
            }
        }, 1000);

        return function(h) {
            handler = h;
        };
    }();
}();

// select2 language zh-CN
$(function(){
(function(){if(jQuery&&jQuery.fn&&jQuery.fn.select2&&jQuery.fn.select2.amd)var e=jQuery.fn.select2.amd;return e.define("select2/i18n/zh-CN",[],function(){return{errorLoading:function(){return"无法载入结果。"},inputTooLong:function(e){var t=e.input.length-e.maximum,n="请删除"+t+"个字符";return n},inputTooShort:function(e){var t=e.minimum-e.input.length,n="请再输入至少"+t+"个字符";return n},loadingMore:function(){return"载入更多结果…"},maximumSelected:function(e){var t="最多只能选择"+e.maximum+"个项目";return t},noResults:function(){return"未找到结果"},searching:function(){return"搜索中…"}}}),{define:e.define,require:e.require}})();
});

$(document.body).delegate('a.download-csv', 'click', function(event){
    event.preventDefault();

    var $tables = $(this).closest('.portlet').find('table.table');

    if ($('#download-form').length == 0) {
        $('<form id="download-form" action="/dataddy/download" method="post" style="display:none"><textarea name="data"></textarea></form>')
        .appendTo(document.body);
    }

    var html = $tables.map(function() { return $(this).html() }).get().join('');

    $('#download-form').find('textarea[name=data]').val(html).end().trigger('submit');
});

var debounce = function (fn, delay) {
    let timer = null;
    return function () {
        clearTimeout(timer);
        timer = setTimeout(() => { fn.apply(this, arguments); }, delay);
    };
};
