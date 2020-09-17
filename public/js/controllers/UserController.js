'use strict';

MetronicApp.controller('UserController', ['$rootScope', '$scope', '$http', '$log', 'Notification', '$stateParams',
    function($rootScope, $scope, $http, $log, Notification, $stateParams) {

    $scope.params = $stateParams;

    $scope.users = [];

    var initUserTable = function () {
        var tableWrapper = $('#user-table_wrapper'); // datatable creates the table wrapper by adding with id {your_table_jd}_wrapper

        tableWrapper.find('.dataTables_length select').select2(); // initialize select2 dropdown
    };

    var refresh_users = function() {
        get_json('/user/list.json', function(users){
            $scope.users = users;
            initUserTable();
        });
    };

    $scope.dtOptions = get_dtoption('user');

    $scope.$on('$viewContentLoaded', function() {
        // initialize core components
        Metronic.initAjax();

        refresh_users();
    });

    $scope.remove = function(user_id) {
        $log.debug('Remove user[' + user_id + ']');
        $http.post('/user/remove', { id : user_id }).success(function(ret){
            if (ret && ret.code == 0) {
                Notification.success("删除用户[" + ret.data.name + "]成功！");
                refresh_users();
                return;
            }

            Notification.error("删除用户失败：" + (ret ? ret.message : ''));
        });
    };

    $scope.create = function(user_id) {
        $log.debug('Create user with parent[' + user_id + ']');
    };

    $scope.edit = function(user_id) {
        $log.debug('Edit user[' + user_id + ']');
        $rootScope.$state.go('user.form', { id : user_id });
    };

    $rootScope.paths = [ { url : '#/user/list', name : '用户列表' } ];

}]);


