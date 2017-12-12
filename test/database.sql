CREATE TABLE `wl_devices` (
  `device_id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '设备ID',
  `device_no` varchar(64) NOT NULL COMMENT '设备ID',
  `device_key` varchar(255) NOT NULL COMMENT '设备通信key',
  `lng` decimal(14,11) DEFAULT '0.00000000000' COMMENT '经度',
  `lat` decimal(14,11) DEFAULT '0.00000000000' COMMENT '纬度',
  `address` varchar(255) NOT NULL COMMENT '位置信息',
  `area_id` int(6) DEFAULT '0' COMMENT '所在区域',
  `status` tinyint(4) DEFAULT '0' COMMENT '0 尚未启用，1 运行中 ，3 暂停，4 停止',
  `protocol_version` varchar(255) DEFAULT NULL,
  `hardware_version` varchar(255) DEFAULT NULL,
  `software_version` varchar(255) DEFAULT NULL,
  `created_time` datetime DEFAULT NULL,
  `updated_time` datetime DEFAULT NULL,
  PRIMARY KEY (`device_id`),
  UNIQUE KEY `device_no` (`device_no`),
  KEY `area_id` (`area_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='设备信息表';


CREATE TABLE `wl_devices_login_logs` (
  `login_id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '登录id',
  `device_id` int(11) unsigned NOT NULL COMMENT '设备ID',
  `device_number` varchar(32) NOT NULL COMMENT '设备号',
   `tags` int(11) NOT NULL DEFAULT 0  COMMENT '标签总数',
   `weight` int(11) NOT NULL DEFAULT 0  COMMENT '总重量 g 为单位',
   `connect_time` datetime DEFAULT NULL COMMENT '连接时间',
  `login_time` datetime DEFAULT NULL COMMENT '登录时间',
  `login_ip` varchar(16) DEFAULT NULL  COMMENT '登录ip',
  PRIMARY KEY (`login_id`),
  KEY `device_id` (`device_id`)
) ENGINE=InnoDB AUTO_INCREMENT=100000000 DEFAULT CHARSET=utf8 COMMENT='设备登录日志';

device_door_logs

action 开关门
status true/false
transaction_number
device_id

door_tag_logs
