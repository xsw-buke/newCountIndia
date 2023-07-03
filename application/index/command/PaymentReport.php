<?php
/**
 * 支付报表新
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/9/7
 * Time: 22:46
 */


namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class PaymentReport extends Command
{
	const STATUS_END = 3;//已支付
	const STATUS_START = 1;//下单
	const TYPE_ORDER = 1;//下单
	const ORDER_TYPE_ORDINARY = 1;//普通订单
	const ORDER_TYPE_SUBSCRIBE = 2;//订阅订单

	const NOTIFICATION_TYPE_RENEW = 2; //通知 续费
	const PAY_STATUS_TRUE = 1;//订阅真实支付状态 是

	//日期
	//日期数字 找表用的
	private $dateYmd;
	private $db_report;
	private $db_center;
	private $date;
	private $hour;
	private $dateWhereBetween;
	private $channelList;

	/**
	 * 配置方法
	 */
	protected function configure()
	{
		//给php think CreateTable 注册备注
		$this->setName('PaymentReport')// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		->setDescription('支付小时报表')/**
		 * 定义形参
		 * Argument::REQUIRED = 1; 必选
		 * Argument::OPTIONAL = 2;  可选
		 */
		->addArgument('cronTab', Argument::OPTIONAL, '是否是定时任务')
			->addArgument('start_date', Argument::OPTIONAL, '开始日期:格式2012-12-12')
			->addArgument('duration_hour', Argument::OPTIONAL, '持续小时')// 运行 "php think list" 时的简短描述
			->setHelp("暂无");
	}

	///	 *     * 生成所需的数据表
	//	 * php think test  调用的方法
	//	 *
	//	 * @param Input  $input  接收参数对象
	//	 * @param Output $output 操作命令

	protected function execute(Input $input, Output $output)
	{


		//数据库连接初始化
		$this->db_report = Db::connect('database.report');
		$this->db_center = Db::connect('database.center');

		//中心报表库连接
		$channelList       = Db::connect('database.portal')
			->table('tp_channel')
			->field('channel_id')
			->distinct(TRUE)
			->select();
		$this->channelList = array_column($channelList, 'channel_id');

		//是定时任务
		if (!empty($input->getArgument('cronTab'))) {
			$time = strtotime('-1 hour');
			//时间
			$this->date = date('Y-m-d', $time);
			//表后数字
			$this->dateYmd = date('Ymd', $time);
			//目前小时
			$this->hour                = date('H', $time);
			$this->dateWhereBetween[0] = $this->date . " {$this->hour}:00:00";
			$this->dateWhereBetween[1] = $this->date . " {$this->hour}:59:59";
			//干第一件事 刷新用户的
			$this->_refresh_subscription_log($output);
			//删除这一个小时的投注统计
			$this->_deleteLog($output);
			//定时任务生成上一个小时入库
			$this->_generateLog($output);
			exit;
		}
		//不是定时任务
		//报表重跑  获得两个参数 ,一个为开始时间
		$startDate = $input->getArgument('start_date');
		//持续小时
		$durationHour = $input->getArgument('duration_hour');

		$time = strtotime($startDate);
		//重跑$hour个小时 的报表
		for ($i = $durationHour; $i > 0; $i--) {
			//时间
			$this->date = date('Y-m-d', $time);
			//表后数字
			$this->dateYmd = date('Ymd', $time);
			//目前小时
			$this->hour                = date('H', $time);
			$this->dateWhereBetween[0] = $this->date . " {$this->hour}:00:00";
			$this->dateWhereBetween[1] = $this->date . " {$this->hour}:59:59";
			//干第一件事 刷新用户的
			$this->_refresh_subscription_log($output);
			//删除这一个小时的投注统计
			$this->_deleteLog($output);
			//定时任务生成上一个小时入库
			$this->_generateLog($output);
			//时间加3600秒
			$time += 3600;
		}
	}

	private function _generateLog(Output $output)
	{

		$output->writeln("日期:{$this->date} 小时:{$this->hour} - 生成支付小时日志开始...... ");
		//遍历渠道 统计各个渠道的小时数据
		foreach ($this->channelList as $channelId) {
			$this->generateReport($channelId, $output);
		}

		$output->writeln("日期:{$this->date} 小时:{$this->hour} - 生成支付小时日志结束 ");
	}

	/**
	 * 获取上一个小时 获取时间段有充值订单的会员
	 *
	 * @param $channelId
	 *
	 * @return array|bool
	 */
	private function getRoleList($channelId)
	{
		//渠道ID  前期玩家不用分页
		$list = $this->db_center->table('tbl_pay_order')
			->field(['role_id'])
			->where(['channel_id' => $channelId])
			->whereBetween('update_time', $this->dateWhereBetween)
			->distinct('true')

			->select();

		if (empty($list)) return FALSE;
		return array_column($list, 'role_id');

	}

	private function generateReport($channelId, Output $output)
	{
		// 获取时间段有充值订单的会员
		$roleList = $this->getRoleList($channelId);
		//这个渠道这段时间没人充值
		if (empty($roleList)) return;
		//遍历玩家列表  得到该玩家在上个小时 这个游戏  统计的数据
		foreach ($roleList as $roleId) {
			//记录
			$add = [
				'date'       => $this->date,
				'role_id'    => $roleId,
				'date_time'  => $this->date . " {$this->hour}:00:00",
				'channel_id' => $channelId,
			];
			$map = [
				'role_id'    => $roleId,
				'type'       => self::TYPE_ORDER,//下单
				'order_type' => self::ORDER_TYPE_ORDINARY,//普通订单
			];
			//普通订单 下单
			$count = $this->db_center->table('tbl_pay_order_log')
				->field([
					'count(*) as place_order_count', //普通下单订单笔数
					'sum(price) as place_order_price_sum', //普通下单订单金额
				])
				->where($map)
				->whereBetween('date_time', $this->dateWhereBetween)
				->find();
			//如果数量为0 总金额也为0
			if ($count['place_order_count'] == 0) {
				$count['place_order_price_sum'] = 0;
			}
			//数组记录
			$add['place_order_count']     = $count['place_order_count'];
			$add['place_order_price_sum'] = $count['place_order_price_sum'];
			//这个时间段付款的订单
			$map['type'] = self::STATUS_END;
			//普通订单 付款
			$count = $this->db_center->table('tbl_pay_order_log')
				->field([
					'sum(price) as valid_place_order_price_sum', //普通订单付款有效金额
					'count(*) as valid_place_order_count' //p普通订单有效笔数
				])
				->where($map)
				->whereBetween('date_time', $this->dateWhereBetween)
				->find();
			if ($count['valid_place_order_count'] == 0) {
				$count['valid_place_order_price_sum'] = 0;
			}
			$add['valid_place_order_count']     = $count['valid_place_order_count'];  //有效普通订单总数
			$add['valid_place_order_price_sum'] = $count['valid_place_order_price_sum']; //有效普通订单总金额
			//重新复制
			$map = [
				'pay_status' => self::PAY_STATUS_TRUE,//类型订阅付款成功
				'role_id'    => $roleId,
			];
			//类型下单
			$count = $this->db_center->table('tbl_pay_order_log')
				->field([
					'count(*) as valid_subscribe_count', //订阅有效笔数
					'sum(price) as valid_subscribe_price_sum', //订阅有效金额
				])
				->where($map)
				->whereBetween('date_time', $this->dateWhereBetween)
				->find();
			//如果数量为0 总金额也为0
			if ($count['valid_subscribe_count'] == 0) {
				$count['valid_subscribe_price_sum'] = 0;
			}
			$add['valid_subscribe_count']     = $count['valid_subscribe_count'];
			$add['valid_subscribe_price_sum'] = $count['valid_subscribe_price_sum'];

			$result = $this->db_report->table('tbl_pay_log')
				->insert($add);
			if ($result == FALSE) {
				$output->writeln("玩家ID:{$roleId} 充值日志入库失败");
			}
			// 输出日志
			$msg = '插入数据:' . json_encode($add);
			$output->writeln($msg);
		}
	}

	private function _deleteLog(Output $output)
	{
		$output->writeln("日期:{$this->date};小时:{$this->hour} - 删除旧数据开始......");

		$map = [
			'date_time' => "{$this->date} {$this->hour}:00:00",
		];
		//删除报表
		$result = $this->db_report->table('tbl_pay_log')
			->where($map)
			->delete();
		if ($result == FALSE) {
			$output->writeln("日期: {$this->date} 小时: {$this->hour} tbl_pay_log 没有数据删除");
			return;
		}
		$output->writeln("日期: {$this->date} 小时: {$this->hour} tbl_pay_log 删除成功");
	}

	// 从表中获取新触发的订阅
	private function _refresh_subscription_log(Output $output)
	{
		$output->writeln("日期:{$this->date};小时:{$this->hour} - 刷新订阅缓存开始 ......");

		$map = [
			'notification_type' => self::NOTIFICATION_TYPE_RENEW,//类型订阅续费
		];
		//获取这个小时的续费订阅 TODO::暂时不做限制 一个小时能有1千条订阅 就不得了
		$renewNoticeS = $this->db_center->table('tbl_google_notification_log')
			->where($map)
			->whereBetween('event_time_millis', [
				$this->dateWhereBetween[0],
				$this->date . " 23:59:59",
			])
			->select();
		//没有续费订阅
		if (empty($renewNoticeS)) {
			return FALSE;
		}

		//遍历谷歌异步订阅得通知
		foreach ($renewNoticeS as $notice) {
			//获取订单号
			$order = json_decode($notice['query_info'], TRUE)['developerPayload'];
			//订单号异常
			if (empty($order)) {
				$output->writeln("日期:{$this->date};小时:{$this->hour} - 订单号异常 " . $notice['query_info']);
				continue;
			}
			$map = [
				'order' => $order, //订单号
				'type'  => self::STATUS_END, //类型付款
			];
			//更改订单记录 状态
			$result = $this->db_center->table('tbl_pay_order_log')
				->where($map)
				->whereBetween('date_time', $this->dateWhereBetween)
				->limit(1)//限制只修改一条
				->update(['pay_status' => self::PAY_STATUS_TRUE]);
			if (!empty($result)) {
				$output->writeln("日期:{$this->date};小时:{$this->hour} - 重复更新? ");
				continue;
			}
			$output->writeln("日期:{$this->date};小时:{$this->hour} - 刷新订单号:{$order} 的订阅日志");
		}
	}

}