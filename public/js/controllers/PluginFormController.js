'use strict';

MetronicApp.controller('PluginFormController', [
    '$rootScope', '$scope', '$http', '$log', '$stateParams', 'Notification', '$timeout', function($rootScope, $scope, $http, $log, $stateParams, Notification, $timeout) {

    var id = $stateParams.id;
    $scope.plugin = {};
    $scope.$on('$viewContentLoaded', function() {
        // initialize core components
        Metronic.initAjax();

        get_json('/plugin/detail.json', { id : id }, function(data){
            $scope.plugin = data.plugin || {};
            if ($scope.plugin.name) {
                $scope.title = '编辑插件[' + $scope.plugin.name + ']';
            }
            $scope.refresh = true;
        });
    });

    if (id) {
      //temp fix: waiting for data_version module loaded
      $timeout(function() {
        $rootScope.$broadcast('register_data_version', {
            name : 'plugin',
            pk : id,
            onSelect : function(version_info, version_data) {
                $scope.plugin = angular.fromJson(version_data);
                $scope.refresh = true;
            }
        });
      }, 3000);
    }

    $scope.title = id ? '编辑插件' : '创建插件';

    $rootScope.paths = [{ url : '#/plugin/list', name : '插件列表' }, { url : 'javascript:;', name : $scope.title }];

    $scope.editorOptions = {
        mode : "php",
        lineNumbers: true,
        matchBrackets: true,
        indentWithTabs: true,
        indentUnit: 4
    };

    if ($rootScope.session.config.editor.vim_mode) {
        $scope.editorOptions.keyMap = "vim";
    }

    $('#plugin-form').on('submit', function(event){
        event.preventDefault();

        $log.debug('Save plugin:' + $scope.plugin.name);

        $.post('/plugin/save', $scope.plugin, function(ret){
            if (ret && ret.code == 0) {
                Notification.success($scope.title + "成功！");
              //$rootScope.$state.go('plugin.list')
            } else {
                Notification.error(ret ? ret.message : '系统错误');
            }
        }, 'json');
    });
}]);
