-- @author wanggang
-- @date 2015-11-09 15:05
-- @desc 初始
CREATE DATABASE IF NOT EXISTS dataddy DEFAULT CHARSET utf8;

use dataddy;

CREATE TABLE IF NOT EXISTS `config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `namespace` varchar(50) NOT NULL,
  `name` varchar(50) NOT NULL,
  `value` text,
  `last_update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`namespace`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT '配置表';

CREATE TABLE IF NOT EXISTS `statistic` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `page_url` varchar(1024) NOT NULL,
  `page_name` varchar(50) NOT NULL DEFAULT '',
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `use_time` float NOT NULL,
  `access_user` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT '报表访问统计';

CREATE TABLE IF NOT EXISTS `menuitem` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL COMMENT 'title',
  `type` enum('alarm', 'report', 'link', 'dir'),
  `uri` varchar(256) NOT NULL DEFAULT '' COMMENT 'uri',
  `content` text COMMENT '内容',
  `content_type` enum('sql', 'html', 'json') NOT NULL DEFAULT 'sql' COMMENT '内容输出类型',
  `dsn` varchar(250) NOT NULL DEFAULT 'default' COMMENT '数据库连接',
  `desc` text NOT NULL COMMENT '说明',
  `crontab` varchar(250) NOT NULL default '' COMMENT '定时任务设置',
  `mail_title` varchar(250) NOT NULL default '',
  `mail_receiver` text NOT NULL COMMENT '邮件发送对象',
  `mail_memo` text NOT NULL COMMENT '邮件备注',
  `settings` text NOT NULL COMMENT '其他配置信息',
  `disabled` tinyint NOT NULL DEFAULT 0 COMMENT '是否禁用',
  `visiable` tinyint NOT NULL DEFAULT 1 COMMENT '普通用户是否可见',
  `create_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'sql创建时间',
  `create_account` varchar(32) NOT NULL default '' COMMENT '创建用户',
  `modify_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT '菜单项';

CREATE TABLE IF NOT EXISTS `dsn` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(64) NOT NULL,
  remark varchar(250) NOT NULL DEFAULT '',
  dsn varchar(250) NOT NULL,
  create_time timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  modify_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  create_account varchar(32) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT '数据源';

CREATE TABLE IF NOT EXISTS `data_version` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version_id` varchar(50) DEFAULT NULL,
  `table_name` varchar(50) NOT NULL,
  `pk` int(11) NOT NULL,
  `data` text,
  `modify_fields` varchar(100) NOT NULL DEFAULT '',
  `user_id` int(11) NOT NULL DEFAULT '0',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `version_id` (`version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT '数据变更记录';

CREATE TABLE IF NOT EXISTS `role` (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(64) NOT NULL,
  parent_id int(11) NOT NULL DEFAULT 0,
  resource TEXT NULL COMMENT '拥有资源的访问权限',
  config TEXT NULL COMMENT '其它配置',
  PRIMARY KEY (id),
  UNIQUE KEY(name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT '角色定义';

CREATE TABLE IF NOT EXISTS `user` (
  id int(11) NOT NULL AUTO_INCREMENT,
  username varchar(64) NOT NULL,
  password varchar(32) NOT NULL,
  nick varchar(32) NOT NULL,
  roles varchar(100) NOT NULL DEFAULT '',
  is_admin TINYINT NOT NULL DEFAULT 0 COMMENT '是否为管理员',
  last_login_time TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (id),
  UNIQUE KEY(username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT '用户';


-- @author wanggang
-- @date 2015-11-17 18:22
-- @desc 测试数据
INSERT role (id, name, parent_id) VALUES
  (1, '管理员', 0),
  (2, '高级产品', 1),
  (3, '产品', 2),
  (4, '高级运营', 1),
  (5, '运营', 4),
  (6, '媒介', 1),
  (7, '销售', 1),
  (8, '系统开发', 0);

INSERT user (id, username, password, nick, roles, is_admin, last_login_time) VALUES
  (1, 'baboo', '巴布西', '', '1', 1, '2015-11-11 11:11:11'),
  (2, 'zhangshan', '张三', '', '2,5', 0, '2015-11-13 11:11:11'),
  (3, 'lisi', '李四', '','6', 0, '0000-00-00 00:00:00'),
  (4, 'wanger', '王二', '', '8', 0, '2015-11-11 11:11:11');

INSERT dsn (id, name, remark, dsn, create_account) VALUES
  (1, 'default', '默认', 'root:Addev#@!@tcp(127.0.0.1:3306)/ad_report', 'baboo'),
  (2, 'ad_report', '广告报表库', 'root:Addev#@!@tcp(127.0.0.1:3306)/ad_report', 'baboo'),
  (3, 'ad_core', '广告核心库', 'reader:Addev#@!@tcp(127.0.0.1:3306)/ad_core', 'baboo')
;

-- @date 2015-11-24 14:32
ALTER TABLE menuitem ADD COLUMN `parent_id` int NOT NULL DEFAULT 0 COMMENT '父级节点' AFTER visiable;
ALTER TABLE menuitem MODIFY COLUMN type enum('alarm', 'report', 'link', 'folder') NOT NULL DEFAULT 'report';
ALTER TABLE menuitem MODIFY COLUMN content_type enum('sql', 'html', 'json', 'none') NOT NULL DEFAULT 'none';

INSERT menuitem (id, name, type, uri, content, content_type, visiable, parent_id,`desc`,`mail_receiver`,`mail_memo`, `settings`) VALUES
  (1, '基础报表', 'folder', '', '', 'none', 1, 0, '', '', '', ''),
  (2, '分钟数据', 'report', '', '', 'sql', 1, 1, '', '', '', ''),
  (3, '小时数据', 'report', '', '', 'sql', 1, 1, '', '', '', ''),
  (4, '天数据', 'report', '', '', 'sql', 1, 1, '', '', '', ''),
  (5, '媒介报表', 'folder', '', '', 'none', 1, 0, '', '', '', ''),
  (6, '广告位数据', 'report', '', '', 'sql', 1, 5, '', '', '', ''),
  (7, '媒体数据', 'report', '', '', 'sql', 1, 5, '', '', '', ''),
  (8, '财务报表', 'folder', '', '', 'none', 1, 0, '', '', '', ''),
  (9, '收入详情', 'report', '', '', 'sql', 1, 8, '', '', '', ''),
  (10, '监控作业', 'folder', '', '', 'none', 0, 0, '', '', '', ''),
  (11, '广告位流量监控', 'alarm', '', '', 'sql', 0, 10, '', '', '', ''),
  (12, '整体流量监控', 'alarm', '', '', 'sql', 0, 10, '', '', '', ''),
  (13, '机器状态', 'link', 'http://g.sa.adeaz.com', '', 'none', 0, 10, '', '', '', '');

-- @date 2015-11-27 15:15
ALTER TABLE config ADD COLUMN remark varchar(250) NOT NULL DEFAULT '' AFTER value;

-- @date 2015-12-08 12:58
ALTER TABLE user ADD COLUMN `avatar` varchar(255) AFTER `last_login_time`, ADD COLUMN `email` varchar(100) AFTER `avatar`, ADD COLUMN `mobile` varchar(50) AFTER `email`;

-- @date 2015-12-11 10:20 增加密码强度
ALTER TABLE user CHANGE COLUMN `password` `password` varchar(128) NOT NULL;

-- @date 2015-12-14 18:20
ALTER TABLE data_version ADD COLUMN `db_name` varchar(50) NOT NULL  AFTER `version_id`;

-- @date 2016-01-27 20:30
ALTER TABLE menuitem ADD COLUMN safe_code tinyint not null DEFAULT 0 AFTER content_type;

-- @date 2016-02-15
ALTER TABLE `menuitem` ADD COLUMN `sort` smallint NOT NULL DEFAULT 0 AFTER `modify_time`;

--@author 王刚
--@date 2016/04/06 14:36:32
--@desc 添加用户主题设置
------------------------------------------------------
ALTER TABLE user ADD COLUMN theme varchar(1024) NOT NULL DEFAULT '';

--@author 王刚
--@date 2016/04/25 14:04:26
--@desc 用户表添加字段
------------------------------------------------------
ALTER TABLE user ADD COLUMN avatar_small varchar(255) NOT NULL DEFAULT '',
ADD COLUMN config TEXT NULL;

--@author 王刚
--date 2020-09-18 16:11
--@desc 插件代码写表
ALTER TABLE plugin ADD COLUMN content NULL;
ALTER TABLE plugin ADD COLUMN update_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE plugin ADD COLUMN scope varchar(50) NOT NULL default 'report';
