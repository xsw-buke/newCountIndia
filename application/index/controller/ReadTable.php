<?php
/**
 * 驼峰控制器名需要read_table
 * 总日志读取分配到各个分日志
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/5/28
 * Time: 15:50
 */

namespace app\index\controller;


use app\index\model\Test;
use think\Db;
use think\Exception;
use think\Log;

class ReadTable
{
	//center 库连接
	private $center_db;
	//logs总表库连接
	private $log_db;
	//今天日期的数字 获取表用
	private $date_int;
	//今天日期
	private $date;
	//今天时间
	private $date_time;


	private $eid_type;
	//分页数量大小 1000; TODO测试改成100
	const SIZE = 1000;
	//事件对应表
	const EID_TYPE = [
		//		0    => 'server_log',//未知来源日志
		1    => 'tbl_server_log',//服务器启动
		1001 => 'tbl_register_log',//登录表名
		1002 => 'tbl_login_log',//登录
		1003 => 'tbl_login_log',//登出
		2001 => 'tbl_gold_log',//金币改变
		2002 => 'tbl_exp_log',//经验值改变
		2003 => 'tbl_level_log',//等级改变
		2004 => 'tbl_vip_exp_log',//vip经验值改变
		2005 => 'tbl_vip_level_log',//vip等级改变
		2006 => 'tbl_exp_buff_log',//经验双倍buff
		2007 => 'tbl_upgrade_bonus_buff_log',//升级奖励双倍buff
		3001 => 'tbl_room_log',//进入房间日志
		3002 => 'tbl_room_log',//离开房间日志
		2008 => 'tbl_prop_log', //获得道具
		2101 => 'tbl_email_log',//收到个人邮件
		2102 => 'tbl_email_log',//收到特殊个人邮件
		2103 => 'tbl_email_log',//打开个人邮件
	];


	function __construct()
	{

		ini_set('memory_limit', '3072M');// 临时设置最大内存占用为3G
		ini_set('max_execution_time', '3600');
		$this->date_int  = date('Ymd');
		$this->date      = date('Y-m-d');
		$this->date_time = date('Y-m-d H:i:s');
		try {
			$this->center_db = Db::connect('database.center');
			$this->log_db    = Db::connect('database.slotdatacenter');
		} catch (Exception $e) {
			echo $e->getMessage();
			echo "\n--- 数据库连接异常 ---";
			die;
		}

		echo "\n--- {$this->date_time}定时任务总日志读取开始 ---";
	}

	/**
	 * 获取表格
	 *
	 * @param $eid
	 * @param $date
	 *
	 * @return mixed
	 */
	private static function getTable($eid, $date)
	{
		$date_int = date('Ymd', strtotime($date));

		$eidType = [
			//		0    => 'server_log',//未知来源日志
			1    => 'tbl_server_log',//服务器启动
			1001 => 'tbl_register_log',//注册
			1002 => 'tbl_login_log',//登录
			1003 => 'tbl_login_log',//登出
			2001 => 'tbl_gold_log_' . $date_int,//金币改变
			2002 => 'tbl_exp_log_' . $date_int,//经验值改变
			2003 => 'tbl_level_log',//等级改变
			2004 => 'tbl_vip_exp_log_' . $date_int,//vip经验值改变
			2005 => 'tbl_vip_level_log',//vip等级改变
			2006 => 'tbl_exp_buff_log_' . $date_int,//经验双倍buff
			2007 => 'tbl_upgrade_bonus_buff_log_' . $date_int,//升级奖励双倍buff
			3001 => 'tbl_room_log_' . $date_int,//进入房间日志
			3002 => 'tbl_room_log_' . $date_int,//离开房间日志

			2008 => 'tbl_prop_log_' . $date_int, //获得道具
			2101 => 'tbl_email_log_' . $date_int,//收到个人邮件
			2102 => 'tbl_email_log_' . $date_int,//收到特殊个人邮件
			2103 => 'tbl_email_log_' . $date_int,//打开个人邮件
		];
		return $eidType[$eid];
	}

	function readLogs()
	{
		//TODO::需要锁表 防止另外的进程又来 跑定时任务  ,先不考虑 锁冲突

		// 获取最新一条定时 获取总日志记录
		$log = $this->log_db->table('t_read_logs_log')
			->order('id desc')
			->find();

		//如果时间跨天 也就意味着跨表 		//把前一天日志跑完 ,添加日期今天

		if ($log['date_int'] != $this->date_int) {
			echo $log['date_int'] ;
			//处理表数据 这是昨天的 需要放到昨天的日志
			$this->handleLog("t_logs_" . $log['date_int'], $log);
			//追加t_read_logs_log的记录日志 跨天  下次id 为  0+1
			$insert['logs_id'] = 0;
			//重新赋值  为log['data_int'] +1 防止重启的bug
			$this->date_int = date('Ymd', strtotime($log['date_int']) + 86400);

		} else {
			echo  $this->date_int;
			//接收最大的ID
			$insert['logs_id'] = $this->handleLog("t_logs_" . $this->date_int, $log);
		}
		$insert['create_time'] = date('Y-m-d H:i:s');
		$insert['date_int']    = $this->date_int;


		//插入最终记录日志
		$result = $this->log_db->table('t_read_logs_log')
			->insert($insert);
		if ($result == FALSE) {
			echo "\n --- logs总日志{$this->date_int}操作记录失败 ---";
		}
		echo "\n --- logs总日志{$this->date_int}操作记录成功 ---";
		die;

	}

	//$table 总日志表
	private function handleLog(string $table, array $log)
	{

		$lowerLimit = $log['logs_id'];
		//给予默认,防止为0入数据库
		$upperLimit = $log['logs_id'];
		$count      = $this->log_db->table($table)
			->where('id', '>', $log['logs_id'])
			->count();
		$pageCount  = ceil($count / self::SIZE);
		//页数小于总页数
		for ($page = 1; $page <= $pageCount; $page++) {
			$lowerLimit = $lowerLimit + self::SIZE * ($page - 1) + 1;
			//如果是最后一页
			if ($page == $pageCount) {
				$upperLimit = $log['logs_id'] + $count;
			} else {
				$upperLimit = $lowerLimit + self::SIZE - 1;
			}
			//			dump([$lowerLimit, $upperLimit]);
			//获取一千条总日志 分别插入其他日志
			$logs = $this->log_db->table($table)
				->whereBetween('id', [$lowerLimit, $upperLimit])
				->select();
			//之前手动复制 导致自增ID不从0开始

			foreach ($logs as $value) {
				//是已知的类型 入库
				if (array_key_exists($value['eid'], self::EID_TYPE)) {
					//清除ID
					unset($value['id']);

					//调用动态方法 入库
					$this->center_db->table(self::getTable($value['eid'], $value['date']))
						->insert($value);
				}
				Log::record(json_encode($value), 'notice');

			}
			//			echo in_array(9999, $this->eid_type);
		}
		if ($upperLimit == $log['logs_id']) {
			echo "\n--- 没有新的总日志 ---";
		}

		//返回最后的ID
		return $upperLimit;
	}


}