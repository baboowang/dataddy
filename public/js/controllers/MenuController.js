'use strict';


MetronicApp.controller('MenuController', ['$rootScope', '$scope', '$http', '$log', 'Notification', '$stateParams',
    function($rootScope, $scope, $http, $log, Notification, $stateParams) {

    $scope.params = $stateParams;

    var refresh_menus = function() {
        get_json('/menu/list.json', function(menus){
            $log.debug(menus);
            $scope.menus = menus.tree;
            $scope.version = menus.version;
            build_menu_tree(menus.tree, $http, $log, Notification, $rootScope, $scope);
        });
    };

    $scope.$on('$viewContentLoaded', function() {
        // initialize core components
        Metronic.initAjax();
        refresh_menus();
    });

     $rootScope.paths = [ { url : '#/menu', name : '菜单管理' } ];
}]);

function build_menu_tree(data, $http, $log, Notification, $rootScope, $scope) {
    var tree_loading = function(flag) {
        loading($('#menu-tree'), flag);
    };
    var is_server_item = function(node) {
        var id = typeof node == 'string' || typeof node == 'number' ? node : node.id;
        return /^\d+$/.test(id || '');
    };

    var types = {
        "default" : {
            "icon" : "fa fa-folder icon-state-warning icon-lg"
        },
        "folder" : {
            "icon" : "fa fa-folder icon-state-warning icon-lg"
        },
        "report" : {
            "icon" : "fa fa-file icon-state-success icon-lg"
        },
        "alarm" : {
            "icon" : "fa fa-shield icon-state-success icon-lg"
        },
        "link" : {
            "icon" : "fa fa-link icon-state-default icon-lg"
        }
    };

    $.map($.map(types, function(v, k) { return k }), function(type) {
        types['secret-' + type] = {'icon' : 'fa fa-eye-slash icon-state-default icon-lg'};
    });

    var $tree;
    var last_select_id = 0;
    var refresh_form_timer = null;

    var create_node = function(parent_node, type) {
        var node = {
            text : '未命名',
            type : type,
            state : { need_sync : true }
        };

        $tree.create_node(parent_node, node, 'last', function(node){
            $tree.edit(node);
        });
    };

    $tree = $('#menu-tree').jstree({
        "core" : {
            "themes" : {
                "responsive": false
            },
            // so that create works
            "check_callback" : true,
            'data': data
        },
        "types" : types,
        "state" : { "key" : "menutree" },
        "plugins" : [ "contextmenu", "dnd", "state", "types", "search" ],
        "search": {
            "case_sensitive": false,
            "show_only_matches": true,
            'search_callback': function(searchValue, node){
                var match = node.id === (searchValue+'') || node.text.indexOf(searchValue) !== -1;
                return match;
            }
        },
        "contextmenu" : {
            "items" : function($node) {
                var actions = {};

                if (($node.children && $node.children.length > 0) || ($node.type == 'folder' || $node.type == 'secret' || $node.type == 'default')) {

                    actions['createReport'] = {
                        label : '新建报表',
                        action : function() {
                            create_node($node, 'report');
                        },
                        icon : 'fa fa-file icon-state-warning icon-lg'
                    };

                    actions['createLink'] = {
                        label : '新建链接',
                        action : function() {
                            create_node($node, 'link');
                        },
                        icon : 'fa fa-link icon-state-default icon-lg'
                    };

                    actions['createAlarm'] = {
                        label : '新建监控作业',
                        action : function() {
                            create_node($node, 'alarm');
                        },
                        icon : 'fa fa-shield icon-state-success icon-lg'
                    };

                    actions['createFolder'] = {
                        label : '新建普通目录',
                        separator_before : true,
                        action : function() {
                            create_node($node, 'folder');
                        },
                        icon : 'fa fa-folder icon-state-warning icon-lg'
                    };

                    actions['createSecret'] = {
                        label : '新建隐藏目录',
                        action : function() {},
                        icon : 'fa fa-eye-slash icon-state-danger icon-lg'
                    };


                } else if ($node.type == 'report' || $node.type == 'alarm' || $node.type == 'link') {
                    actions['browser'] = {
                        label : '浏览',
                        action : function () {
                            window.open('/#/report/' + $node.id);
                        },
                        icon : 'fa fa-external-link icon-state-success icon-lg'
                    };
                }

                if (!$node.children || $node.children.length == 0) {
                    actions['delete'] = {
                        label : '删除节点',
                        separator_before : true,
                        action : function() {
                            if (!is_server_item($node)) {
                                $tree.delete_node($node);
                                return;
                            }

                            if (!window.confirm('你确定要删除节点：' + $node.text + '？')) {
                                return;
                            }

                            tree_loading(true);

                            $http.post('/menu/remove', { id : $node.id }).success(function(ret){
                                tree_loading(false);
                                if (ret && ret.code == 0) {
                                    $tree.delete_node($node);
                                } else {
                                    Notification.error(ret.message || SERVER_ERROR);
                                }
                            });
                        },
                        icon : 'fa fa-times icon-state-danger icon-lg'
                    };
                }

                actions['rename'] = {
                    label : '重命名',
                    separator_before : true,
                    action : function() {
                        $tree.edit($node);
                    },
                    icon : 'fa fa-edit icon-state-warning icon-lg'
                };

                return actions;
            }
        }
    })
    .on('select_node.jstree', function(e, ed) {
        var node = ed.node;

        console.log('select ' + node.id);
        //if (/folder/.test(node.type) || last_select_id == node.id) {
        if (last_select_id == node.id) {

            return;
        }

        if (refresh_form_timer) {
            clearTimeout(refresh_form_timer);
            refresh_form_timer = null;
        }

        last_select_id = node.id;
        refresh_form_timer = setTimeout(function(){
            refresh_form_timer = null;
            var menu_id = is_server_item(node) ? node.id : '';

            var menu = {
                id : menu_id
            };

            if (!menu.id) {
                var type = node.type;
                var visiable = true;
                if (/^secret/.test(type)) {
                    type = type.replace(/^secret-/, '');
                    visiable = false;
                }

                menu.type = type;
                menu.visiable = visiable;
                menu.parent_id = is_server_item(node.parent) ? node.parent : 0;
            }

            $rootScope.$broadcast('menu_form_request', menu);
        }, 300);
    })
    .on('move_node.jstree', function(e ,obj) {
        $log.debug("move node");
            $log.debug(obj.node);

        var tree_nested = $('#menu-tree').jstree(true).get_json(obj.parent, {no_state: true, no_data: true, flat: true});
        var tree_data = JSON.stringify(tree_nested);
        tree_loading(true);

        $http.post('/menu/updateTree', {
            tree: tree_data,
            id : obj.node.id,
            parent: obj.parent,
            position : obj.position,
            version : $scope.version

        }).success(function(ret){
            tree_loading(false);
            if (ret && ret.code == 0) {
                Notification.success('更新目录树成功');
                $scope.version = ret.data.version;
                return;
            }

            Notification.error('更新目录树失败:' + (ret.message || SERVER_ERROR));

        });
    })
    .on('rename_node.jstree', function(e, ed) {
        var node = ed.node;

            //TODO 字段暂时设空
        var node_data = {
            name : node.text,
            type : node.type,
            desc : '',
            visiable : 1
        };

        if (/^secret-/.test(node_data.type)) {
            node_data.type = node_data.type.replace(/^secret-/, '');
            node_data.visiable = 0;
        }

        if (node.parent != '#') {
            if (!is_server_item(node.parent)) {
                $log.debug('不合法的父级节点:' + node.parent);
                return;
            }
            node_data.parent_id = node.parent;
        }

        if (is_server_item(node)) {
            node_data['id'] = node.id;
        }

        tree_loading(true);

        $http.post('/menu/save?format=json', node_data).success(function(ret){

            tree_loading(false);

            if (ret && ret.code == 0) {
                $tree.set_id(node, ret.data.id);
                node.state.need_sync = false;
                Notification.success('创建新节点成功，' + node.text + '[' + ret.data.id + ']');
                $tree.select_node(node);
                return;
            }

            Notification.error('添加新节点失败:' + (ret.message || SERVER_ERROR));

            $tree.delete_node(node);
        });
    })
    .on('ready.jstree', function() {
        if ($scope.params.id) {
            $tree.deselect_all();
            $tree.select_node({id : $scope.params.id});
        }
    })
    .jstree(true);

    var to = false;
    $('#tree_search_input').keyup(function () {
        if(to) { clearTimeout(to); }
        to = setTimeout(function () {
            var v = $('#tree_search_input').val().trim();
            $tree.search(v);
        }, 250);
    });

    $('#add-big-node-btn').click(function(){
        var node = {
            text : '未命名',
            type : 'folder',
            state : { need_sync : true }
        };

        $tree.create_node('#', node, 'last', function(node){
            $tree.edit(node);
        });
    });

    var dh = $rootScope.$on('menu_update', function(e, menu) {
        var node = $tree.get_node(menu.id);

        if (node) {
            var name = menu.name.split(/\//).pop();
            if (node.text != name) {
                $tree.set_text(node, name);
            }
            var type = (menu.visiable ? '' : 'secret-') + menu.type;

            if (node.type != type) {
                $tree.set_type(node, type);
            }
        }
    });

    $scope.$on('$destroy', dh);
}
