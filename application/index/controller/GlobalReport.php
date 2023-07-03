<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/6/23
 * Time: 11:20
 */

namespace app\index\controller;


use think\Db;
use think\Exception;
use think\Request;

class GlobalReport extends TaskBase
{
	//日期
	private $date;
	private $date_int;
	//日期数字 找表用的
	private $hour;
	private $db;
	private $dateWhereBetween;


	const EID_LOGIN = 1002;

	function __construct()
	{
		try {
			//中心报表库连接
			$this->db = Db::connect('database.center');
		} catch (Exception $e) {
		}

	}

	//确定时间范围 轮训
	function countGlobalReport(Request $request)
	{
		//如果是新的一个小时定时任务
		if ($request->param('newHour') == TRUE) {
			$time = strtotime('-1 hour');
			//时间
			$this->date     = date('Y-m-d', $time);
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
			/*
						echo "<br />";
						dump($this->dateWhereBetween);*/
			//变数,时间加上3600秒
			$time += 3600;
		}
	}

	private function _generateLog()
	{
		echo "日期:{$this->date} 小时:{$this->hour} - 生成全局报表日志开始...... " . self::PHP_EOL;

		$add = [
			'date'      => $this->date,
			'date_time' => "{$this->date} {$this->hour}:00:00",
		];

		//在线人数 这个范围内最多的人数 峰值 谷值 online_role_min_count
		$onlineInfo = $this->db->table('tbl_online_info')
			->whereBetween('date_time', $this->dateWhereBetween)
			->field([
				'max(online_num) as online_role_count',
				'min(online_num) as online_role_min_count'
			])
			->find();


		$add['online_role_count']     = intval($onlineInfo['online_role_count']);//峰值
		$add['online_role_min_count'] = intval($onlineInfo['online_role_min_count']);//峰值

		$map = [
			'eid' => self::EID_LOGIN,
		];
		//登陆人数
		$add['login_role_count'] = $this->db->table('tbl_login_log')
			->whereBetween('date', $this->dateWhereBetween)
			->where($map)
			->count('DISTINCT roleid');

		//注册人数 考虑到注册不会重复 也就是不需要筛选条件 where 就不需要了
		$add['register_role_count'] = $this->db->table('tbl_register_log')
			->whereBetween('date', $this->dateWhereBetween)
			->count();

		//有效充值人数
		$map             = ['type' => 2];//已付款
		$validOrderCount = $this->db->table('tbl_pay_order_log')
			->field([
				'count(*) as valid_recharge_count', //有效订单数
				'coalesce(sum(price),0) as valid_recharge_sum', //有效充值金额总计
				'count(distinct role_id) as recharge_role_count'  //有效充值人数
			])
			->whereBetween('date_time', $this->dateWhereBetween)
			->where($map)
			->find();
		$map             = ['type' => 1];//下单
		$orderCount      = $this->db->table('tbl_pay_order_log')
			->field([
				'count(*) as recharge_count', //总订单数
				'coalesce(sum(price),0) as recharge_sum', //总订单金额
			])
			->whereBetween('date_time', $this->dateWhereBetween)
			->where($map)
			->find();

		$add = array_merge($add, $validOrderCount, $orderCount);
		$result = $this->db->table('tbl_global_report')
			->insert($add);
		if ($result == FALSE) {
			echo $msg = '时间: ' . $this->date . '小时:' . $this->hour . "日志入库失败" . PHP_EOL;
		}

		// 输出日志
		$msg = '时间: ' . $this->date . '小时:' . $this->hour;
		$msg .= ' - 在线人数: ' . $add['online_role_count'];
		$msg .= ' - 登陆人数: ' . $add['login_role_count'];
		$msg .= ' - 注册人数: ' . $add['register_role_count'];
		$msg .= ' - 有效订单人数: ' . $add['recharge_role_count'];
		$msg .= ' - 有效订单数: ' . $add['valid_recharge_count'];
		$msg .= ' - 有效订单金额: ' . $add['valid_recharge_sum'];
		$msg .= ' - 订单数: ' . $add['recharge_count'];
		$msg .= ' - 订单金额: ' . $add['recharge_sum'];
		echo $msg . PHP_EOL;

		echo "生成全局报表日志结束" . self::PHP_EOL;

	}

	/**
	 * 获取上一个小时 所有游戏玩家列表
	 *
	 * @param $game_id
	 *
	 * @return array|bool
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\ModelNotFoundException
	 * @throws \think\exception\DbException
	 */
	private function getRoleList($game_id)
	{
		//游戏ID为这个 有压注消耗的
		$map = [
			'p3'   => $game_id,
			'esrc' => self::SPIN_COST, //压注消耗
		];
		//前期玩家不用分页
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
		$result = $this->db->table('tbl_global_report')
			->where($map)
			->delete();
		if ($result == FALSE) {
			echo "日期: {$this->date} 小时: {$this->hour} tbl_global_report 没有数据删除" . self::PHP_EOL;
			return;
		}
		echo "日期: {$this->date} 小时: {$this->hour} tbl_global_report 删除成功" . self::PHP_EOL;
	}

}