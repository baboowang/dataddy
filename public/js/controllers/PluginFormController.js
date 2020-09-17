'use strict';

MetronicApp.controller('PluginFormController', [
    '$rootScope', '$scope', '$http', '$log', '$stateParams', 'Notification', function($rootScope, $scope, $http, $log, $stateParams, Notification) {

    var clazz = $stateParams.clazz;
    $scope.plugin = {};
    $scope.$on('$viewContentLoaded', function() {
        // initialize core components
        Metronic.initAjax();

        get_json('/plugin/detail.json', { clazz : clazz }, function(data){
            $scope.plugin = data;
        });
    });

    $scope.title = '编辑插件';

    $rootScope.paths = [{ url : '#/plugin/list', name : '插件列表' }, { url : 'javascript:;', name : $scope.title }];

    $scope.editorOptions = {
        mode : { name : "javascript", json : true },
        lineNumbers: true,
        matchBrackets: true,
        indentWithTabs: true,
        indentUnit: 4
    };

    $('#plugin-form').on('submit', function(event){
        event.preventDefault();

        $log.debug('Save plugin:' + $scope.plugin.name);

        $.post('/plugin/save', $scope.plugin, function(ret){
            if (ret && ret.code == 0) {
                console.log($scope.plugin);
                Notification.success($scope.title + "【" + $scope.plugin.className + "】成功！");
                $rootScope.$state.go('plugin.list')
            } else {
                Notification.error(ret ? ret.message : '系统错误');
            }
        }, 'json');
    });
}]);
