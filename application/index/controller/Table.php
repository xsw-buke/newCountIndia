<?php
/**
 * 定时生成表
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/5/14
 * Time: 17:35
 */

namespace app\index\controller;


use think\cache\driver\Redis;
use think\Db;
use think\Exception;
use think\Request;

class Table
{

	private $db;
	private $tomorrow_int;

	const LOG_LIST = [
//		"tbl_register_log_"           => '注册日志',
//		"tbl_login_log_"              => '登陆日志',
		"tbl_gold_log_"               => '金币日志',
		"tbl_exp_log_"                => '经验日志',
		"tbl_level_log_"              => '等级日志',
		"tbl_vip_exp_log_"            => 'vip经验日志',
		"tbl_vip_level_log_"          => 'vip等级日志',
		"tbl_exp_buff_log_"           => '经验buff日志',
		"tbl_upgrade_bonus_buff_log_" => '等级奖励buff日志',

		"tbl_room_log_"               => '房间日志',
		"tbl_prop_log_"                => '道具获得日志',
		"tbl_email_log_"               => '个人邮件日志',
	];

	function __construct()
	{
		//获取明天时间数字
		$this->tomorrow_int = date('Ymd', strtotime('tomorrow'));

		echo "\n--- 定时任务子报表开始 ---";
	}

	/**
	 * 创建第二天的报表
	 *
	 * @param Request $request
	 *
	 * @throws Exception
	 * @throws \think\exception\PDOException
	 */
	function createTable(Request $request)
	{
		$this->db = Db::connect('database.slotdatacenter');
		//dateInTenDays
		$dateInTenDaysInt = date('Ymd', strtotime('+10 day'));
		//表是否存在
		$exist = $this->db->query("show tables like 't_logs_{$dateInTenDaysInt}'");
		if (empty($exist)) {
			//获取总日志表sql
			$createLogsSql = $this->getCreateLogsSql('t_logs_' . $dateInTenDaysInt);

			//生成登录日志表
			$this->db->query($createLogsSql);
			echo "\n--- t_logs_{$dateInTenDaysInt} :日志表生成成功 ---";
		}

		$this->db = Db::connect('database.center');

		if (!empty($request->param('date'))) {
			$this->tomorrow_int = $request->param('date');
		}

		foreach (self::LOG_LIST as $table => $message) {
			//判断表表不存在
			$exist = $this->db->query("show tables like '{$table}{$this->tomorrow_int}'");
			if (empty($exist)) {
				$createTableSql = $this->getCreateLogsSql($table . $this->tomorrow_int, $message);
				//生成各种日志表
				$this->db->execute($createTableSql);
				echo "\n--- {$table}{$this->tomorrow_int}:日志表生成成功 ---";
			}
		}

		echo "\n--- 定时任务生成子报表结束 ---";
	}

	//生成各种日志表
	private function getCreateLogsSql($table, $message = '总日志表')
	{
		return "
		CREATE TABLE `{$table}` (
		  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		  `date` datetime DEFAULT NULL,
		  `sid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id，为0表示来源未知',
		  `roleid`  int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id-角色id，为0表示系统事件 角色id不唯一',
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
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='{$message}';
		";
	}


	/**
	 * 检查明天的日志总表是否未生成
	 * @throws Exception
	 */
	function checkTable()
	{
		$this->db = Db::connect('database.slotdatacenter');
		//判断表表不存在
		$exist = $this->db->query("SHOW TABLES LIKE 't_logs_{$this->tomorrow_int}'");
		if (empty($exist)) {
			//获取登录日志表sql
			$loginSql = $this->getCreateLogsSql('t_logs_' . $this->tomorrow_int);
			//生成登录日志表
			$this->db->query($loginSql);
			echo "\n--- tbl_login_log_{$this->tomorrow_int} :日志总表补充生成 ---";
		} else {
			echo "\n--- 检查{$this->tomorrow_int}日志总表无异常 ---";
		}
	}
}