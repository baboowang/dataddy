'use strict';

MetronicApp.controller('PluginController', ['$rootScope', '$scope', '$http', '$log', 'Notification', '$stateParams',
    function($rootScope, $scope, $http, $log, Notification, $stateParams) {

    $scope.params = $stateParams;

    $scope.plugins = [];

    $scope.dtOptions = get_dtoption('plugin');


    var refresh_plugins = function() {
        get_json('/plugin/list.json', function(plugins){
            $scope.plugins = plugins;
        });
    };

    $scope.$on('$viewContentLoaded', function() {
        // initialize core components
        Metronic.initAjax();

        refresh_plugins();

    });


    $scope.remove = function(plugin_id) {
        $log.debug('Remove plugin[' + plugin_id + ']');
        $http.post('/plugin/remove', { id : plugin_id }).success(function(ret){
            if (ret && ret.code == 0) {
                Notification.success("删除插件[" + ret.data.name + "]成功！");
                refresh_plugins();
                return;
            }

            Notification.error("删除插件失败：" + (ret ? ret.message : ''));
        });
    };

    $rootScope.paths = [ { url : '#/plugin/list', name : '插件列表' } ];

}]);