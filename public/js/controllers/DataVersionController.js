'use strict';

MetronicApp.controller('DataVersionController', function($rootScope, $scope, $http, $timeout, $filter) {
    $scope.versions = [];
    $scope.$on('$viewContentLoaded', function() {
        // initialize core components
        Metronic.initAjax();

    });

     var dh = $rootScope.$on('register_data_version', function(e, context) {
        $scope.context = context;
        $scope.cur_version = null;
    });
    $scope.cur_version = null;

    var loadVersion = function() {
        $scope.versions = [];
        get_json('/' + $scope.context.name + '/versionList.json', { id : $scope.context.pk }, function(data){
            $scope.versions = data;
        });
    };

    $scope.status = {
        isopen: false
    };

    $scope.toggle = function($event) {
        $event.preventDefault();
        $event.stopPropagation();
        if (!$scope.status.isopen) {
            loadVersion();
        }
        $scope.status.isopen = !$scope.status.isopen;
    };

    $scope.select = function($version_id) {
        var version = $filter('filter')($scope.versions, {id: $version_id}, true)[0];

        $scope.cur_version = {
            version_id : '加载中...'
        };

        get_json('/' + $scope.context.name + '/versionData.json', { id : $version_id }, function(data) {
            $scope.context.onSelect(version, data);
            $scope.cur_version = version;
        });
    }

    $scope.$on('$destroy', dh);
});
