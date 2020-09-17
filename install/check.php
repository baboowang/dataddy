<?php
//检查项
return array(
    'SYSTEM' => array(
        'name' => '环境检查',
        'params' => array(
            'OS' => array(
                'name' => '操作系统',
                'claim_p' => 'Linux',//要求值
                'current_p' => '',//当前值
                'is_valid' => 0,//是否可用：0不可用;1可用
                'info' => '',//检测不过描述信息
                'strict' => 0 //-1当前值忽略，不用检测，0检测，如果不符合，只是提示，1 严格检测
            ),
            'PHP' => array(
                'name' => 'PHP版本',
                'claim_p' => '7.0.0',
                'current_p' => '',
                'is_valid' => 0,
                'info' => '',
                'strict' => 1
            )
        ),
    ),
    'PHP_MODULAR' => array(
        'name' => 'PHP模块',
        'params' => array(
            'mysqli' => array(
                'name' => 'MysqlI',
                'claim_p' => '',
                'current_p' => '',
                'is_valid' => 0,
                'info' => '',
                'strict' => 1
            ),
            'PDO' => array(
                'name' => 'PDO',
                'claim_p' => '',
                'current_p' => '',
                'is_valid' => 0,
                'info' => '',
                'strict' => 1
            ),
/*
            'redis' => array(
                'name' => 'Redis',
                'claim_p' => '',
                'current_p' => '',
                'is_valid' => 0,
                'info' => '',
                'strict' => 1
            ),
            'memcache' => array(
                'name' => 'Memcache',
                'claim_p' => '',
                'current_p' => '',
                'is_valid' => 0,
                'info' => '',
                'strict' => 1
            ),
 */
            'yaf' => array(
                'name' => 'Yaf',
                'claim_p' => '2.1.1',
                'current_p' => '',
                'is_valid' => 0,
                'info' => '',
                'strict' => 1
            ),
            /*
            'session' => array(
                'name' => 'Session',
                'claim_p' => '',
                'current_p' => '',
                'is_valid' => 0,
                'info' => '',
                'strict' => 1
            )
             */
        )
    ),
    'DIR_RIGHT' => array(
        'name' => '权限检测',
        'params' => array(
            'APPLICATION' => array(
                'name' => '日志生成目录',
                'claim_p' => '可写',
                'current_p' => '',
                'path' => '',
                'is_valid' => 0,
                'info' => '',
                'strict' => 1,
            ),
            'PHP' => array(
                'name' => 'php所在路径',
                'claim_p' => '可执行',
                'current_p' => '',
                'path' => '',
                'is_valid' => 0,
                'info' => '',
                'strict' => 1,
                'modify' => 1
            ),
            '/tmp' => array(
                'name' => '定时任务输出文件',
                'claim_p' => '可写',
                'current_p' => '',
                'path' => '',
                'is_valid' => 0,
                'info' => '',
                'strict' => 1
            )
        )
    ),
    'DATABASE' => array(
        'name' => '数据库信息(MYSQL)',
        'params' => array(
            'host' => array(
                'name' => '数据库服务器',
                'default' => 'localhost',
                'exp' => '服务器地址,一般为localhost',
                'require' => 1
            ),
            'port' => array(
                'name' => '数据库端口',
                'default' => '3306',
                'exp' => '',
                'require' => 1
            ),
            'name' => array(
                'name' => '数据库名',
                'default' => 'dataddy',
                'exp' => '',
                'require' => 1
            ),
            'user' => array(
                'name' => '数据库用户名',
                'default' => '',
                'exp' => '',
                'require' => 1
            ),
            'password' => array(
                'name' => '数据库用户密码',
                'default' => '',
                'type' => 'password',
                'exp' => '',
                'require' => 1
            )
        )
    ),
    'ADMIN' => array(
        'name' => '管理员信息',
        'params' => array(
            'admin_account' => array(
                'name' => '管理员账号',
                'default' => '',
                'exp' => '',
                'require' => 1
            ),
            'admin_password' => array(
                'name' => '管理员密码',
                'default' => '',
                'type' => 'password',
                'exp' => '',
                'require' => 1
            ),
            'admin_rePassword' => array(
                'name' => '重复密码',
                'default' => '',
                'type' => 'password',
                'exp' => '',
                'require' => 1
            )
        )
    ),
    'INIT_CONFIG' => array(
        'name' => '软件初始化配置',
        'params' => array(
            'plugins' => array(
                'name' => '系统插件',
                'default' => 'notice',
                'type' => 'checkbox',
                'options' => 'PLUGINS',
                'exp' => '系统提供的插件,可根据需求，自行选择，也可以后期修改配置进行添加',
            ),
            'sso_page' => array(
                'name' => '单点登陆网址',
                'default' => '',
                'type' => 'hidden',
                'exp' => '单点登陆，用于验证登陆的网址',
            ),
            'log_level' => array(
                'name' => '日志级别',
                'default' => 'notice',
                'type' => 'select',
                'options' => array(
                    'notice' => 'Notice级别',
                    'warning' => 'Warning级别',
                    'error' => 'Error级别'
                ),
                'exp' => '记录日志级别,记录等于或者超过当前级别的日志信息',
                'require' => 1
            ),
            'vim_mode' => array(
                'name' => '开启vim编辑',
                'default' => '1',
                'type' => 'radio',
                'options' => array(
                    '0' => '关闭',
                    '1' => '开启',
                ),
                'exp' => '前台编辑器，是否支持vim模式',
                'require' => 1
            ),
            'num_format' => array(
                'name' => '开启数字格式化',
                'default' => '1',
                'type' => 'radio',
                'options' => array(
                    '0' => '关闭',
                    '1' => '开启',
                ),
                'exp' => '报表显示数字,是否格式化',
                'require' => 1
            ),
            'cookie_expire' => array(
                'name' => 'Cookie保存周期',
                'default' => '3600',
                'exp' => 'cookie保存周期,单位秒',
                'require' => 1
            ),
            'mail_host' => array(
                'name' => '邮箱服务器',
                'default' => 'smtp.example.com',
                'exp' => '形如：smtp.xxx.com',
                'require' => 1
            ),
            'mail_port' => array(
                'name' => '端口',
                'default' => '25',
                'require' => 1,
                'exp' => ''
            ),
            'mail_username' => array(
                'name' => '邮箱账号',
                'default' => 'message@example.com',
                'exp' => '形如：xxx@xxx.com',
                'require' => 1
            ),
            'mail_password' => array(
                'name' => '邮箱密码',
                'type' => 'password',
                'default' => '',
                'exp' => '',
                'require' => 1,
            ),
            'alarm_type' => [
                'name' => '报警渠道',
                'default' => '',
                'exp' => '自定义报警渠道，类weixin, dingding, ...，在报表报警配置使用。如有多个渠道，可在后续配置文件中添加。',
            ],
            'alarm_url' => [
                'name' => '报警发送URL',
                'default' => '',
                'exp' => '形如：http://xxx.com/send_message?receiver={receiver}&message={message}”，其中{receiver}及{message}为宏变量，报警发送时会自动替换',
            ]
        )
    )
);
