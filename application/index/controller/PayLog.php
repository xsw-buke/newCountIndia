<?php
/**
 * 充值报表
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/6/19
 * Time: 18:22
 */

namespace app\index\controller;


use think\Db;
use think\Exception;
use think\Request;


class PayLog extends TaskBase
{

	private $db;
	private $date;
	private $date_int;
	private $hour;
	private $dateWhereBetween;

	private $channelList;
	const STATUS_END = 3;//已支付
	const STATUS_START = 1;//下单
	const TYPE_ORDER = 1;//下单
	const TYPE_PAY = 2;//已经付款

	function __construct()
	{
		try {
			//中心报表库连接
			$this->channelList = Db::connect('database.portal')
				->table('tp_channel')
				->field('channel_id')
				->distinct(TRUE)
				->select();
			$this->channelList = array_column($this->channelList, 'channel_id');

			$this->db = Db::connect('database.center');
		} catch (Exception $e) {
		}

	}


	//确定时间范围 轮训
	function countPayLog(Request $request)
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
			//删除这一个小时的支付统计
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


	private function _deleteLog()
	{
		echo "日期:{$this->date};小时:{$this->hour} - 删除旧数据开始......" . PHP_EOL;

		$map    = [
			'date_time' => "{$this->date} {$this->hour}:00:00",
		];
		//删除报表
		$result = $this->db->table('tbl_pay_log')
			->where($map)
			->delete();
		if ($result == FALSE) {
			echo "日期: {$this->date} 小时: {$this->hour} tbl_pay_log 没有数据删除" . self::PHP_EOL;
			return;
		}
		echo "日期: {$this->date} 小时: {$this->hour} tbl_pay_log 删除成功" . self::PHP_EOL;
	}


	// 根据类型生成报表
	private function _generateLog()
	{
		echo "日期:{$this->date} 小时:{$this->hour} - 生成支付小时日志开始...... " . self::PHP_EOL;
		//遍历渠道 统计各个渠道的小时数据
		foreach ($this->channelList as $channelId) {
			$this->generateReport($channelId);
		}
		echo "日期:{$this->date} 小时:{$this->hour} - 生成支付小时日志结束 " . self::PHP_EOL;
	}

	/**
	 * 获取上一个小时 获取时间段有充值订单的会员
	 *
	 * @param $channelId
	 *
	 * @return array|bool
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\ModelNotFoundException
	 * @throws \think\exception\DbException
	 */
	private function getRoleList($channelId)
	{
		//渠道ID  前期玩家不用分页
		$list = $this->db->table('tbl_pay_order')
			->field(['role_id'])
			->where(['channel_id' => $channelId])
			->whereBetween('update_time', $this->dateWhereBetween)
			->distinct('true')
			->select();

		if (empty($list)) return FALSE;
		return array_column($list, 'role_id');

	}

	private function generateReport($channelId)
	{
		// 获取时间段有充值订单的会员
		$roleList = $this->getRoleList($channelId);
		//这个渠道这段时间没有人玩
		if (empty($roleList)) return;
		//遍历玩家列表  得到该玩家在上个小时 这个游戏  统计的数据
		foreach ($roleList as $roleId) {
			$map = [
				'role_id' => $roleId,
				'type' => self::TYPE_ORDER,//下单
			];
			$add = [
				'date'       => $this->date,
				'role_id'    => $roleId,
				'date_time'  => $this->date . " {$this->hour}:00:00",
				'channel_id' => $channelId,
			];

			//TODO::总订单笔数 总金额 查日志   有效笔数 有效金额


			//总共下了多少订单
			$count = $this->db->table('tbl_pay_order_log')
				->field([
					'count(*) as pay_count', //总订单笔数
					'sum(price) as pay_money_sum', //总订单金额
				])
				->where($map)
				->whereBetween('date_time', $this->dateWhereBetween)
				->find();
			if ($count['pay_count'] == 0){
				$count['pay_money_sum'] = 0;
			}

			//合并两个数组
			$add = array_merge($add, $count);

			//这个时间段付款的订单
			$map['type'] = self::TYPE_PAY;
			$count         = $this->db->table('tbl_pay_order_log')
				->field([
					'sum(price) as valid_pay_money_sum', //总付款金额
					'count(*) as valid_pay_count' //付款订单笔数
				])
				->where($map)
				->whereBetween('date_time', $this->dateWhereBetween)
				->find();
			if ($count['valid_pay_count'] == 0){
				$count['valid_pay_money_sum'] = 0;
			}

			//合并两个数组
			$add = array_merge($add, $count);

			$result = $this->db->table('tbl_pay_log')
				->insert($add);

			if ($result == FALSE) {
				echo "玩家ID:{$roleId} 充值日志入库失败" . PHP_EOL;
			}

			// 输出日志
			$msg = '时间: ' . $this->date . '小时:' . $this->hour;
			$msg .= ' - 会员: ' . $roleId;
			$msg .= ' - 支付总笔数: ' . $add['pay_count'];
			$msg .= ' - 有效笔数: ' . $add['valid_pay_count'];
			$msg .= ' - 总金额: ' . $add['pay_money_sum'];

			echo $msg . PHP_EOL;
		}
	}
}