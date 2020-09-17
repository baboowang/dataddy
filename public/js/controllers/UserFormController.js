'use strict';

MetronicApp.controller('UserFormController', [
    '$rootScope', '$scope', '$http', '$log', '$stateParams', 'Notification', function($rootScope, $scope, $http, $log, $stateParams, Notification) {

    var user_id = $stateParams.id;

    $scope.$on('$viewContentLoaded', function() {
        // initialize core components
        Metronic.initAjax();

        get_json('/user/detail.json', { id : user_id }, function(data){
            if (data.user){
                if (data.user.roles) {
                    var role_ids = data.user.roles.split(/,/);
                    var roles = [];
                    $.each(data.roles, function(i, role){
                        if (role_ids.indexOf(role.id) != -1) {
                            roles.push(role);
                        }
                    });
                    data.user.roles = roles;
                }
            }
            $scope.user = data.user;
            $scope.roles = data.roles;
        });
    });

    var is_new = !user_id;
    $scope.title = is_new ? '创建新用户' : '编辑用户';
    $scope.user = {};
    $scope.roles = [];

    $rootScope.paths = [{ url : '#/user/list', name : '用户列表' }, { url : 'javascript:;', name : $scope.title }];

    $scope.editorOptions = {
        mode : { name : "javascript", json : true },
        lineNumbers: true,
        matchBrackets: true,
        indentWithTabs: true,
        indentUnit: 4
    };

    $('#user-form').on('submit', function(event){
        event.preventDefault();

        $log.debug('Save user:' + $scope.user.username);

        if ($scope.user.roles) {
            $scope.user.role_ids = $.map($scope.user.roles, function(v) { return v.id }).join(',');
        }

        $.post('/user/save', $scope.user, function(ret){
            if (ret && ret.code == 0) {
                Notification.success($scope.title + "【" + $scope.user.username + "】成功！");
                $rootScope.$state.go('user.list')
            } else {
                Notification.error(ret ? ret.message : '系统错误');
            }
        }, 'json');
    });
}]);
