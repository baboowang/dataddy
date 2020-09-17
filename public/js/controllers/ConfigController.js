'use strict';

MetronicApp.controller('ConfigController', ['$rootScope', '$scope', '$http', '$log', 'Notification', '$stateParams', '$modal',
    function($rootScope, $scope, $http, $log, Notification, $stateParams, $modal) {

    $scope.params = $stateParams;

    $scope.config_list = [];

    var refresh_list = function() {
        get_json('/config/list.json', function(config_list){
            $scope.config_list = config_list;
        });
    };

    $scope.dtOptions = get_dtoption('config');

    $scope.$on('$viewContentLoaded', function() {
        // initialize core components
        Metronic.initAjax();

        refresh_list();
    });

    $scope.remove = function(config_id) {
        $log.debug('Remove config[' + config_id + ']');
        $http.post('/config/remove', { id : config_id }).success(function(ret){
            if (ret && ret.code == 0) {
                Notification.success("删除config[" + ret.data.name + "]成功！");
                refresh_list();
                return;
            }

            Notification.error("删除config失败：" + (ret ? ret.message : ''));
        });
    };

    $scope.create = function(config_id) {
        $log.debug('Create config with parent[' + config_id + ']');
    };

    $scope.edit = function(config_id) {
        $log.debug('Edit config[' + config_id + ']');
        $rootScope.$state.go('config.form', { id : config_id });
    };

    $rootScope.paths = [ { url : '#/config', name : 'config列表' } ];

    $scope.open_config_form = function (configId) {
        var modalInstance = $modal.open({
          templateUrl: 'config_form.html',
          controller: 'ConfigFormController',
          size: 'lg',
          resolve: {
            configId : function () {
              return configId;
            }
          }
        });

        modalInstance.result.then(function () {
            refresh_list();
        }, function () {
        });
    };

}]);

MetronicApp.controller('ConfigFormController', ['$rootScope', '$scope', '$http', '$log', 'Notification', 'configId', '$modalInstance',
    function($rootScope, $scope, $http, $log, Notification, configId, $modalInstance) {

    $scope.config = {};
    $scope.title = configId ? '更新config，数据加载中...' : '创建config';
    $scope.loading = false;

    if (configId) {
        $scope.loading = true;
    }

    $scope.editorOptions = {
        mode : { name : "javascript", json : true },
        lineNumbers: false,
        matchBrackets: true,
        indentWithTabs: true,
        indentUnit: 4,
        height : 50
    };

    if (configId) {
        get_json('/config/detail.json', { id : configId }, function(data){
            if (data && data.config) {
                $scope.config = data.config;
                $scope.title = '更新config';
                $scope.loading = false;
            } else {
                Notification.error(data ? data.message : '加载config数据失败');
            }
        });
    }

    $scope.ok = function () {
        $http.post('/config/save', $scope.config).success(function(ret){
            if (ret && ret.code == 0) {
                Notification.success($scope.title + "【" + $scope.config.name + "】成功！");
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
