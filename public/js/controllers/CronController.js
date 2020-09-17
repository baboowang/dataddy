'use strict';

MetronicApp.controller('CronController', ['$rootScope', '$scope', '$http', '$log', 'Notification', '$stateParams', '$modal',
function($rootScope, $scope, $http, $log, Notification, $stateParams, $modal) {

    $scope.params = $stateParams;

    $scope.cron_list = [];

    var refresh_list = function(){
        get_json('/cron/list.json',function(cron_list){
            $scope.cron_list = cron_list;
        });
    };

    var data_loading = function(flag) {
        loading($('#cron-table'), flag);
    };

    $scope.$on('$viewContentLoaded',function(){

        Metronic.initAjax();
        refresh_list();
    });

    $scope.dtOptions = get_dtoption('cron');

    $scope.save_cron = function(item) {
        var cron = (item.enable ? '' : '#') + item.cron;

        data_loading(true);

        $http.post('/menu/save?format=json', { id : item.report_id, crontab : cron }).success(function(ret) {
            data_loading(false);

            if (ret && ret.code == 0) {
                Notification.success('更新CRON成功，[' + item.report_name + ']');
                return;
            }

            Notification.error('更新CRON失败:' + (ret.message || SERVER_ERROR));
        });
    }

    $scope.open_cron_form = function (cronId) {
        var modalInstance = $modal.open({
            templateUrl: 'cron_form.html',
            controller: 'CronFormController',
            size: 'lg',
            resolve: {
                cronId : function(){
                    return cronId;
                }
            }
        });

        modalInstance.result.then(function(){
            refresh_list();
        },function(){
        });
    };
}]);

MetronicApp.controller('CronFormController',['$rootScope','$scope','$http','$log','Notification',
'cronId','$modalInstance',
function($rootScope,$scope,$http,$log,Notification,cronId,$modalInstance){
    $scope.cron = {};
    $scope.title = cronId ? '更新CRON,数据加载中...':'创建CRON';
    $scope.loading  = false;

    if (cronId){
        $scope.loading = true;
    }

    get_json('/cron/detail.json',{id:cronId},function(data){
    });


}]);
