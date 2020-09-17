<!DOCTYPE html>
<!--[if IE 8]> <html lang="en" class="ie8 no-js"> <![endif]-->
<!--[if IE 9]> <html lang="en" class="ie9 no-js"> <![endif]-->
<!--[if !IE]><!-->
<html lang="en" class="no-js">
<!--<![endif]-->
<!-- BEGIN HEAD -->
<head>
    <meta charset="utf-8">
    <title>DDY! 安装向导</title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <meta content="" name="description">
    <meta content="" name="author">
    <!-- BEGIN GLOBAL MANDATORY STYLES -->
    <link href="http://fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all" rel="stylesheet" type="text/css">
    <link href="../../assets/global/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css">
    <link href="../../assets/global/plugins/simple-line-icons/simple-line-icons.min.css" rel="stylesheet" type="text/css">
    <link href="../../assets/global/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css">
    <link href="../../assets/global/plugins/uniform/css/uniform.default.css" rel="stylesheet" type="text/css">
    <!-- END GLOBAL MANDATORY STYLES -->
    <!-- BEGIN PAGE LEVEL PLUGIN STYLES -->
    <link href="../../assets/global/plugins/jqvmap/jqvmap/jqvmap.css" rel="stylesheet" type="text/css">
    <link href="../../assets/global/plugins/morris/morris.css" rel="stylesheet" type="text/css">
    <!-- END PAGE LEVEL PLUGIN STYLES -->
    <!-- BEGIN PAGE STYLES -->
    <link href="../../assets/admin/pages/css/tasks.css" rel="stylesheet" type="text/css"/>
    <!-- END PAGE STYLES -->
    <!-- BEGIN THEME STYLES -->
    <!-- DOC: To use 'rounded corners' style just load 'components-rounded.css' stylesheet instead of 'components.css' in the below style tag -->
    <link href="../../assets/global/css/components-rounded.css" id="style_components" rel="stylesheet" type="text/css">
    <link href="../../assets/global/css/plugins.css" rel="stylesheet" type="text/css">
    <link href="../../assets/admin/layout3/css/layout.css" rel="stylesheet" type="text/css">
    <link href="../../assets/admin/layout3/css/themes/default.css" rel="stylesheet" type="text/css" id="style_color">
    <link href="../../assets/admin/layout3/css/custom.css" rel="stylesheet" type="text/css">
    <!-- END THEME STYLES -->
    <link rel="shortcut icon" href="favicon.ico">
    <style type="text/css">
        .label-self{
            white-space: normal;
        }
        .page-header, .page-header-menu {
            height:50px;
        }
        .page-header .page-title{
            margin-top:10px;
            margin-bottom:10px;
            color: #FFFFFF;
        }
        .container {
            width: 720px !important;
        }
        #alterModel{
            position:fixed !important;
            top:0px;
            _top: expression(eval(document.documentElement.scrollTop));
            z-index:99999;
            margin-left:200px;
        }
        .success-text{color:rgb(69, 182, 175);}
        .danger-text{color:rgb(243, 86, 93);}
        form .controlAutoComplete{
            height: 0px;
            top: -10px;
            padding: 0;
            position: fixed;
            border: 1px solid transparent;
        }
        input[type=checkbox]{
        {
            width: 15px;
            height: 15px;
        }
    </style>
    <script src="../../assets/global/plugins/jquery.min.js" type="text/javascript"></script>
    <script type="text/javascript">
        var checkResult = '<?=$this->checkResult?>',
            isOver = <?=isset($isOver) ? 1 : 0 ?>;

        if(isOver){
            var url = '<?=$nextModule?>';
        }else {
            var url = '/install/index.php?m=<?=$nextModule?>';
        }

        function trim(str) {
            return str.replace(/^\s+|\s+$/g, "");
        }

        function atrim(str) {
            return str.replace(/\s+/g, "");
        }

        function nextStep(){
            if(checkResult != '1'){
                popup_msg('检测未通过，不能进行下一步', 'error');
                return ;
            }
            window.location = url;
        }

        function exitInstall() {
            popup_msg('如果不同意将无法继续安装', 'error');
        }

    </script>
</head>
<body>
<!-- BEGIN HEADER -->
<div class="page-header">
    <div class="page-header-menu">
        <div class="container">
            <h2 class="page-title">DDY安装向导</h2>
        </div>
    </div>
    <!-- END HEADER MENU -->
</div>
<!-- END HEADER -->
<!-- BEGIN PAGE CONTAINER -->
<div class="page-container">
    <!-- BEGIN PAGE HEAD -->
    <div class="page-head">
        <div class="container">
            <!-- BEGIN PAGE TITLE -->
            <div class="page-title">
                <h1><?=$subTitle?></h1>
            </div>
        </div>
    </div>
    <!-- END PAGE HEAD -->
    <!-- BEGIN PAGE CONTENT -->
    <div class="page-content">
        <div class="container">
            <!-- END PAGE BREADCRUMB -->
            <!-- BEGIN PAGE CONTENT INNER -->
            <div class="row margin-top-10">
                <div id="alterModel" class="alert alert-danger fade in hide">
                </div>
                <div class="col-md-12">
                    <?=$__content?>
                </div>
            </div>
            <!-- END PAGE CONTENT INNER -->
        </div>
        <script type="text/javascript">
            var $popup_msg = $('#alterModel'),
                hideTimer = null,
                hideInterval = 10000,
                minShowTime = 500,
                startTime = 0,
                clearHideTimer = function(){
                    if (hideTimer) {
                        window.clearTimeout(hideTimer);
                        hideTimer = null;
                    }
                };

            function popup_msg(msg, type)
            {
                type = type || 'error';

                if (type == 'succ') type = 'success';
                if (type == 'error') type = 'danger';

                msg =
                    '<button type="button" class="close" onclick ="hide_msg();" data-dismiss="alert" aria-hidden="true"></button>' +
                    msg.replace(/<(?:div|p)[^>]*>/gi, '').replace(/<\/(?:div|p)>/gi, '<br/>').replace(/<br\/>\s*$/, '');

                $popup_msg.html(msg).show();
                $popup_msg.attr('class', 'alert alert-' + type);

                startTime = + new Date;
                clearHideTimer();

                if (type == 'success') {
                    hideTimer = setTimeout(function(){ hide_msg() }, hideInterval);
                }
            }

            function hide_msg()
            {
                clearHideTimer();

                var showTime = + new Date - startTime;
                if (showTime < minShowTime) {
                    hideTimer = setTimeout(function() { hide_msg() }, minShowTime - showTime);
                    return;
                }
                $popup_msg.css('display', 'none');
            }

            window.popup_msg = popup_msg;
            window.hide_popup_msg = hide_msg;

        </script>
    </div>
    <!-- END PAGE CONTENT -->
</div>
<!-- END PAGE CONTAINER -->
<!-- BEGIN FOOTER -->
<div class="page-footer">
    <div class="container">
        2016 © DATADDY.COM.
    </div>
</div>
<div class="scroll-to-top">
    <i class="icon-arrow-up"></i>
</div>
<!-- END JAVASCRIPTS -->
</body>
<!-- END BODY -->
</html>
