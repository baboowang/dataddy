--
-- Table structure for table `config`
--

DROP TABLE IF EXISTS `config`;
CREATE TABLE `config` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`namespace` varchar(50) NOT NULL,
`name` varchar(50) NOT NULL,
`value` text,
`remark` varchar(250) NOT NULL DEFAULT '',
`last_update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
PRIMARY KEY (`id`),
UNIQUE KEY `namespace` (`namespace`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='配置表';

--
-- Table structure for table `data_version`
--

DROP TABLE IF EXISTS `data_version`;
CREATE TABLE `data_version` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`version_id` varchar(50) DEFAULT NULL,
`db_name` varchar(50) NOT NULL,
`table_name` varchar(50) NOT NULL,
`pk` int(11) NOT NULL,
`data` text,
`modify_fields` varchar(100) NOT NULL DEFAULT '',
`user_id` int(11) NOT NULL DEFAULT '0',
`create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (`id`),
KEY `version_id` (`version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='数据变更记录';

--
-- Table structure for table `dsn`
--

DROP TABLE IF EXISTS `dsn`;
CREATE TABLE `dsn` (
`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
`name` varchar(64) NOT NULL,
`remark` varchar(250) NOT NULL DEFAULT '',
`dsn` varchar(250) NOT NULL,
`create_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
`modify_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
`create_account` varchar(32) NOT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='数据源';

--
-- Table structure for table `menuitem`
--

DROP TABLE IF EXISTS `menuitem`;
CREATE TABLE `menuitem` (
`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
`name` varchar(64) NOT NULL COMMENT 'title',
`type` enum('alarm','report','link','folder') NOT NULL DEFAULT 'report',
`uri` varchar(256) NOT NULL DEFAULT '' COMMENT 'uri',
`content` text COMMENT '内容',
`content_type` enum('sql','html','json','none') NOT NULL DEFAULT 'none',
`safe_code` tinyint(4) NOT NULL DEFAULT '0',
`dev_content` text NULL COMMENT '开发版本',
`dev_uid` INT NOT NULL DEFAULT 0 COMMENT '开发人员ID',
`release_version_time` TIMESTAMP NULL COMMENT '已发布的版本时间',
`dev_version_time` TIMESTAMP NULL COMMENT '开发的版本时间',
`dev_safe_code` TINYINT NOT NULL DEFAULT 0,
`dsn` varchar(250) NOT NULL DEFAULT 'default' COMMENT '数据库连接',
`desc` text NOT NULL COMMENT '说明',
`crontab` varchar(250) NOT NULL DEFAULT '' COMMENT '定时任务设置',
`mail_title` varchar(250) NOT NULL DEFAULT '',
`mail_receiver` text NOT NULL COMMENT '邮件发送对象',
`mail_memo` text NOT NULL COMMENT '邮件备注',
`settings` text NOT NULL COMMENT '其他配置信息',
`disabled` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否禁用',
`visiable` tinyint(4) NOT NULL DEFAULT '1' COMMENT '普通用户是否可见',
`parent_id` int(11) NOT NULL DEFAULT '0' COMMENT '父级节点',
`create_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'sql创建时间',
`create_account` varchar(32) NOT NULL DEFAULT '' COMMENT '创建用户',
`modify_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '修改时间',
`sort` smallint(6) NOT NULL DEFAULT '0',
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='菜单项';



DROP TABLE IF EXISTS `dashboard`
create table dashboard (
    id int not null auto_increment primary key,
    title varchar(50) not null default '',
    uid int not null default 0,
    config text null,
    update_at timestamp not null default current_timestamp on update current_timestamp,
    create_at timestamp not null default current_timestamp
) engine=innodb default charset utf8;

--
-- Table structure for table `role`
--

DROP TABLE IF EXISTS `role`;
CREATE TABLE `role` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`name` varchar(64) NOT NULL,
`parent_id` int(11) NOT NULL DEFAULT '0',
`resource` text COMMENT '拥有资源的访问权限',
`config` text COMMENT '其它配置',
PRIMARY KEY (`id`),
UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='角色定义';

--
-- Table structure for table `statistic`
--

DROP TABLE IF EXISTS `statistic`;
CREATE TABLE `statistic` (
`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
`page_url` varchar(1024) NOT NULL,
`page_name` varchar(50) NOT NULL DEFAULT '',
`start_time` timestamp NULL DEFAULT NULL,
`end_time` timestamp NULL DEFAULT NULL,
`use_time` float NOT NULL,
`access_user` varchar(50) NOT NULL DEFAULT '',
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='报表访问统计';

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`username` varchar(64) NOT NULL,
`password` varchar(128) NOT NULL,
`nick` varchar(32) NOT NULL,
`roles` varchar(100) NOT NULL DEFAULT '',
`is_admin` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否为管理员',
`last_login_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
`avatar` varchar(255) DEFAULT NULL,
`email` varchar(100) DEFAULT NULL,
`mobile` varchar(50) DEFAULT NULL,
`theme` varchar(1024) NOT NULL DEFAULT '',
`avatar_small` varchar(255) NOT NULL DEFAULT '',
`config` text,
PRIMARY KEY (`id`),
UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户';

INSERT role (id, name, parent_id) VALUES (1, '管理员', 0);


--  生成基本目录数据
INSERT INTO `menuitem` (`id`, `name`, `type`, `uri`, `content`, `content_type`, `safe_code`, `dsn`, `desc`, `crontab`, `mail_title`, `mail_receiver`, `mail_memo`, `settings`, `disabled`, `visiable`, `parent_id`, `create_time`, `create_account`, `modify_time`, `sort`) VALUES
(1, '开发文档', 'folder', '', 'null', 'none', 0, 'default', '', '', '', '', '', '{"icon":"icon-docs"}', 0, 1, 0, '0000-00-00 00:00:00', '', '2017-07-25 00:50:26', -120),
(2, '函数说明', 'report', '', '#!markdown\n\n内嵌PHP是在沙盒中执行，只允许调用白名单中的函数。\n白名单设置规则：\n\n1. 指定的系统函数,下列的系统函数列表\n2. 所有_array_,_str_,_url_,_ddy_为前缀的函数，框架内置函数主要为_ddy_前缀函数，在后面详细说明；应用自定义的函数，也基本以_ddy_做为前缀。\n\n## 系统函数\n\n- [`print`](http://php.net/manual/en/function.print.php) \n- [`var_dump`](http://php.net/manual/en/function.var-dump.php)\n- [`json_encode`](http://php.net/manual/en/function.json-encode.php)\n- [`json_decode`](http://php.net/manual/en/function.json-decode.php)\n- [`count`](http://php.net/manual/en/function.count.php)\n- [`array`](http://php.net/manual/en/function.array.php)\n- [`sizeof`](http://php.net/manual/en/function.sizeof.php)\n- [`is_array`](http://php.net/manual/en/function.is-array.php)\n- [`is_bool`](http://php.net/manual/en/function.is-bool.php)\n- [`is_numeric`](http://php.net/manual/en/function.is-numeric.php)\n- [`is_string`](http://php.net/manual/en/function.is-string.php)\n- [`trim`](http://php.net/manual/en/function.trim.php)\n- [`date`](http://php.net/manual/en/function.date.php)\n- [`time`](http://php.net/manual/en/function.time.php)\n- [`strtotime`](http://php.net/manual/en/function.strtotime.php)\n- [`printf`](http://php.net/manual/en/function.printf.php)\n- [`sprintf`](http://php.net/manual/en/function.sprintf.php)\n- [`number_format`](http://php.net/manual/en/function.number-format.php)\n- [`implode`](http://php.net/manual/en/function.implode.php)\n- [`explode`](http://php.net/manual/en/function.explode.php)\n- [`substr`](http://php.net/manual/en/function.substr.php)\n- [`preg_match`](http://php.net/manual/en/function.preg-match.php)\n- [`preg_match_all`](http://php.net/manual/en/function.preg-match-all.php)\n- [`preg_split`](http://php.net/manual/en/function.preg-split.php)\n- [`preg_replace`](http://php.net/manual/en/function.preg-replace.php)\n- [`parse_url`](http://php.net/manual/en/function.parse-url.php)\n- [`parse_str`](http://php.net/manual/en/function.parse-str.php)\n- [`http_build_query`](http://php.net/manual/en/function.http-build-query.php)\n- [`round`](http://php.net/manual/en/function.round.php)\n- [`intval`](http://php.net/manual/en/function.intval.php)\n- [`ceil`](http://php.net/manual/en/function.ceil.php)\n- [`floor`](http://php.net/manual/en/function.floor.php)\n- [`rand`](http://php.net/manual/en/function.rand.php)\n- [`abs`](http://php.net/manual/en/function.abs.php)\n- [`usort`](http://php.net/manual/en/function.usort.php)\n- [`uasort`](http://php.net/manual/en/function.uasort.php)\n- [`uksort`](http://php.net/manual/en/function.uksort.php)\n- [`sort`](http://php.net/manual/en/function.sort.php)\n- [`asort`](http://php.net/manual/en/function.asort.php)\n- [`ksort`](http://php.net/manual/en/function.ksort.php)\n- [`min`](http://php.net/manual/en/function.min.php)\n- [`max`](http://php.net/manual/en/function.max.php)\n- [`extract`](http://php.net/manual/en/function.extract.php)\n- [`in_array`](http://php.net/manual/en/function.in-array.php)\n\n\n## dataddy内置函数\n\n### `void ddy_hello()`\n> 打印 Hello,world!\n\n---\n\n### `\\GG\\Db\\GlobalDb ddy_db(string $dsn)`\n> 获取DB对象，一般不直接调用该函数；使用ddy_model\n\n**参数**\n\n`$dsn`  数据源ID，在DSN管理中配置的DSN的引用名称\n\n---\n\n### `\\GG\\Db\\Model\\Base ddy_model(string $table, string $dsn = ''default'')`\n> 获取Model对象，进行数据库操作\n\n**参数**\n\n`$table`  表名\n\n`$dsn`    数据源名称\n\n---\n\n### `void ddy_macro(string $name, string $value, bool $quote = TRUE)`\n> 设置模板宏\n\n**参数**\n\n`$name`     宏名称\n\n`$value`	宏值\n\n`$quote`	宏值在引用时，是否使用引号引起\n\n---\n\n### `mixed ddy_data(string $name, mixed $default = NULL)`\n> 获取页面查询条件的值\n\n---\n\n### `number ddy_math_exp(string $exp)`\n> 执行一个算术表达式字符串，返回其计算结果。（沙盒环境，不允许调用`eval`函数）\n\n---\n\n### `void ddy_set_page_data(mixed $data, string $name = ''default'')`\n> 非SQL类型报表，可以通过PHP直接将数据结果集，设置给页面。\n\n`$name` 为数据的名称, **default** 为报表数据\n\n报表数据结果集的格式约定：\n\n```\nddy_set_page_data([\n	"report1" => [  # report1为report id\n		"rows" => [ # report的数据\n			[ "列1" => "值11", "列2" => "值13" ],\n			[ "列1" => "值21", "列2" => "值23" ],\n			[ "列1" => "值31", "列2" => "值33" ],\n		]\n	],\n	"report2" => [\n		"rows" => [\n			[ "列1" => "值11", "列2" => "值13" ],\n			[ "列1" => "值21", "列2" => "值23" ],\n			[ "列1" => "值31", "列2" => "值33" ],\n		]\n	],\n]);\n```\n\n---\n\n### `mixed ddy_get_page_data(string $name = ''default'')`\n> 获取页面的数据\n\n### `void ddy_set_table_options(string $report_id, array $options)`\n> 设置数据表的选项，如果报表的设置框里已有该report\\_id的静态配置，则会合并，覆盖重叠的key配置。\n\n### `void ddy_set_chart_options(string $chart_id, mixed $options)`\n> 设置图表的选项，`$options`可以是完整的amchart的相关配置，也可以简化的数据列名称的配置\n\n### `void ddy_set_options(mixed $options, string $name = ''default'')` \n> 设置通用选项，可以视为报表与框架引擎间的一种传递数据的方式。当`$name`为default时，设置全局报表配置。\n\n### `mixed ddy_json_decode(string $json, mixed $default = [])`\n> 封装的json\\_decode函数， 支持包含注释的json字符串；支持设置默认值。\n\n### `void ddy_view_filter()`\n> 输出定义的查询控件HTML\n\n### `void ddy_register_form_handler(string $report_id, callable $handler = NULL)`\n> 定义可编辑报表的表单处理句柄\n\n**参数**\n\n`$report_id`  可编辑报表的报表ID\n\n`$handler`	  表单处理句柄, `function(&$error, $row_id, $data)`\n\n**示例**\n```\n# df_start\nddy_set_table_options(0, [\n	''fields'' => [\n		''city'' => [ ''class'' => ''text-success'' ]\n	],\n	''edit'' => [\n		''pk'' => ''0'', # 指定主键列，数字视为索引，可以直接写列的展现名称（officeCode)。如果有多列联合主键，可以指定一个数据：[ ''列a'', ''列b'' ]；默认为第一列；如果主键列有属性eid，则主键值优先取该属性的值\n		''columns'' => [\n			//可编辑的列名\n			''国家'' => [\n				''type'' =>  "select",  # 类型，目前仅 select 和 text ，默认为text\n				#''rule'' => ''numeric'', # 字段校验规则，\n				''name'' => ''country'',  # 字段名，默认为列名\n				''options'' => [        # select的选项\n					[ "label" => "美国", "value" => "USA" ],\n                    [ "label" => "中国", "value" => "China" ],\n                    [ "label" => "日本", "value" => "Janpan" ],\n                    [ "label" => "法国", "value" => "France" ],\n                    [ "label" => "澳大利亚", "value" => "Australia"] \n				]\n			],\n			''phone'' => [] # 使用默认设置\n		],\n		#''temp'' => TRUE   # 临时编辑，不保存\n	]\n]);\n# df_end\n\n/**\n * $error   错误消息\n * $row_id  主键值，如果设置的多列联合主键，值用半角逗号分隔，例：列a值,列b值\n * $data     通过规则检测的数据 key => value\n */\nddy_register_form_handler(function (&$error, $row_id, $data)\n{\n	$m = ddy_model(''offices'', ''demo'');\n	$result = $m->update([''officeCode'' => $row_id], $data);\n						  \n	if ($result === FALSE) {\n		$error = "保存错误";\n	    return FALSE;\n	}\n						  \n	return TRUE;\n});\n```\n\n### `array ddy_current_session()`\n> 获取当前用户的信息，包含下列字段\n```\n{\n	"id" : 1,\n	"username" : "用户名",\n	"nick" : "昵称",\n	"roles" : "角色ID",\n	"email" : "xxx@xx.com",\n	"mobile" : "159xxxxxxx"\n}\n```\n', 'none', 1, 'default', '', '', '', '', '', '', 0, 1, 1, '0000-00-00 00:00:00', '', '2017-07-25 09:01:13', -122),
(3, '模板设计', 'report', '', '#!markdown\n\n## 模板解析过程\n\n### 1. 控件解析及替换\n搜索所有控件定义`${...}`，创建控件类型对象的Filter对象，并将控件的当前值替换掉控件定义。\n\n语法：`${form_name|label_name|default_value|type_define}`\n- *form_name*      **required**     控制表单名称，即在请求query串中的参数名\n- *label_name*     **optional**     表单的标签名称，可为空 \n- *default_value*  **optional**     默认值，如果是类型为时间，默认值直接写[时间表达式](http://php.net/manual/zh/datetime.formats.relative.php) \n- *type_define*    **optional**     系统类型包含: date,string,enum,bool，类型参数可通过 .key 或 (key:val,key:val) 方式传入, .key 形式为 (key:true) 的简写\n    \n### 2. 内嵌PHP执行\n\n### 3. 按分号拆分SQL语句\n\n### 4. 解析SQL\n\n#### 4.1 解析SQL配置\n语法：`-- @xxx=...`\n配置以特定SQL注释的方式，写在SQL语句的开头，一个配置一行\n配置针对当前SQL语句，一般定义数据插件，标题，ID等\nSQL配置会和全局配置中的相关配置合并\n\n#### 4.2 宏替换 \n宏使用方式：\n\n`{xx}` 直接用名为xx的宏的值替换当前占位符\n\n`{?xx}` 如果名为xx的宏的值为空，则删除当前行，如果当前定义处于行头`-- {?xx}`，则删除内容至最后（当前SQL语句）\n\n`{?!xx}` 如果名为xx的宏的值不为空，则删除当前行\n\n`{4?xx}` 同`{?xx}`，但如果删除行数为4（可修改数字）\n\n宏来自于全局配置及当前控件定义的宏\n\n### 4.3 SQL字段配置\n`... AS ''列名'', -- @{"key":"value"}`\n\n字段配置以SQL注释的方式，嵌入在SQL语句之中\n\n`click/request*100 AS ''上报请求CTR'' -- @round({上报点击}/{请求}*100, 4)`  \n\n上术规则，定义了上报请求CTR的计算规则，用于实时计算平均/合计项。\n\n\n### 5. 清除注释，执行SQL', 'none', 1, 'default', '', '', '', '', '', '', 0, 1, 1, '0000-00-00 00:00:00', '', '2017-07-25 09:01:17', -121),
(4, '系统插件', 'report', '', '#!markdown\n\n## 查询插件/查询控件\n\n查询控件是用户和报表的主要交互机制，通过较好的交互体验，获取用户的输入，并将期转换成报表的查询字段。\n\n查询控件都继承自 `\\MY\\Filter_Abstract` 类，需要实现UI渲染及值的校验工作。\n\n控件在报表中的调用语法：`${form_name|label_name|default_value|type_define}`\n\n- *form_name*      **required**     控制表单名称，即在请求query串中的参数名\n- *label_name*     **optional**     表单的标签名称，可为空 \n- *default_value*  **optional**     默认值，如果是类型为时间，默认值直接写[时间表达式](http://php.net/manual/zh/datetime.formats.relative.php) \n- *type_define*    **optional**     系统类型包含: date,string,enum,bool等，默认为date。类型的属性或参数可通过 .key 或 (key:val,key:val) 方式传入, .key 形式为 (key:true) 的简写。key/value对，也可以由一个函数生成，见下示例。\n\n示例\n`${date|日期|yesterday}`\n`${date|日期|-7 days,yesterday|date_range}`\n`${ratio|百分比|0|number.decimal(min:0,max:100)}` \n`${type|类型||enum(ddy_generate_type)}`\n\n控件通用属性\n\n- `raw`    控件值是否不需要处理（默认会使用SQL转义，并用引号引起)，有一些控制该值默认为`true`，比如`number`控件\n- `macro`  控件值是否定义为一个宏引用，所有非宏引用的控件，在控件的调用处，都会会控件本身的值做替换，宏控件，则替换为空白；如`date_range`之类控件，该值默认为`true`\n\n### `string` 文本输入控件\n\n### `number` 数字输入控件\n**特殊属性**\n\n- `multiple` bool 多个数字模式，用逗号分隔\n- `min` number 最小值\n- `max` number 最大值\n- `decimal` bool 是否支持小数\n- [inputmask](https://github.com/RobinHerbots/jquery.inputmask/blob/3.x/README_numeric.md)插件支持的自定义选项\n\n**默认属性**\n\n- `raw` true\n\n### `enum` 固定选项\n**特殊属性**\n\n- `multiple` 是否可多选\n- `minWidth` 控件展示的最小宽度，默认为150\n- 选项值 key:value对\n\n示例: `${ad_type|广告类型|brand|enum(brand:品牌广告,union:联盟广告,adx:ADX广告)}`\n\n### `date` 日期控件\n**特殊属性**\n\n- `format`  指定日期格式化规则，默认为 `%Y-%m-%d`\n- `limit`	指定日期，相对于当天的最大距离天数\n- `end`		指定日期控件最大可选日期\n- `month`   指定为月份选择模式\n\n### `date_range` 日期范围控件\n特殊属性参考`date`控件\n\n**默认属性**\n\n- `macro` true\n- `range` 最大日期范围，默认`31`\n\n### `time`	时间选择控件\n**特殊属性**\n\n属性参考`date`控件\n\n- `hour` 是否为小时模式，默认为分钟模式\n\n### `time_range` 时间范围选择控件\n特殊属性参考`time`控件\n\n**默认属性**\n\n- `macro` true\n- `range` 最大日期范围，默认`30`\n\n### `bool` 开关选项\n\n**默认属性**\n\n- `macro` true\n\n### `combine` 组合控件\n将几个控件的值，组合成一个，一般用来做条件判断时使用。\n组合的控件名称由属性传入，见示例\n\n**默认属性**\n\n- macro true\n\n示例\n\n`${a|控件a}`\n`${b|控件b}`\n`${c|组合控件||combine(a,b)}`\n\n有一种简化的写法，可以直接使用多个宏变量的组合条件\n`{a,b}`, `{?a,b}`, `{?!a,b,c}` \n', 'none', 1, 'default', '', '', '', '', '', '', 0, 1, 1, '0000-00-00 00:00:00', '', '2017-07-25 09:01:20', -123),
(5, '报表高级功能', 'report', 'open:9f034b63948763d489f944319c2eb3de', '#!markdown\n\n## 全局宏配置\n可以在系统配置中，新建立一个名称 `macro`的配置，平台全部的报表，可以引用到其定义的宏。\n宏定义，就是定义一个JSON对象，key => value形式。通过 `{key}`引用到value的值。\n\n## SQL查询缓存\n\n通过配置table选项`sql_cache`来开启SQL缓存，值为缓存时间，单位秒。\n\ncli模式（cron作业）会强制不使用缓存，页面请求需要强制刷新缓存，可以在请求服务器的参数加上`_disable_cache=1`\n\n使用场景：\n\n通过CRON定时去生成查询耗时比较长的SQL，所有用户查询，直接读取缓存。\n\n\n## 外部报表\n\n某些情况，需要将报表公开给第三方人员查看，但又不想公开内部系统给第三方，带来安全隐患。\n\n外部报表，使用单独的网站入口文件，需要web服务器单独配置一个域名使用；外部报表严格限制了访问内容，只能访问到指定的页面。\n\n内部报表的入口文件为：`public/index.php`\n\n外部报表的入口文件为：`public/open/index.php`\n\n\n1. 如何将一个报表设置为外部报表？\n\n	在报表的URI配置项里，配置 `open:32位md5字符串`。32位的md5字符串，是公开报表的访问路径。\n\n	例：`open:098f6bcd4621d373cade4e832627b4f6` 的外部访问url为 `http://xxx/open/098f6bcd4621d373cade4e832627b4f6`\n\n2. 如何给页面设置访问密码？\n\n	大部分情况下，因为路径本身是一个md5串，具有一定的保密功能，不太需要设置密码。如果真想再设置一个访问密码，可以这么设置`open:32位md5字符串@密码`。\n\n', 'none', 1, 'default', '', '', '', '', '', '', 0, 1, 1, '0000-00-00 00:00:00', '', '2017-07-25 09:01:23', -124),
(6, '预警说明', 'report', '', '#!markdown\n\n## 预警信息发送\n有时候会需要预警报表，意思就是这个报表的数据并不是供用户查看的，或者说不仅仅是供用户查看的，而是作为后台定时脚本独立运行，并且将运行结果发送给相应负责人的。\n如果是这样的脚本，我们需要配置“Crontab时间配置”和“配置”,来发送报警数据，目前平台支持两种发送：邮件和微信。\n这里给出完整的配置示例：\n\n###邮件发送\nCrontab时间配置: */30 * * * * mail  \n每隔30分钟运行脚本，并且将运行输出结果邮件发送给配置人员。\n邮件配置\n\n\n`{\n	"mail" : {\n		"receiver" : "zhangsan@mail.com,lisi@mail.com",\n		"subject" : "这是一个xxx的预警"\n	}\n}`\n\n\n###微信发送\nCrontab时间配置: */30 * * * * alarm  \n每隔30分钟运行脚本，并且将运行输出结果邮件发送给配置人员。\n微信配置\n\n\n`{\n	"alarm" : {\n		"type" : "weixin",\n		"receiver" : {\n			"weixin" : "zhangsan,lisi"\n		}\n	}\n}`\n\n\n另外除了这种通过配置来发送报警信息外，同样系统也封装了相应的函数\n\n`ddy_adeaz_mail($receiver, $msg, $title);`\n\n`ddy_adeaz_alarm($receiver,  $msg);`\n\n当然了不管是邮件发送还是微信发送都需要有环境的支持，比如邮件发送需要配置自己的或者第三方的邮件配置，如果是微信发送则需要有相应的微信发送接口\n', 'none', 1, 'default', '', '', '', '', '', '', 0, 1, 1, '0000-00-00 00:00:00', '', '2017-07-25 09:01:27', 0),
(7, '报表示例', 'folder', '', NULL, 'none', 0, 'default', '', '', '', '', '', '', 0, 1, 0, '0000-00-00 00:00:00', '', '2017-07-25 00:50:36', 0),
(8, 'sql语句的报表', 'report', '', '${date|日期|-6 days,yesterday|date_range.macro(range:30)};\n${obj_id|对象id||testObj.macro.raw};\n${show_income|显示收入|on|bool.macro};\n${show_cost|显示成本|0|bool.macro};\n${cond|||combine(show_income,show_cost)};\n\n<?php\n\n/*\n*上面是配置的过滤插件：日期插件，对象插件(testObj,还记得安装时候选择的“TestFilter”吗)，两个boolean插件，最后一个“cond”是“show_income”和“show_cost”的合并条件，其中有一个变量为真，则该变量为真\n*\n*示例一：直接使用数据库表查询，可以通过配置：-- {?show_income}，告诉sql控制器处理处理sql语句时候是否显示去掉和拼接“SUM(income) AS ''收入''”,“-- {?show_cost}”功能类似\n*     “-- @{点击}/{请求}*100”配置用来控制服务器处理汇总数据时候，将以怎样的方式来处理“CTR”字段。\n*      日期插件生成的是一个时间段，传递给后台的参数是“from_date”开始日期，“to_date”结束日期，“{?from_date}”这里的“?”是告诉处理引擎根据是否有传递该变量值而决定加或者去除该行\n*	\n*示例二：-- {?cond}配置告诉处理引擎如果该条件为真则执行对应的sql，如果为假，则忽略改sql\n*\n*示例三：使用了“系统配置”的“macro”配置的“income”和“obj”，处理引擎会根据配置替换这两个变量为真实值\n*\n*/\n\n?>\n\n-- @id=示例一\nSELECT\n	me.date AS ''日期'',\n 	CONCAT(obj.name , ''【'', obj.id,''】'') AS ''对象'',\n	SUM(request) AS ''请求'',\n	SUM(click) AS ''点击'',\n	SUM(impression) AS ''展现'',\n	SUM(income) AS ''收入'', -- {?show_income}\n	SUM(cost) AS ''成本'', -- {?show_cost}\n	SUM(click)/SUM(request)*100 AS ''CTR'' -- @{点击}/{请求}*100\nFROM test_income_report AS me\nLEFT JOIN test_obj AS obj\nON me.obj_id = obj.id\nWHERE me.date >= {?from_date}\nAND me.date <= {?to_date}\nAND me.obj_id IN ({?obj_id})\nGROUP BY me.date, me.obj_id;\n\n-- {?cond}\n-- @id=示例二\nSELECT \n   me.date AS ''日期'',\n 	CONCAT(obj.name , ''【'', obj.id,''】'') AS ''对象'',\n	SUM(income) AS ''收入'', -- {?show_income}\n	SUM(cost) AS ''成本'', -- {?show_cost}\n	SUM(impression) AS ''展现''\nFROM test_income_report AS me\nLEFT JOIN test_obj AS obj\nON me.obj_id = obj.id\nWHERE me.date >= {?from_date}\nAND me.date <= {?to_date}\nAND me.obj_id IN ({?obj_id})\nGROUP BY me.date, me.obj_id;\n\n-- @id=示例三\n-- @sum=0\n-- @avg=1\nSELECT\n   me.date AS ''日期'',\n	CONCAT(obj.name , ''【'', obj.id,''】'') AS ''对象'',\n	SUM(request) AS ''请求'',\n	SUM(click) AS ''点击'',\n	SUM(impression) AS ''展现'',\n	SUM(income) AS ''收入'', -- {?show_income}\n	SUM(cost) AS ''成本'', -- {?show_cost}\n	SUM(click)/SUM(request)*100 AS ''CTR'' -- @{点击}/{请求}*100\nFROM {income} AS me\nLEFT JOIN {obj} AS obj\nON me.obj_id = obj.id\nWHERE date >= {?from_date}\nAND date <= {?to_date}\nAND me.obj_id IN ({?obj_id})\nGROUP BY me.date, me.obj_id', 'none', 0, 'default', '', '', '', '', '', '', 0, 1, 7, '0000-00-00 00:00:00', '', '2017-07-25 09:26:52', 0),
(9, '带图表的报表', 'report', '', '${date|日期|-6 days,yesterday|date_range.macro(range:30)};\n${obj_id|对象id||testObj.macro.raw};\n\n-- @sum=1\n-- @svg=1\nSELECT\n   me.date AS ''日期'',\n 	CONCAT(obj.name , ''【'', obj.id,''】'') AS ''对象'',\n	SUM(request) AS ''请求'',\n	SUM(click) AS ''点击'',\n	SUM(impression) AS ''展现'',\n	SUM(income) AS ''收入'',\n	SUM(cost) AS ''成本'',\n	SUM(click)/SUM(request)*100 AS ''CTR'' -- @{点击}/{请求}*100\nFROM test_income_report AS me\nLEFT JOIN test_obj AS obj\nON me.obj_id = obj.id\nWHERE me.date >= {?from_date}\nAND me.date <= {?to_date}\nAND me.obj_id IN ({?obj_id})\nGROUP BY me.date, me.obj_id;', 'none', 0, 'default', '', '', '', '', '', '{\n	"chart" : {\n		"valueAxes": [{\n			"id":"v1",\n			"position": "left"\n		}, {\n			"id":"v2",\n			"position": "right"\n		}],\n		"graphs" : [\n			{ "valueField" : "请求", "valueAxis" : "v1" },\n			{ "valueField" : "展现", "valueAxis" : "v1" },\n			{ "valueField" : "点击", "valueAxis" : "v1" },\n			{ "valueField" : "成本", "valueAxis" : "v2" },\n			{ "valueField" : "收入", "valueAxis" : "v2" }\n		]\n	}\n}', 0, 1, 7, '0000-00-00 00:00:00', '', '2017-07-25 09:27:04', 0),
(10, 'php代码生成的报表', 'report', '', '${date|日期|-6 days,yesterday|date_range.macro(range:30)};\n${obj_id|对象id||testObj.macro}\n${rate|扣量系数|15|number.macro.raw(min:10,max:90)};\n${business|业务类型|0|enum.macro.raw(ddy_page_business_type)};\n\n<?php\n###df_start\nfunction ddy_page_business_type(){\n	return [\n		''0'' => ''所有'',\n		''taobao'' => ''淘宝'',\n		''tencent'' => ''腾讯'',\n		''baidu'' => ''百度''\n	];\n}\n\n###df_end\n\nfunction ddy_ctr($click, $request) {\n	return $request > 0 ? round($click / $request * 100, 2) . ''%'' : 0;\n}\n\n$start = ddy_data(''from_date'');\n$end = ddy_data(''to_date'');\n$obj = ddy_data(''obj_id'');\n$rate = ddy_data(''rate'') / 100;\n$business = ddy_data(''business'');\n\n$where = array(\n	''&/me.date'' => array(\n		''>='' => $start,\n		''<='' => $end,\n		''__logic'' => ''AND''\n	)\n);\n\nif ($obj) {\n	$where[''&/me.obj_id''] = $obj;\n}\n\nif ($business) {\n	$where[''&/me.business_type''] = $business;\n}\n\n$attrs = array(\n	''select'' => "date AS `日期`, CONCAT(obj.name, ''【'', obj.id,''】'') AS ''对象'', SUM(request) AS `请求`, SUM(impression) AS `展现`, SUM(click) AS `点击`,SUM(income) AS `收入`",\n	''group_by'' => ''me.date, me.obj_id''\n);\n\n$table = "__USER_DB_NAME__.test_income_report me LEFT JOIN __USER_DB_NAME__.test_obj obj ON me.obj_id = obj.id";\n$m = ddy_model($table);\n$rows = $m->select($where, $attrs);\nif ($rows) {\n	foreach($rows as &$row) {\n		$row[''请求''] = floor($row[''请求''] * (1 - $rate));\n		$row[''点击''] = floor($row[''点击''] * (1 - $rate));\n		$row[''ctr''] = ddy_ctr($row[''点击''], $row[''请求'']);\n	}\n	ddy_set_page_data(\n        array(\n            ''这是一个php代码生成的报表'' => array(\n                ''rows'' => $rows\n            )\n        )\n	);\n} else {\n	echo "SELECT ''没有数据'' AS ''结果''";\n} \n?>', 'none', 0, 'default', '', '', '', '', '', '{\n	"table": {\n		"sum": false,\n		"avg": true\n	}\n}', 0, 1, 7, '0000-00-00 00:00:00', '', '2017-07-25 09:27:14', 0),
(11, '系统监控', 'folder', '', NULL, 'none', 0, 'default', '', '', '', '', '', '', 0, 1, 0, '0000-00-00 00:00:00', '', '2017-07-25 00:50:58', 0),
(12, '系统监控的脚本', 'report', '', '<?php\n//假如这是一个监测广告位流量的脚本，我们设置一个阀值，对超过这个阀值的数据进行报警。报表输出数据将会发送给配置的邮件收件人\n//当然了这只是一个示例脚本，所以这里没有设置“Crontab时间配置”，脚本并不会作为定时脚本，后台运行\n\n$where = array(\n	''&/me.date'' => array(\n		''>='' => date(''Y-m-d'', strtotime(''-7 day'')),\n		''<='' => date(''Y-m-d'', strtotime(''-1 day'')),\n		''__logic'' => ''AND''\n	)\n);\n\n$vavle = ''1400'';\n\n$attrs = array(\n	''select'' => "date AS `日期`, CONCAT(obj.name, ''【'', obj.id,''】'') AS ''对象'', SUM(request) AS `请求`, SUM(impression) AS `展现`, SUM(click) AS `点击`,SUM(income) AS `收入`",\n	''group_by'' => ''me.date, me.obj_id'',\n	''having'' => "`请求` > {$vavle}"\n);\n\n$table = "__USER_DB_NAME__.test_income_report me LEFT JOIN dataddy_t1.test_obj obj ON me.obj_id = obj.id";\n$m = ddy_model($table);\n$rows = $m->select($where, $attrs);\nif ($rows) {\n	ddy_set_page_data(\n        array(\n            ''流量预警报表'' => array(\n                ''rows'' => $rows\n            )\n        )\n	);\n} else {\n	echo "SELECT ''没有数据'' AS ''结果''";\n} \n?>', 'none', 1, 'default', '', '', '', '', '', '{\n	"mail" : {\n		"receiver" : "zhangsan@mail.com,lisi@mail.com",\n		"subject" : "这是一个xxx的预警"\n	}\n}', 0, 1, 11, '0000-00-00 00:00:00', '', '2017-07-27 09:19:58', 0);
-- 生成示例配置
INSERT INTO `config` (`id`, `namespace`, `name`, `value`, `remark`, `last_update_time`) VALUES
(1, 'system', 'macro', '{\n	"income" : "__USER_DB_NAME__.test_income_report",\n	"obj": "__USER_DB_NAME__.test_obj"\n}', '宏配置,在报表界面写的sql语句里面可使用这里的宏变量', '2017-07-25 02:54:14'),
(2, 'system', 'data_rule', '{\n	"sum" : {\n		"ignore" : "(?:[^a-z]ID|CTR|CPM|CPC|率)|(^客户|广告位|投放|媒体|应用$)",\n		"fields" : {\n			"请求CTR" : "round({点击}/{请求}*100, 4)"\n		}\n	}\n}', '数据统计规则，比如下面的配置：对报表数据做总计操作时候会忽略掉“ignore”正则配置的字段，“fields”配置的是报表包含这个字段的时候做sum操作的计算规则', '2017-07-25 09:37:42'),
(3, 'system', 'system', '{\n	"site_name" : "这是网站名称",\n	"copyright" : "这是网站备案域名"\n}', '系统配置', '2017-07-25 09:40:20');
