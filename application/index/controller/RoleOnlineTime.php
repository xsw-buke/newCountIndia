<?php
/**
 * 角色的在线时长
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/7/7
 * Time: 10:07
 */

namespace app\index\controller;

use think\Db;
use think\Exception;
use think\Request;

class RoleOnlineTime extends TaskBase
{
	//日期
	private $date;
	//日期数字 找表用的
	private $db;
	private $dateWhereBetween;
	private $create_time;
	const ROLE_PAGE_SIZE = 1000;

	function __construct()
	{
		try {
			$this->create_time = date('Y-m-d H:i:s');
			//中心报表库连接
			$this->db = Db::connect('database.center');
		} catch (Exception $e) {
		}
	}

	//tbl_login_log
	function CountOnlineTime(Request $request)
	{
		//如果有日期重跑
		if (!empty($request->param('date'))) {
			$this->date             = $request->param('date');
			$this->dateWhereBetween = [
				$request->param('date') . " 0:00:00",
				$request->param('date') . " 23:59:59",
			];
			//没有传日期 就是定时任务
		} else {
			$yesterday              = date('Y-m-d', strtotime('-1 day'));
			$this->date             = $yesterday;
			$this->dateWhereBetween = [
				$yesterday . " 0:00:00",
				$yesterday . " 23:59:59",
			];
		}
		echo $this->date . "日 生成玩家登陆时长统计开始" . PHP_EOL;
		//删除这一个小时的投注统计
		$this->_deleteLog();
		//定时任务生成上一个小时入库
		$this->_generateLog();


		echo $this->date . "日 生成玩家登陆时长统计完毕" . PHP_EOL;
	}

	private function _deleteLog()
	{
		$this->db->table('tbl_role_day_count')
			->where(['date' => $this->date])
			->delete();
	}

	private function _generateLog()
	{//获取登陆玩家列表
		$roleList = $this->db->table('tbl_login_log')
			->field('roleid')
			->whereBetween('date', $this->dateWhereBetween)
			->distinct(TRUE)
			->select();
		if (empty($roleList)) {
			echo $this->date . "日 没有登陆玩家" . PHP_EOL;
			return;
		}

		foreach ($roleList as $roleInfo) {
			$roleOnlineTimeAdd = $this->getRoleOnlineTime($roleInfo);
			if ($roleOnlineTimeAdd == FALSE) {
				continue;
			}
			$this->db->table('tbl_role_day_count')
				->insert($roleOnlineTimeAdd);
			echo "{$roleInfo['roleid']} 玩家当天上线时间为 {$roleOnlineTimeAdd['online_time']}" . PHP_EOL;
		}
	}

	private function getRoleOnlineTime($roleInfo)
	{
		//获取今天所有登陆记录
		$loginLogList = $this->db->table('tbl_login_log')
			->field(['eid', 'date', 'roleid'])
			->where(['roleid' => $roleInfo['roleid']])
			->whereBetween('date', $this->dateWhereBetween)
			->select();
		if (empty($loginLogList)) {
			return FALSE;
		}
		$time       = 0;
		$start_time = NULL;
		$end_time   = NULL;
		foreach ($loginLogList as $value) {


			// 是登陆或者零点       没有下线
			if (($value['eid'] == 1002 || $value['eid'] == 1004) && $start_time == NULL && $end_time == NULL) {
				$start_time = strtotime($value['date']);
			}

			//下线 并且 开始时间存在  苦逼滚蛋吧事故湖吧喔
			if ($value['eid'] == 1003 && $start_time != NULL) {
				$end_time = strtotime($value['date']);
			}

			if (!empty($start_time) && !empty($end_time)) {
				//叠加 清空
				$time       += $end_time - $start_time;
				$start_time = NULL;
				$end_time   = NULL;
			}
			/*			//测试
			if ($roleInfo['roleid'] == '1-1501513729') {
							$dump = [
								'start_time' => $start_time,
								'end_time'   => $end_time,
								'time'       => $time,
								'date'       => $value['date']
							];
							dump($dump);
						}*/
		}
		//跑完循环 如果start_time 还存在
		if ($start_time != NULL) {
			$time += strtotime($this->date . " 23:59:59") - $start_time;
		}


		return [
			'role_id'     => $roleInfo['roleid'],
			'online_time' => $time,
			'date'        => $this->date,
			'create_time' => $this->create_time,
			//			'channel_id'        => 1
		];

	}


}