'use strict';

MetronicApp.controller('RoleFormController', [
    '$rootScope', '$scope', '$http', '$log', '$stateParams', 'Notification', function($rootScope, $scope, $http, $log, $stateParams, Notification) {

    var role_id = $stateParams.id;
    var parent_id = $stateParams.parent_id;
    var build_menu_tree = function(data) {
        var tree_loading = function(flag) {
            loading($('#menu-tree'), flag);
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

        var set_w = function(node, flag) {

            if (flag !== undefined) {
                node.state.w = flag;
            }

            var $dom = $($tree.get_node(node, true));
            if (node.state.w) {
                $dom.addClass('writeable');
            } else {
                $dom.removeClass('writeable');
            }
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
            "checkbox" : { "keep_selected_style" : false },
            "types" : types,
            "plugins" : [ "contextmenu", "types", "checkbox" ],
            "contextmenu" : {
                "items" : function(node) {
                    var actions = {};

                    actions['setWriteMode'] = {
                        label : node.state.w ? '设置只读' : '设置可写',
                        action : function() {
                            set_w(node, !node.state.w);
                        },
                        icon : 'fa icon-lg ' + (node.state.w ? 'fa-eye icon-state-warning' : 'fa-edit icon-state-danger')
                    };

                    return actions;
                }
            }
        })
        .on('select_node.jstree', function(e, ed) {
        })
        .on('after_open.jstree', function(e, ed) {
            set_w(ed.node);

            if (!ed.node.state.w && ed.node.children && ed.node.children.length) {
                $.each(ed.node.children, function(i, id) {
                    var nd = $tree.get_node(id);
                    set_w(nd);
                });
            }
        })
        .on('activate_node.jstree', function(e, ed) {
            var node = ed.node;

            if (node.parent == '#') {
                return;
            }

            if (!$tree.is_checked(node)) {
                set_w(node, false);
                return;
            }

            var parent = $tree.get_node(node.parent);

            if (parent.state.w != node.state.w) {
                set_w(node, parent.state.w);
            }
        })
        .on('ready.jstree', function() {
            if ($scope.role && $scope.role.resource) {
                var resource = JSON.parse($scope.role.resource);

                $.each(resource, function(id, mode) {
                    var node = $tree.get_node(id);
                    window.$tree = $tree;

                    if (!node) {
                        $log.error('Node[' + id + '] not exists');
                        return;
                    }

                    if (!/folder/.test(node.type) || /R/.test(mode)) {
                        $tree.check_node(node);
                    }

                    if (/w/.test(mode)) {
                        set_w(node, true);
                    }
                });
            }
        })
        .jstree(true);
    };

    $scope.$on('$viewContentLoaded', function() {
        // initialize core components
        Metronic.initAjax();

        get_json('/role/detail.json', { id : role_id }, function(data){
            $scope.role = data.role;
            if (!$scope.role && parent_id) {
                $scope.role = new Object;
                $scope.role.parent_id = parent_id;
                $scope.role.create_child = true;
            }

            if ($scope.role && $scope.role.parent_id) {
                $scope.role.parent = $.grep(data.roles, function(r) { return r.id == $scope.role.parent_id })[0];
            }
            $scope.roles = data.roles;
            build_menu_tree(data.tree);
        });
    });

    var is_new = !role_id;
    $scope.title = is_new ? '创建新角色' : '编辑角色';
    if (parent_id) {
        $scope.title = '创建子角色';
    }

    $rootScope.paths = [{ url : '#/role/list', name : '角色列表' }, { url : 'javascript:;', name : $scope.title }];

    //$scope.roles = [];

    $scope.editorOptions = {
        mode : { name : "javascript", json : true },
        lineNumbers: true,
        matchBrackets: true,
        indentWithTabs: true,
        indentUnit: 4
    };

    $('#role-form').on('submit', function(event){
        event.preventDefault();

        $log.debug('Save role:' + $scope.role.name);

        $scope.role.parent_id = $scope.role.parent ? $scope.role.parent['id'] : 0;

        var $jstree = $('#menu-tree').jstree(true);

        var nodes = $jstree.get_checked(true);
        var resource = {};
        $.each(nodes, function(i, node) {
            var mode = 'r';
            if (node.state.w) {
                mode += 'w';
            }
            if (/folder/.test(node.type) && !node.state.incomplete) {
                mode += 'R';
            }

            if (node.parent != '#') {
                if (resource[node.parent]) {
                    var pmode = resource[node.parent], default_state = true;

                    if (/R/.test(pmode)) {
                        for (var i = 0; i < mode.length; i++) {
                            if (pmode.indexOf(mode.charAt(i)) == -1) {
                                default_state = false;
                                break;
                            }
                        }
                        if (default_state) {
                            mode += 'T'; //临时状态
                            //return;
                        }
                    }
                } else {
                    var pnode = node;
                    while (pnode && pnode.parent != '#') {
                        if (!resource[pnode.parent]) {
                            resource[pnode.parent] = 'r';
                        } else if (!/r/.test(resource[pnode.parent])) {
                            resource[pnode.parent] += 'r';
                        }
                        pnode = $jstree.get_node(pnode.parent);
                    }
                }
            }
            resource[node.id] = mode;
        });

        $.map(resource, function(v, k) {
            if (/T/.test(v)) {
                delete resource[k];
            }
        });
        $scope.role.resource = JSON.stringify(resource);

        $.post('/role/save?format=json', $scope.role, function(ret){
            if (ret && ret.code == 0) {
                Notification.success($scope.title + "【" + $scope.role.name + "】成功！");
                $rootScope.$state.go('role.list')
            } else {
                Notification.error(ret ? ret.message : '系统错误');
            }
        }, 'json');
    });
}]);
