--  用于生成测试数据
--
-- Table structure for table `test_obj`
--

DROP TABLE IF EXISTS `test_obj`;
CREATE TABLE `test_obj` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`name` varchar(64) NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='测试组件';

INSERT INTO `test_obj` VALUES (1,'测试数据1'), (2,'测试数据2'), (3,'测试数据3'), (4,'测试数据4'), (5,'测试数据5'), (6,'测试数据6'), (7,'测试数据7'), (8,'测试数据8');

DROP TABLE IF EXISTS `test_income_report`;
CREATE TABLE `test_income_report` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`date` date NOT NULL,
`obj_id` int(11) NOT NULL,
`ad_id` int(11) NOT NULL,
`request` int(11) NOT NULL DEFAULT '0',
`click` int(11) NOT NULL DEFAULT '0',
`business_type` varchar(20) NOT NULL DEFAULT '' COMMENT '业务类型',
`income` decimal(10,2) NOT NULL DEFAULT '0.00',
`cost` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '成本',
`impression` int(11) NOT NULL DEFAULT '0',
`play` int(11) NOT NULL DEFAULT '0',
PRIMARY KEY (`id`),
UNIQUE KEY `date` (`date`,`obj_id`,`ad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

