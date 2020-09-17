ALTER TABLE menuitem
ADD COLUMN dev_content text NULL COMMENT '开发版本',
ADD COLUMN dev_uid INT NOT NULL DEFAULT 0 COMMENT '开发人员ID',
ADD COLUMN release_version_time TIMESTAMP NULL COMMENT '已发布的版本时间',
ADD COLUMN dev_version_time TIMESTAMP NULL COMMENT '开发的版本时间',
ADD COLUMN dev_safe_code TINYINT NOT NULL DEFAULT 0
;

create table dashboard (
    id int not null auto_increment primary key,
    title varchar(50) not null default '',
    uid int not null default 0,
    config text null,
    update_at timestamp not null default current_timestamp on update current_timestamp,
    create_at timestamp not null default current_timestamp
) engine=innodb default charset utf8;
