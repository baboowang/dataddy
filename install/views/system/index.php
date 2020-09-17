<div class="portlet">
    <div class="portlet-title">
        <div class="caption">
            <i class="icon-share font-blue-steel"></i>
            <span class="caption-subject font-blue-steel bold uppercase"><?=$checkParams['name']?></span>
        </div>
    </div>
    <div class="portlet-body">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>检测项</th>
                    <th>所需配置</th>
                    <th>当前配置</th>
                    <th>检测结果</th>
                    <th>描述</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($checkParams['params'] as $p) :
                ?>
                <tr class="<?=$p['is_valid'] == 1 ? 'success-text' : 'danger-text' ?>" >
                    <th><?=$p['name']?></th>
                    <th><?=$p['claim_p']?></th>
                    <th><?=$p['current_p']?></th>
                    <th><?=$p['is_valid'] == 1 ? '√' : '×'?></th>
                    <th><?=$p['info']?></th>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<div class="portlet">
    <div class="portlet-title">
        <div class="caption">
            <i class="icon-share font-blue-steel"></i>
            <span class="caption-subject font-blue-steel bold uppercase"><?=$checkPhpModules['name']?></span>
        </div>
    </div>
    <div class="portlet-body">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>扩展名称</th>
                <th>所需版本</th>
                <th>当前版本</th>
                <th>检测结果</th>
                <th>描述</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($checkPhpModules['params'] as $p) :
                ?>
                <tr class="<?=$p['is_valid'] == 1 ? 'success-text' : 'danger-text' ?>">
                    <th><?=$p['name']?></th>
                    <th><?=d($p['claim_p'], '-')?></th>
                    <th><?=d($p['current_p'], '-')?></th>
                    <th><?=$p['is_valid'] == 1 ? '√' : '×'?></th>
                    <th><?=$p['info']?></th>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<div class="portlet">
    <div class="portlet-title">
        <div class="caption">
            <i class="icon-share font-blue-steel"></i>
            <span class="caption-subject font-blue-steel bold uppercase"><?=$checkDirRight['name']?></span>
        </div>
    </div>
    <div class="portlet-body">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>检测目录/文件</th>
                <th>实际目录/文件</th>
                <th>所需权限</th>
                <th>目前权限</th>
                <th>检测结果</th>
                <th>描述</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($checkDirRight['params'] as $key => $p) :
                ?>
                <tr class="<?=$p['is_valid'] == 1 ? 'success-text' : 'danger-text' ?>">
                    <th><?=$p['name']?></th>
                    <th>
                        <p><?=d($p['path'], '-')?></p>
                        <input type="text" class="hide" onblur='doModify(this, "<?=$key?>");' name="<?=$key?>">
                    </th>
                    <th><?=d($p['claim_p'], '-')?></th>
                    <th><?=d($p['current_p'], '-')?></th>
                    <th><?=$p['is_valid'] == 1 ? '√' : '×'?></th>
                    <th>
                        <?=$p['info']?>
                        <?=isset($p['modify']) ? '<a onclick=\'modifyParams(this, "' . $key . '");\' class="btn green">修改</a>' : '' ?>
                    </th>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<div class="row">
    <div class="col-md-offset-3 col-md-9">
        <a onclick="nextStep();" class="btn green">下一步</a>
    </div>
</div>
<script type="text/javascript">
    function modifyParams(el, name){
        var $p = $(el).closest('tr').find('input[name='+name+']');
        $p.removeClass('hide');
        $p.prev().addClass('hide');
    }

    function doModify(el, name){
        var p = trim($(el).val());
        if(p == ''){
            $(el).css('border', '1px solid red');
            popup_msg('请输入修改参数', 'error');
            return ;
        }

        p = {
            PHP:p
        };

        $.post('/install/index.php?m=system&a=modifyPHP', p, function(ret){
            if (ret.code != 0) {
                popup_msg(ret ? ret.msg : '发生异常错误', 'error');
            } else {
                if (ret.msg) {
                    popup_msg(ret.msg, 'succ');
                }
                window.location = '/install/index.php?m=system';
            }
        },'json').error(function(){
            popup_msg('服务器响应错误', 'error');
        });
    }
</script>