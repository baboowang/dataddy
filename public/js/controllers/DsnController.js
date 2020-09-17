'use strict';

MetronicApp.controller('DsnController', ['$rootScope', '$scope', '$http', '$log', 'Notification', '$stateParams', '$modal',
    function($rootScope, $scope, $http, $log, Notification, $stateParams, $modal) {

    $scope.params = $stateParams;

    $scope.dsn_list = [];

    var refresh_list = function() {
        get_json('/dsn/list.json', function(dsn_list){
            $scope.dsn_list = dsn_list;
        });
    };

    $scope.dtOptions = get_dtoption('dsn');

    $scope.$on('$viewContentLoaded', function() {
        // initialize core components
        Metronic.initAjax();

        refresh_list();
    });

    $scope.remove = function(dsn_id) {
        $log.debug('Remove dsn[' + dsn_id + ']');
        $http.post('/dsn/remove', { id : dsn_id }).success(function(ret){
            if (ret && ret.code == 0) {
                Notification.success("删除DSN[" + ret.data.name + "]成功！");
                refresh_list();
                return;
            }

            Notification.error("删除DSN失败：" + (ret ? ret.message : ''));
        });
    };

    $scope.create = function(dsn_id) {
        $log.debug('Create dsn with parent[' + dsn_id + ']');
    };

    $scope.edit = function(dsn_id) {
        $log.debug('Edit dsn[' + dsn_id + ']');
        $rootScope.$state.go('dsn.form', { id : dsn_id });
    };

    $rootScope.paths = [ { url : '#/dsn', name : 'DSN列表' } ];

    $scope.open_dsn_form = function (dsnId) {
        var modalInstance = $modal.open({
          templateUrl: 'dsn_form.html',
          controller: 'DsnFormController',
          size: 'lg',
          resolve: {
            dsnId : function () {
              return dsnId;
            }
          }
        });

        modalInstance.result.then(function () {
            refresh_list();
        }, function () {
        });
    };

}]);

MetronicApp.controller('DsnFormController', ['$rootScope', '$scope', '$http', '$log', 'Notification', 'dsnId', '$modalInstance',
    function($rootScope, $scope, $http, $log, Notification, dsnId, $modalInstance) {

    $scope.dsn = {};
    $scope.title = dsnId ? '更新DSN，数据加载中...' : '创建DSN';
    $scope.loading = false;

    if (dsnId) {
        $scope.loading = true;
    }

    get_json('/dsn/detail.json', { id : dsnId }, function(data){
        if (data && data.dsn) {
            $scope.dsn = data.dsn;
            $scope.title = '更新DSN';
            $scope.loading = false;
        } else {
            Notification.error(data ? data.message : '加载DSN数据失败');
        }
    });

    $scope.ok = function () {
        $http.post('/dsn/save', $scope.dsn).success(function(ret){
            if (ret && ret.code == 0) {
                Notification.success($scope.title + "【" + $scope.dsn.name + "】成功！");
                $modalInstance.close('ok');
            } else {
                Notification.error(ret ? ret.message : '系统错误');
            }
        });
    };

    $scope.cancel = function () {
        $modalInstance.dismiss('cancel');
    };
}]);
