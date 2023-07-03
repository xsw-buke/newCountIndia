<?php
/**
 * 创建表格
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/9/5
 * Time: 21:50
 */

namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class CreateLogTable extends Command
{

	private $db;
	private $db_datacenter;
	private $dateYmd;

	const CREATE_TABLE = 'CREATE TABLE';
	const LOG_CREATE_SQL = [
		'tbl_gold_log_' => "(
			  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			  `create_time` datetime DEFAULT NULL,
			  `sid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id，为0表示来源未知',
			  `role_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id-角色id，为0表示系统事件 角色id不唯一',
			  `eid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '事件id 大分类',
			  `esrc` int(11) unsigned DEFAULT '0' COMMENT '事件来源 子分类',
			  `add_gold` bigint(20) DEFAULT '0' COMMENT '改变量',
			  `change_gold` bigint(20) DEFAULT '0' COMMENT '改变后的金币',
			  `level` int(20) DEFAULT '0' COMMENT '等级',
			  `game_id` int(20) unsigned DEFAULT '0' COMMENT '游戏id',
			  `activity_id` varchar(255) DEFAULT '0' COMMENT '活动id',
			  `small_game_number` int(20) unsigned DEFAULT '0' COMMENT '小游戏次数',
			  PRIMARY KEY (`id`),
			  KEY `activity` (`role_id`,`esrc`,`activity_id`) USING BTREE,
			  KEY `gameReport` (`create_time`,`role_id`,`game_id`,`esrc`,`level`,`sid`) USING BTREE
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='金币日志';
			",
		/*'tbl_utc8_gold_log_' => "(
			  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			  `create_time` datetime DEFAULT NULL,
			  `sid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id，为0表示来源未知',
			  `role_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id-角色id，为0表示系统事件 角色id不唯一',
			  `eid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '事件id 大分类',
			  `esrc` int(11) unsigned DEFAULT '0' COMMENT '事件来源 子分类',
			  `add_gold` bigint(20) DEFAULT '0' COMMENT '改变量',
			  `change_gold` bigint(20) DEFAULT '0' COMMENT '改变后的金币',
			  `level` int(20) DEFAULT '0' COMMENT '等级',
			  `game_id` int(20) unsigned DEFAULT '0' COMMENT '游戏id',
			  `activity_id` varchar(20) DEFAULT '0' COMMENT '活动id',
			  `small_game_number` int(20) unsigned DEFAULT '0' COMMENT '小游戏次数',
			  `utc8_create_time` datetime DEFAULT NULL,
			  PRIMARY KEY (`id`),
			  KEY `activity` (`role_id`,`esrc`,`activity_id`) USING BTREE,
			  KEY `gameReport` (`create_time`,`role_id`,`game_id`,`esrc`,`level`,`sid`) USING BTREE
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='UTC8金币日志';
			",*/

		'tbl_exp_log_' => " (
			  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
              `create_time` datetime DEFAULT NULL,
              `sid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id，为0表示来源未知',
			  `role_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id-角色id，为0表示系统事件 角色id不唯一',
			  `eid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '事件id 大分类',
			  `esrc` int(11) unsigned DEFAULT '0' COMMENT '事件来源 子分类',
			  `add_exp` int(20) DEFAULT '0' COMMENT '改变量',
			  `change_exp` text COMMENT '改变后的经验',
			  `add_level` int(20) DEFAULT '0' COMMENT '增加等级',
			  `game_id` int(11) DEFAULT NULL,
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='经验日志';",

		'tbl_vip_exp_log_' => " (
			  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			  `create_time` datetime DEFAULT NULL,
			  `sid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id，为0表示来源未知',
			  `role_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id-角色id，为0表示系统事件 角色id不唯一',
			  `eid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '事件id 大分类',
			  `esrc` int(11) unsigned DEFAULT '0' COMMENT '事件来源 子分类',
			  `add_exp` int(20) DEFAULT '0' COMMENT '改变量',
			  `change_exp` int(20) DEFAULT '0' COMMENT '改变后的经验',
			  `add_level` int(20) DEFAULT '0' COMMENT '增加等级',
			  `activity_id` varchar(20) DEFAULT '0' COMMENT '活动id',
			  PRIMARY KEY (`id`)
		 	) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='vip经验日志';	",

		'tbl_email_log_' => " (
			  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
              `create_time` datetime DEFAULT NULL,
			  `sid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id，为0表示来源未知',
			  `role_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id-角色id，为0表示系统事件 角色id不唯一',
			  `eid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '事件id 大分类',
			  `esrc` int(11) unsigned DEFAULT '0' COMMENT '事件来源 子分类',
			  `msgid` bigint(20) DEFAULT '0' COMMENT '邮件id',
			  `info` varchar(1024) DEFAULT NULL COMMENT '信息',
			  `activity_id` varchar(255) DEFAULT NULL COMMENT '活动id',
			  PRIMARY KEY (`id`),
			  KEY `role_date` (`role_id`,`create_time`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='个人邮件日志';",

		'tbl_room_log_' => "(
			  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			  `create_time` datetime DEFAULT NULL,
			  `sid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id，为0表示来源未知',
			  `role_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id-角色id，为0表示系统事件 角色id不唯一',
			  `eid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '事件id 大分类',
			  `esrc` int(11) unsigned DEFAULT '0' COMMENT '事件来源 子分类',
			  `game_id` bigint(20) DEFAULT '0' COMMENT '游戏id',
			  `gold` varchar(255) DEFAULT '0' COMMENT '金币',
			  PRIMARY KEY (`id`),
			  KEY `sid` (`sid`),
              KEY `role_id` (`role_id`,`game_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='房间日志';",

		'tbl_ad_log_'       => "(
			  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			  `create_time` datetime DEFAULT NULL,
			  `sid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id，为0表示来源未知',
			  `role_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id-角色id，为0表示系统事件 角色id不唯一',
			  `eid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '事件id 大分类',
			  `esrc` int(11) unsigned DEFAULT '0' COMMENT '事件来源 子分类',
			  `ad_type` varchar(255) DEFAULT NULL COMMENT '广告类型',
			  `msg` varchar(255) DEFAULT NULL,
              `pay_status` int(11) DEFAULT '0',
			  PRIMARY KEY (`id`),
			  KEY `create_time` (`create_time`),
			  KEY `adReport1` (`sid`,`ad_type`,`role_id`) USING BTREE,
              KEY `adRe[prt2` (`sid`,`role_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='广告日志';",
		'tbl_activity_log_' => " (
			  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			  `create_time` datetime NOT NULL,
			  `sid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id，为0表示来源未知',
			  `role_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id-角色id，为0表示系统事件 角色id不唯一',
			  `eid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '事件id 大分类',
			  `esrc` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '事件来源 子分类',
			  `level` int(20) NOT NULL DEFAULT '0' COMMENT '等级',
			  `activity_id` int(20) NOT NULL DEFAULT '0' COMMENT '活动id',
			  `json_info` varchar(1024) NOT NULL,
			  `add_gold` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '活动增加金币',
			  `change_gold` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '改变后金币',
			  PRIMARY KEY (`id`),
			  KEY `activity` (`role_id`,`eid`,`activity_id`),
			  KEY `gameReport` (`create_time`,`role_id`,`esrc`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='运营活动日志';",

		'tbl_currency_log_'  => " (
			  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			  `create_time` datetime DEFAULT NULL,
			  `sid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id，为0表示来源未知',
			  `role_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id-角色id，为0表示系统事件 角色id不唯一',
			  `eid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '事件id 大分类',
			  `esrc` int(11) unsigned DEFAULT '0' COMMENT '事件来源 子分类',
			  `add_gold` bigint(20) DEFAULT '0' COMMENT '改变量',
			  `change_gold` bigint(20) DEFAULT '0' COMMENT '改变后的宝石',
			  `level` int(20) DEFAULT '0' COMMENT '等级',
			  `game_id` int(20) unsigned DEFAULT '0' COMMENT '游戏id',
			  `activity_id` varchar(20) DEFAULT '0' COMMENT '活动id',
			  PRIMARY KEY (`id`),
			  KEY `activity` (`role_id`,`eid`,`activity_id`),
			  KEY `gameReport` (`create_time`,`role_id`,`game_id`,`esrc`,`level`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='宝石币日志';"
	];


	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'CreateLogTable' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '创建日志表命令' )/**
		 * 定义形参
		 * Argument::REQUIRED = 1; 必选
		 * Argument::OPTIONAL = 2;  可选
		 */
		     ->addArgument ( 'cronTab', Argument::OPTIONAL, '是否是定时任务' )
		     ->addArgument ( 'start_date', Argument::OPTIONAL, '开始日期:格式2012-12-12' )
		     ->addArgument ( 'end_date', Argument::OPTIONAL, '结束日期' )// 运行 "php think list" 时的简短描述
		     ->setHelp ( "当不给开始结束时间,默认只跑当天;\n只给了开始时间,跑指定的一天;\n给了开始和结束时间,跑指定范围内所有数据;" );
	}


	/**
	 *     * 生成所需的数据表
	 * php think test  调用的方法
	 *
	 * @param Input  $input  接收参数对象
	 * @param Output $output 操作命令
	 *
	 * @return int|null|void
	 * @throws \think\Exception
	 */
	protected function execute ( Input $input, Output $output )
	{
		//数据库连接
		$this->db            = Db::connect ( 'database.log' );
		$this->db_datacenter = Db::connect ( 'database.datacenter' );

		//是定时任务
		if ( !empty( $input->getArgument ( 'cronTab' ) ) ) {
			//生成明天的日志
			$this->dateYmd = date ( 'Ymd', strtotime ( 'tomorrow' ) );
			$output->writeln ( "---{$this->dateYmd}日定时任务开始---" );
			//生成数据源日志表 TODO::测试
			$exist = $this->db_datacenter->query ( "show tables like 't_logs_{$this->dateYmd}'" );
			if ( empty( $exist ) ) {
				$this->db_datacenter->execute ( self::getLogsSql ( $this->dateYmd ) );
				$output->writeln ( "---t_logs_{$this->dateYmd}:总日志表生成成功---" );
			}
			//遍历生成日志
			foreach ( self::LOG_CREATE_SQL as $table => $createTableSql ) {
				//判断表表不存在
				$exist = $this->db->query ( "show tables like '{$table}{$this->dateYmd}'" );
				if ( empty( $exist ) ) {
					$this->db->execute ( self::CREATE_TABLE . "`{$table}{$this->dateYmd}`" . $createTableSql );
					$output->writeln ( "---{$table}{$this->dateYmd}:日志表生成成功---" );
				}
			}
			$output->writeln ( "---本次定时任务结束---" );
			exit;
		}
		//获取传输的时间
		$start_date = $input->getArgument ( 'start_date' );
		$end_date   = $input->getArgument ( 'end_date' );

		if ( empty( $start_date ) || empty( $end_date ) ) {
			$output->writeln ( "---start_date或者end_date未传入---" );
			exit;
		}


		while ( $start_date <= $end_date ) {
			//循环生成报表
			foreach ( self::LOG_CREATE_SQL as $table => $createTableSql ) {
				//日志日期充值
				$this->dateYmd = date ( 'Ymd', strtotime ( $start_date ) );
				//生成数据源日志表 TODO::测试
				$exist = $this->db_datacenter->query ( "show tables like 't_logs_{$this->dateYmd}'" );
				if ( empty( $exist ) ) {
					$this->db_datacenter->execute ( $this->getLogsSql ( $this->dateYmd ) );
					$output->writeln ( "---t_logs_{$this->dateYmd}:总日志表生成成功---" );
				}

				//判断表表不存在
				$exist = $this->db->query ( "show tables like '{$table}{$this->dateYmd}'" );
				//如果不存在
				if ( empty( $exist ) ) {
					//生成各种日志表
					$this->db->execute ( self::CREATE_TABLE . "`{$table}{$this->dateYmd}`" . $createTableSql );
					$output->writeln ( "---{$table}{$this->dateYmd}日志生成---" );
				}
			}
			$start_date = date ( 'Y-m-d', strtotime ( "+1 day", strtotime ( $start_date ) ) );
		}
		$output->writeln ( "---本次定时任务结束---" );
	}

	private function getLogsSql ( $dateYmd )
	{
		return "CREATE TABLE `t_logs_{$dateYmd}` (
		  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		  `date` datetime DEFAULT NULL,
		  `sid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id，为0表示来源未知',
		  `roleid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id-角色id，为0表示系统事件 角色id不唯一',
		  `eid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '事件id 大分类',
		  `esrc` int(11) unsigned DEFAULT '0' COMMENT '事件来源 子分类',
		  `p1` bigint(20) DEFAULT '0' COMMENT '参数1',
		  `p2` bigint(20) DEFAULT '0' COMMENT '参数2',
		  `p3` bigint(20) DEFAULT '0' COMMENT '参数3',
		  `p4` bigint(20) unsigned DEFAULT '0' COMMENT '参数4',
		  `p5` bigint(20) DEFAULT '0' COMMENT '参数5',
		  `p6` bigint(20) DEFAULT '0' COMMENT '参数6',
		  `ps1` varchar(1024) DEFAULT NULL COMMENT '字符串参数1',
		  `ps2` varchar(1024) DEFAULT NULL COMMENT '字符串参数2',
		  `ps3` varchar(1024) DEFAULT NULL COMMENT '字符串参数3',
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='总日志表';
		";
	}
}