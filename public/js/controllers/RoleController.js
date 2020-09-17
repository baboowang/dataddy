'use strict';

MetronicApp.controller('RoleController', ['$rootScope', '$scope', '$http', '$log', 'Notification', function($rootScope, $scope, $http, $log, Notification) {
    var refresh_roles = function() {
        get_json('/role/list.json', function(roles){
            $scope.roles = roles;
        });
    };

    $scope.$on('$viewContentLoaded', function() {
        // initialize core components
        Metronic.initAjax();

        refresh_roles();
    });

    $scope.is_processing = false;
    $scope.changed = false;

    $scope.$on('roles_change', function(event, data) {
        data = angular.toJson(data);
        $log.debug("Roles order change:" + data);
    });

    $scope.remove = function(role_id) {
        $log.debug('Remove role[' + role_id + ']');
        $http.post('/role/remove', { id : role_id }).success(function(ret){
            if (ret && ret.code == 0) {
                Notification.success("删除角色[" + ret.data.name + "]成功！");
                refresh_roles();
                return;
            }

            Notification.error("删除角色失败：" + (ret ? ret.message : ''));
        });
    };

    $scope.create = function(role_id) {
        $log.debug('Create role with parent[' + role_id + ']');
        $scope.is_processing = true;
        $rootScope.$state.go('role.form', { parent_id : role_id });
    };

    $scope.edit = function(role_id) {
        $log.debug('Edit role[' + role_id + ']');
        $scope.is_processing = true;
        $rootScope.$state.go('role.form', { id : role_id });
    };

    $rootScope.paths = [ { url : '#/role/list', name : '角色列表' } ];
//    get_json('/role/list'
    // set sidebar closed and body solid layout mode
}]);


