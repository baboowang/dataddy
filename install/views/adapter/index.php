<style>
    .mt10{margin-top: 10px;}
    .mt5{margin-top: 5px;}
    .btn-inner{margin-top: 10px;}
</style>
<div class="alert alert-info">
配置保存完后，可在conf/application.ini 直接修改或添加
</div>
<form class="form-horizontal form-row-seperated" action = "/install/index.php?m=adapter&a=userParams" id="userParams">
    <div class="form-body">
        <div class="portlet">
            <div class="portlet-title">
                <div class="caption">
                    <i class="icon-share font-blue-steel"></i>
                    <span class="caption-subject font-blue-steel bold uppercase"><?=$INIT_CONFIG['name']?></span>
                </div>
            </div>
            <div class="portlet-body">
                <?php foreach($INIT_CONFIG['params'] as $key => $p) : ?>
                    <div class="form-group" <?=@$p['type'] == 'hidden' ? 'style="display:none;" id="webPage" ' : ''?>>
                        <label class="col-md-3 control-label">
                            <?=$p['name']?>
                            <?php if(@$p['require'] == 1) : ?>
                                <span class="required">*&nbsp;</span>
                            <?php endif ?>
                        </label>
                        <div class="col-md-4">
                            <?php if(!isset($p['type'])) : ?>
                                <input class="form-control" type="text" autocomplete="off" name="<?=$key?>" value="<?=$p['default']?>">
                            <?php elseif($p['type'] == 'password') : ?>
                                <input type="text" class="controlAutoComplete">
                                <input class="form-control" type="password" autocomplete="off" name="<?=$key?>" value="<?=$p['default']?>">
                            <?php elseif($p['type'] == 'radio') : ?>
                                <div class="radio-list">
                                    <?php foreach($p['options'] as $opVale => $op) : ?>
                                        <input type="radio" <?=$p['default'] == $opVale ? 'checked' : ''?> name="<?=$key?>" value="<?=$opVale?>">
                                        <?=$op?>
                                    <?php endforeach ?>
                                </div>
                            <?php elseif($p['type'] == 'checkbox') : ?>
                                <div class="checkbox-list">
                                    <?php foreach($plugins as $pluKey => $plu) : ?>
                                        <label>
                                            <div class="" title="<?=$plu['mem']?>">
                                                <span>
                                                    <input <?=$plu['name'] == 'Sso' ? 'onclick="showPage(this);"' : '' ?>  <?=$plu['isCheck'] ? 'checked' : ''?> type="checkbox" name="<?=$key?>[]" value="<?=$pluKey?>">
                                                    <?=$plu['name']?>
                                                    <?=$plu['mem'] ? "({$plu['mem']})" : ''?>
                                                </span>
                                            </div>
                                        </label>
                                    <?php endforeach ?>
                                </div>
                            <?php elseif($p['type'] == 'select') : ?>
                                <select class="table-group-action-input form-control" name="<?=$key?>">
                                    <?php foreach($p['options'] as $opVale => $op) : ?>
                                        <option <?=$p['default'] == $opVale ? 'selected' : ''?> value="<?=$opVale?>"><?=$op?></option>
                                    <?php endforeach ?>
                                </select>
                            <?php elseif($p['type'] == 'hidden') : ?>
                                <input type="text" class="form-control" autocomplete="off" name="<?=$key?>" value="<?=$p['default']?>">
                            <?php endif ?>
                            <?php if($key == 'mail_password') :  ?>
                                <p class="mt10">
                                    <label class="mt5">测试邮箱地址:</label><input class="form-control mt10" type="text" id="sendMail" value="<?=$p['default']?>">
                                    <a id="sendMailBtn" onclick="sendMail();" class="btn green btn-inner btn-inner">发送测试邮件</a>
                                </p>
                            <?php endif ?>
                        </div>
                        <div class="col-md-5 label label-warning label-self"><?=$p['exp']?></div>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    </div>
</form>
<div class="row">
    <div class="col-md-offset-3 col-md-9 marginbot">
        <a onclick="submit();" class="btn green">确定</a>
    </div>
</div>
<script type="text/javascript">

    function showPage(el){
        var $ssoPage = $('#webPage');
        if($(el)[0].checked){
            $ssoPage.css('display', '');
        }else{
            $ssoPage.css('display', 'none');
            $ssoPage.find('input').val('');
        }
    }

    function sendMail(){
        var post_params = {
            mail_host: $('input[name=mail_host]').val(),
            mail_port: $('input[name=mail_port]').val(),
            mail_username: $('input[name=mail_username]').val(),
            receive_mail: $('#sendMail').val(),
            mail_password: $('input[name=mail_password]').val()
        };

        $('#sendMailBtn').prop('disabled', true);

        $.post('/install/index.php?m=adapter&a=testMailServer', post_params, function(ret){

            $('#sendMailBtn').prop('disabled', false);

            if (ret.code != 0) {
                popup_msg(ret ? ret.msg : '发生异常错误', 'error');
            } else {

                if (ret.msg) {
                    popup_msg(ret.msg, 'succ');
                }

            }

        }, 'json').error(function(){

            $('#sendMailBtn').prop('disabled', false);

            popup_msg('服务器响应错误', 'error');
        });
    }

    function submit(){
        var $f = $('#userParams'),
            post_params = $f.serialize();

        $.post($f.attr('action') || location.href, post_params, function(ret){

            if (ret.code != 0) {
                popup_msg(ret ? ret.msg : '发生异常错误', 'error');
            } else {

                $f.trigger('ajax_succ', ret);

                if (ret.msg) {
                    popup_msg(ret.msg, 'succ');
                }
                checkResult = 1;
                $f.find(':input').prop('disabled', true);
                popup_msg('配置完成，你现在可以使用软件了', 'success');
                $('.marginbot').html('<a class="btn green" href="javascript:void(0);" onclick="nextStep();" target="_self">开始使用</a>');
            }

            if (ret && ret.redirect_uri) {

                hide_popup_msg();
                if (/javascript\s*:\s*(.+)/.test(ret.redirect_uri)) {
                    $.globalEval(RegExp.$1);
                }
            }

        }, 'json').error(function(){

            $f.find(':submit').prop('disabled', false);

            $f.trigger('on_response');

            popup_msg('服务器响应错误', 'error');
        });
    }
</script>
