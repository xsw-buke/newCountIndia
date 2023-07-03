<?php
/**
 * 小时活动日志生成
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/6/3
 * Time: 10:16
 */

namespace app\index\controller;

use think\Db;
use think\Exception;
use think\Request;

class ActivityLog extends TaskBase
{
	//日期
	private $date;
	//日期数字 找表用的
	private $date_int;
	private $hour;
	private $db;
	private $dateWhereBetween;
	private $activityList;

	//ESCR 活动获得
	const ACTIVITY_GET = 12;
	//改变值  金币变更
	const  CHANGE_FIELD = 'p1';

	//ACTIVITY_ID
	//  其他来源
	const  ACTIVITY_OTHER_SOURCES = [
		'IN',
		[101, 102, 103, 104, 105, 106, 301, 302, 303]
	];
	//其他来源插入用
	const  ACTIVITY_OTHER_SOURCES_ID = 1;
	//  后台充值 查询用
	const ACTIVITY_BACKGROUND_RECHARGE = 11;
	//  报表活动ID
	const ACTIVITY_BACKGROUND_RECHARGE_ID = 2;
	// 邮件领取
	const  ACTIVITY_MAIL_GET = 401;
	//  报表活动ID
	const  ACTIVITY_MAIL_GET_ID = 3;


	function __construct()
	{
		try {
			//游戏列表的获取
			$this->activityList = Db::connect('database.portal')
				->table('tp_activity_config')
				->field('activity_id')
				->distinct(TRUE)
				->select();
			$this->activityList = array_column($this->activityList, 'activity_id');
			//中心报表库连接
			$this->db = Db::connect('database.center');
		} catch (Exception $e) {
		}

	}

	//确定时间范围 一个小时 还是遍历
	function countActivityLog(Request $request)
	{
		//如果是新的一个小时定时任务
		if ($request->param('newHour') == TRUE) {
			$time = strtotime('-1 hour');
			//时间
			$this->date = date('Y-m-d', $time);
			//表后数字
			$this->date_int = date('Ymd', $time);
			//目前小时
			$this->hour                = date('H', $time);
			$this->dateWhereBetween[0] = $this->date . " {$this->hour}:00:00";
			$this->dateWhereBetween[1] = $this->date . " {$this->hour}:59:59";
			//删除这一个小时的投注统计
			$this->_deleteLog();
			//定时任务生成上一个小时入库
			$this->_generateLog();
			die;
		}
		//报表重跑  获得两个参数 ,一个为开始时间
		$startDate = $request->param('startDate');
		//持续小时
		$durationHour = $request->param('duration');

		$time = strtotime($startDate);
		//重跑$hour个小时 的报表
		for ($i = $durationHour; $i > 0; $i--) {
			//时间
			$this->date = date('Y-m-d', $time);
			//表后数字
			$this->date_int = date('Ymd', $time);
			//目前小时
			$this->hour                = date('H', $time);
			$this->dateWhereBetween[0] = $this->date . " {$this->hour}:00:00";
			$this->dateWhereBetween[1] = $this->date . " {$this->hour}:59:59";

			//删除这一个小时的投注统计
			$this->_deleteLog();
			//定时任务生成上一个小时入库
			$this->_generateLog();

			//变数,时间加上3600秒
			$time += 3600;
		}


	}

	// 根据类型生成报表
	private function _generateLog()
	{
		echo "日期:{$this->date} 小时:{$this->hour} - 生成活动小时日志开始...... " . self::PHP_EOL;
		//其他获取金币入库
		$this->generateReport(self::ACTIVITY_OTHER_SOURCES, self::ACTIVITY_OTHER_SOURCES_ID);
		//后台充值
		$this->generateReport(self::ACTIVITY_BACKGROUND_RECHARGE, self::ACTIVITY_BACKGROUND_RECHARGE_ID);
		//邮件获取金币入库
		$this->generateReport(self::ACTIVITY_MAIL_GET, self::ACTIVITY_MAIL_GET_ID);
		//运营活动获取的游戏
		foreach ($this->activityList as $activity_id) {
			$this->generateReport(self::ACTIVITY_GET, $activity_id, $activity_id);
		}

		//获取所有游戏
		echo "日期:{$this->date} 小时:{$this->hour} - 生成活动小时日志结束 " . self::PHP_EOL;
	}

	/**
	 * 获取上一个小时 所有参加活动获取金币玩家列表
	 *
	 * @param $activity_id
	 * @param $esrc
	 *
	 * @return array|bool
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\ModelNotFoundException
	 * @throws \think\exception\DbException
	 */
	private function getRoleList($esrc, $activity_id = NULL)
	{
		//游戏ID为这个 有压注消耗的
		$map['esrc'] = $esrc; //活动获得金币
		!empty($activity_id) && $map['ps1'] = $activity_id;//活动id字段 特殊的报表没有活动ID
		//前期玩家不用分页 TODO::后期可能玩家要分页
		$list = $this->db->table('tbl_gold_log_' . $this->date_int)
			->field(['roleid'])
			->where($map)
			->whereBetween('date', $this->dateWhereBetween)
			->distinct('true')
			->select();

		if (empty($list)) return FALSE;
		return array_column($list, 'roleid');

	}

	private function _deleteLog()
	{
		echo "日期:{$this->date};小时:{$this->hour} - 删除旧数据开始......" . PHP_EOL;
		$map    = [
			'date_time' => "{$this->date} {$this->hour}:00:00",
		];
		$result = $this->db->table('tbl_activity_log')
			->where($map)
			->delete();
		if ($result == FALSE) {
			echo "日期: {$this->date} 小时: {$this->hour} tbl_activity_log 没有数据删除" . self::PHP_EOL;
			return;
		}
		echo "日期: {$this->date} 小时: {$this->hour} tbl_activity_log 删除成功" . self::PHP_EOL;
	}


	/**
	 * 生成报表
	 *
	 * @param $esrc                int|array  查询条件
	 * @param $activity_id_insert  int 插入数据库的活动ID
	 * @param $activity_id         int 运营活动的ID
	 *
	 * @throws \think\db\exception\BindParamException
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\ModelNotFoundException
	 * @throws \think\exception\DbException
	 * @throws \think\exception\PDOException
	 */
	private function generateReport($esrc, $activity_id_insert, $activity_id = NULL)
	{
		// 获取时间段内 改游戏的 活动获取金币的玩家列表
		$roleList = $this->getRoleList($esrc, $activity_id);
		//这个活动这段时间没有人玩
		if (empty($roleList)) return;
		//遍历玩家列表  得到该玩家在上个小时 这个游戏  统计的数据
		foreach ($roleList as $roleId) {
			$map = [
				'roleid' => $roleId,
				'esrc'   => $esrc,//子类型 活动获得
			];
			!empty($activity_id) && $map['ps1'] = $activity_id;//活动id字段 特殊的报表没有活动ID
			$add = [
				'date'        => $this->date,
				'role_id'     => $roleId,
				'activity_id' => $activity_id_insert,
				'date_time'   => $this->date . " {$this->hour}:00:00",
			];

			//spin_cost投注消耗 和 spin_count投注总次数
			$count = $this->db->table('tbl_gold_log_' . $this->date_int)
				->field([
					'count(*) as get_count',
					'sum(p1) as get_gold'
				])
				->where($map)
				->whereBetween('date', $this->dateWhereBetween)
				->find();
			$add   = array_merge($add, $count);


			$result = $this->db->table('tbl_activity_log')
				->insert($add);
			if ($result == FALSE) {
				echo "玩家ID:{$roleId};活动ID:{$activity_id_insert}日志入库失败" . PHP_EOL;
			}

			// 输出日志
			$msg = '时间: ' . $add['date_time'];
			$msg .= ' - 会员: ' . $roleId;
			$msg .= ' - 活动: ' . $activity_id_insert;
			$msg .= ' - 获取金币次数: ' . $add['get_count'];
			$msg .= ' - 总获取金币: ' . $add['get_gold'];
			echo $msg . PHP_EOL;
		}
	}
}