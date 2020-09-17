<form class="form-horizontal form-row-seperated" action = "/install/index.php?m=user&a=userParams" id="userParams">
    <div class="form-body">
        <div class="portlet">
            <div class="portlet-title">
                <div class="caption">
                    <i class="icon-share font-blue-steel"></i>
                    <span class="caption-subject font-blue-steel bold uppercase"><?=$DATABASE['name']?></span>
                </div>
            </div>
            <div class="portlet-body">
                <?php foreach($DATABASE['params'] as $key => $p) : ?>
                    <div class="form-group">
                        <label class="col-md-3 control-label">
                            <?=$p['name']?>
                            <?php if(@$p['require'] == 1) : ?>
                                <span class="required">*&nbsp;</span>
                            <?php endif ?>
                        </label>
                        <div class="col-md-4">
                        <?php if(!isset($p['type'])) : ?>
                            <input class="form-control" type="text" autocomplete='off' name="<?=$key?>" value="<?=$p['default']?>">
                        <?php elseif($p['type'] == 'password') : ?>
                            <input class="form-control controlAutoComplete" type="text">
                            <input class="form-control" type="password" autocomplete='off' name="<?=$key?>" value="<?=$p['default']?>">
                        <?php elseif($p['type'] == 'radio') : ?>
                            <p>
                                <?php foreach($p['options'] as $opVale => $op) : ?>
                                    <input class="form-control" type="radio" <?=$p['default'] == $opVale ? 'checked' : ''?> name="<?=$opVale?>" value="<?=$opVale?>"><?=$op?>
                                <?php endforeach ?>
                            </p>
                        <?php elseif($p['type'] == 'select') : ?>
                            <select class="table-group-action-input form-control input-medium" name="<?=$key?>">
                                <?php foreach($p['options'] as $opVale => $op) : ?>
                                    <option <?=$p['default'] == $opVale ? 'selected' : ''?> value="<?=$opVale?>"><?=$op?></option>
                                <?php endforeach ?>
                            </select>
                        <?php endif ?>
                        </div>
                        <div class="col-md-5"><?=$p['exp']?></div>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
        <div class="portlet">
            <div class="portlet-title">
                <div class="caption">
                    <i class="icon-share font-blue-steel"></i>
                    <span class="caption-subject font-blue-steel bold uppercase"><?=$ADMIN['name']?></span>
                </div>
            </div>
            <div class="portlet-body">
                <?php foreach($ADMIN['params'] as $key => $p) : ?>
                    <div class="form-group">
                        <label class="col-md-3 control-label">
                            <?=$p['name']?>
                            <?php if(@$p['require'] == 1) : ?>
                                <span class="required">*&nbsp;</span>
                            <?php endif ?>
                        </label>
                        <div class="col-md-4">
                            <?php if(!isset($p['type'])) : ?>
                                <input class="form-control" type="text" autocomplete='off' name="<?=$key?>" value="<?=$p['default']?>">
                            <?php elseif($p['type'] == 'password') : ?>
                                <input class="form-control controlAutoComplete" type="text">
                                <input class="form-control" type="password" autocomplete='off' name="<?=$key?>" value="<?=$p['default']?>">
                            <?php elseif($p['type'] == 'radio') : ?>
                                <p>
                                    <?php foreach($p['options'] as $opVale => $op) : ?>
                                        <input class="form-control" type="radio" <?=$p['default'] == $opVale ? 'checked' : ''?> name="<?=$opVale?>" value="<?=$opVale?>"><?=$op?>
                                    <?php endforeach ?>
                                </p>
                            <?php elseif($p['type'] == 'select') : ?>
                                <select class="table-group-action-input form-control input-medium" name="<?=$key?>">
                                    <?php foreach($p['options'] as $opVale => $op) : ?>
                                        <option <?=$p['default'] == $opVale ? 'selected' : ''?> value="<?=$opVale?>"><?=$op?></option>
                                    <?php endforeach ?>
                                </select>
                            <?php endif ?>
                        </div>
                        <div class="col-md-5"><?=$p['exp']?></div>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-offset-3 col-md-9 marginbot">
            <a onclick="submit();" class="btn green">确定</a>
        </div>
    </div>
    <script type="text/javascript">
            function submit(){
                var $f = $('#userParams'),
                    post_params = $f.serialize(),
                    pwd = trim($('#userParams input[name=admin_password]').val()),
                    rePwd = trim($('#userParams input[name=admin_rePassword]').val());

                if(pwd == '' || pwd != rePwd){
                    popup_msg('两次输入密码不一致', 'error');
                    return false;
                }

                $.post($f.attr('action') || location.href, post_params, function(ret){

                    if (ret.code != 0) {
                        popup_msg(ret ? ret.msg : '发生异常错误', 'error');
                    } else {

                        $f.trigger('ajax_succ', ret);

                        if (ret.msg) {
                            popup_msg(ret.msg, 'succ');
                        }
                        //$f.find(':input').prop('disabled', true);
                        checkResult = 1;
                        $('.marginbot').html('<a class="btn green" onclick="nextStep();" target="_self">下一步</a>');
                        nextStep();
                    }

                }, 'json').error(function(){

                    $f.find(':submit').prop('disabled', false);

                    $f.trigger('on_response');

                    popup_msg('服务器响应错误', 'error');
                });

            };
    </script>
</form>
