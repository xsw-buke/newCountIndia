<?php
/**
 * 支付报表
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

class PayReport extends Command
{
	const STATUS_END = 3;//已支付
	const STATUS_UPDATE = 5;//更新
	const STATUS_START = 1;//下单
	const TYPE_ORDER = 1;//下单
	const ORDER_TYPE_ORDINARY = 1;//普通订单
	const ORDER_TYPE_SUBSCRIBE = 2;//订阅订单
	const NOTIFICATION_TYPE_TRUE = 2;//续费订阅通知

	const TYPE_APPLE = 2;//报表类型苹果
	const TYPE_GOOGLE = 1;//报表类型谷歌
	const TYPE_HUAWEI = 4;//报表类型谷歌
	const PAY_STATUS_TRUE = 1; //支付状态1
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
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'PayReport' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '支付小时报表' )/**
		 * 定义形参
		 * Argument::REQUIRED = 1; 必选
		 * Argument::OPTIONAL = 2;  可选
		 */
		     ->addArgument ( 'cronTab', Argument::OPTIONAL, '是否是定时任务' )
		     ->addArgument ( 'start_date', Argument::OPTIONAL, '开始日期:格式2012-12-12' )
		     ->addArgument ( 'duration_hour', Argument::OPTIONAL, '持续小时' )// 运行 "php think list" 时的简短描述
		     ->setHelp ( "暂无" );
	}


	///	 *     * 生成所需的数据表
	//	 * php think test  调用的方法
	//	 *
	//	 * @param Input  $input  接收参数对象
	//	 * @param Output $output 操作命令

	protected function execute ( Input $input, Output $output )
	{
		$phpStartTime = time ();

		//数据库连接初始化
		$this->db_report = Db::connect ( 'database.report' );
		$this->db_center = Db::connect ( 'database.center' );

		//中心报表库连接
		$this->channelList = Db::connect ( 'database.portal' )
		                       ->table ( 'tp_channel' )
		                       ->field ( [ 'channel_id', 'pay_merchant_id' ] )
		                       ->distinct ( TRUE )
		                       ->select ();

		//		dump($this->channelList);die;
		//是定时任务
		if ( !empty( $input->getArgument ( 'cronTab' ) ) ) {
			$time = strtotime ( '-1 hour' );
			//时间
			$this->date = date ( 'Y-m-d', $time );
			//表后数字
			$this->dateYmd = date ( 'Ymd', $time );
			//目前小时
			$this->hour                  = date ( 'H', $time );
			$this->dateWhereBetween[ 0 ] = $this->date . " {$this->hour}:00:00";
			$this->dateWhereBetween[ 1 ] = $this->date . " {$this->hour}:59:59";
			//删除这一个小时的投注统计
			$this->_deleteLog ( $output );
			//定时任务生成上一个小时入库
			$this->_generateLog ( $output );

			$second = time () - $phpStartTime;
			$output->writeln ( "开始时间" . date ( 'Y-m-d H:i:s', $phpStartTime ) . " ,结束时间" . date ( 'Y-m-d H:i:s' ) . ", 总计费时{$second}秒" );

			exit;
		}
		//不是定时任务
		//报表重跑  获得两个参数 ,一个为开始时间
		$startDate = $input->getArgument ( 'start_date' );
		//持续小时
		$durationHour = $input->getArgument ( 'duration_hour' );

		$time = strtotime ( $startDate );
		//重跑$hour个小时 的报表
		for ( $i = $durationHour; $i > 0; $i-- ) {
			//时间
			$this->date = date ( 'Y-m-d', $time );
			//表后数字
			$this->dateYmd = date ( 'Ymd', $time );
			//目前小时
			$this->hour                  = date ( 'H', $time );
			$this->dateWhereBetween[ 0 ] = $this->date . " {$this->hour}:00:00";
			$this->dateWhereBetween[ 1 ] = $this->date . " {$this->hour}:59:59";


			//删除这一个小时的投注统计
			$this->_deleteLog ( $output );


			//定时任务生成上一个小时入库
			$this->_generateLog ( $output );
			//时间加3600秒
			$time += 3600;
		}
		$second = time () - $phpStartTime;
		$output->writeln ( "开始时间" . date ( 'Y-m-d H:i:s', $phpStartTime ) . " ,结束时间" . date ( 'Y-m-d H:i:s' ) . ", 总计费时{$second}秒" );

	}

	private function _generateLog ( Output $output )
	{
		$output->writeln ( "日期:{$this->date} 小时:{$this->hour} - 生成支付小时日志开始...... " );
		//遍历渠道 统计各个渠道的小时数据
		foreach ( $this->channelList as $channel ) {
			//谷歌
			if ( $channel[ 'pay_merchant_id' ] == 1 ) {
				//生成谷歌报表 普通订单
				$this->generateReport ( $channel[ 'channel_id' ], self::ORDER_TYPE_ORDINARY, $output );
				//订阅
				$this->generateReport ( $channel[ 'channel_id' ], self::ORDER_TYPE_SUBSCRIBE, $output );
			}
			//苹果
			elseif ( $channel[ 'pay_merchant_id' ] == 2 || $channel[ 'pay_merchant_id' ] == 3) {

				//生成苹果报表 普通订单
				$this->generateAppleReport ( $channel[ 'channel_id' ], self::ORDER_TYPE_ORDINARY, $output );
				//订阅
				$this->generateAppleReport ( $channel[ 'channel_id' ], self::ORDER_TYPE_SUBSCRIBE, $output );
			}	//华为
			elseif ( $channel[ 'pay_merchant_id' ] == 2 || $channel[ 'pay_merchant_id' ] == 4) {

				//生成苹果报表 普通订单
				$this->generateHuaweiReport ( $channel[ 'channel_id' ], self::ORDER_TYPE_ORDINARY, $output );
				//订阅
				$this->generateHuaweiReport ( $channel[ 'channel_id' ], self::ORDER_TYPE_SUBSCRIBE, $output );
			}
		}

		$output->writeln ( "日期:{$this->date} 小时:{$this->hour} - 生成支付小时日志结束 " );
	}


	//谷歌报表
	private function generateReport ( $channelId, $order_type, Output $output )
	{

		$map = [
			'order_type' => $order_type,
			'channel_id' => $channelId,
			'type'       => self::TYPE_ORDER,//下单
		];
		$add = [
			'date'       => $this->date, //日期
			'date_time'  => $this->date . " {$this->hour}:00:00", //小时
			'channel_id' => $channelId, //渠道
			'type'       => self::TYPE_GOOGLE, //平台
			'order_type' => $order_type, //订单类型
		];

		//总共下了多少订单
		$count = $this->db_center->table ( 'tbl_pay_order_log' )
		                         ->field ( [
			                         'count(*) as pay_count', //总订单笔数
			                         'sum(price) as pay_money_sum', //总订单金额
			                         'count(distinct role_id) as role_count',
		                         ] )
		                         ->where ( $map )
		                         ->whereBetween ( 'date_time', $this->dateWhereBetween )
		                         ->find ();
		if ( $count[ 'pay_count' ] == 0 ) {
			$count[ 'pay_money_sum' ] = 0;
		}

		//合并两个数组
		$add = array_merge ( $add, $count );
		//这个时间段付款的订单
		$map[ 'pay_status' ] = self::PAY_STATUS_TRUE;
		unset( $map[ 'type' ] );
		//普通订单 不区分订单类型
		//			$map[ 'order_type' ] = self::ORDER_TYPE_ORDINARY;
		$count = $this->db_center->table ( 'tbl_pay_order_log' )
		                         ->field ( [
			                         'sum(price) as valid_pay_money_sum', //总付款有效金额
			                         'count(*) as valid_pay_count', //付款订单有效笔数
			                         'count(distinct role_id) as valid_role_count',
		                         ] )
		                         ->where ( $map )
		                         ->whereBetween ( 'date_time', $this->dateWhereBetween )
		                         ->find ();
		if ( $count[ 'valid_pay_count' ] == 0 ) {
			$count[ 'valid_pay_money_sum' ] = 0;
		}

		//假如没有数据
		if ( $count[ 'valid_pay_count' ] == 0 && $add[ 'pay_count' ] == 0 ) {
			return FALSE;
		}

		//合并两个数组
		$add = array_merge ( $add, $count );

		$result = $this->db_report->table ( 'tbl_pay_report' )
		                          ->insert ( $add );

		if ( $result == FALSE ) {
			$output->writeln ( "{$this->date} 充值日志入库失败" );
		}
		// 输出日志
		$msg = '谷歌报表:';
		$msg .= '时间: ' . $this->date . '小时:' . $this->hour;
		$msg .= ' - 渠道: ' . $add[ 'channel_id' ];
		$msg .= ' - 支付总笔数: ' . $add[ 'pay_count' ];
		$msg .= ' - 总金额: ' . $add[ 'pay_money_sum' ];
		$msg .= ' - 有效笔数: ' . $add[ 'valid_pay_count' ];
		$msg .= ' - 有效总金额: ' . $add[ 'valid_pay_money_sum' ];

		$output->writeln ( $msg );
		return  TRUE;
	}


	private function generateAppleReport ( $channelId, $order_type, Output $output )
	{

		//遍历玩家列表  得到该玩家在上个小时 这个游戏  统计的数据
		$map = [
			'type'       => self::TYPE_ORDER,//下单
			'order_type' => $order_type,
			'channel_id' => $channelId,
		];
		$add = [
			'date'       => $this->date,
			'date_time'  => $this->date . " {$this->hour}:00:00",
			'channel_id' => $channelId,
			'type'       => self::TYPE_APPLE,
			'order_type' => $order_type,
		];

		//总共下了多少订单
		$count = $this->db_center->table ( 'tbl_pay_order_log_apple' )
		                         ->field ( [
			                         'count(*) as pay_count', //总订单笔数
			                         'sum(price) as pay_money_sum', //总订单金额
			                         'count(distinct role_id) as role_count',
		                         ] )
		                         ->where ( $map )
		                         ->whereBetween ( 'date_time', $this->dateWhereBetween )
		                         ->find ();
		if ( $count[ 'pay_count' ] == 0 ) {
			$count[ 'pay_money_sum' ] = 0;
		}
		unset( $map[ 'type' ] );
		//合并两个数组
		$add = array_merge ( $add, $count );
		//这个时间段付款的订单
		$map[ 'pay_status' ] = self::PAY_STATUS_TRUE;
		//普通订单 不区分类型
		//			$map[ 'order_type' ] = self::ORDER_TYPE_ORDINARY;
		$count = $this->db_center->table ( 'tbl_pay_order_log_apple' )
		                         ->field ( [
			                         'sum(price) as valid_pay_money_sum', //总付款有效金额
			                         'count(*) as valid_pay_count',//付款订单有效笔数
			                         'count(distinct role_id) as valid_role_count',
		                         ] )
		                         ->where ( $map )
		                         ->whereBetween ( 'date_time', $this->dateWhereBetween )
		                         ->find ();



		if ( $count[ 'valid_pay_count' ] == 0 ) {
			$count[ 'valid_pay_money_sum' ] = 0;
		}


		//假如没有数据
		if ( $count[ 'valid_pay_count' ] == 0 && $add[ 'pay_count' ] == 0 ) {
			return FALSE;
		}

		//合并两个数组
		$add = array_merge ( $add, $count );

		$result = $this->db_report->table ( 'tbl_pay_report' )
		                          ->insert ( $add );

		if ( $result == FALSE ) {
			$output->writeln ( "{$this->date} 充值日志入库失败" );
		}
		// 输出日志
		$msg = '苹果报表:';
		$msg .= '时间: ' . $this->date . '小时:' . $this->hour;
		$msg .= ' - 渠道: ' . $add[ 'channel_id' ];
		$msg .= ' - 支付总笔数: ' . $add[ 'pay_count' ];
		$msg .= ' - 总金额: ' . $add[ 'pay_money_sum' ];
		$msg .= ' - 有效笔数: ' . $add[ 'valid_pay_count' ];
		$msg .= ' - 有效总金额: ' . $add[ 'valid_pay_money_sum' ];

		$output->writeln ( $msg );
		return  TRUE;
	}

	private function generateHuaweiReport ( $channelId, $order_type, Output $output )
	{

		//遍历玩家列表  得到该玩家在上个小时 这个游戏  统计的数据
		$map = [
			'type'       => self::TYPE_ORDER,//下单
			'order_type' => $order_type,
			'channel_id' => $channelId,
		];
		$add = [
			'date'       => $this->date,
			'date_time'  => $this->date . " {$this->hour}:00:00",
			'channel_id' => $channelId,
			'type'       => self::TYPE_HUAWEI,
			'order_type' => $order_type,
		];

		//总共下了多少订单
		$count = $this->db_center->table ( 'tbl_pay_order_log_huawei' )
		                         ->field ( [
			                         'count(*) as pay_count', //总订单笔数
			                         'sum(price) as pay_money_sum', //总订单金额
			                         'count(distinct role_id) as role_count',
		                         ] )
		                         ->where ( $map )
		                         ->whereBetween ( 'date_time', $this->dateWhereBetween )
		                         ->find ();
		if ( $count[ 'pay_count' ] == 0 ) {
			$count[ 'pay_money_sum' ] = 0;
		}
		unset( $map[ 'type' ] );
		//合并两个数组
		$add = array_merge ( $add, $count );
		//这个时间段付款的订单
		$map[ 'pay_status' ] = self::PAY_STATUS_TRUE;
		//普通订单 不区分类型
		//			$map[ 'order_type' ] = self::ORDER_TYPE_ORDINARY;
		$count = $this->db_center->table ( 'tbl_pay_order_log_huawei' )
		                         ->field ( [
			                         'sum(price) as valid_pay_money_sum', //总付款有效金额
			                         'count(*) as valid_pay_count',//付款订单有效笔数
			                         'count(distinct role_id) as valid_role_count',
		                         ] )
		                         ->where ( $map )
		                         ->whereBetween ( 'date_time', $this->dateWhereBetween )
		                         ->find ();



		if ( $count[ 'valid_pay_count' ] == 0 ) {
			$count[ 'valid_pay_money_sum' ] = 0;
		}


		//假如没有数据
		if ( $count[ 'valid_pay_count' ] == 0 && $add[ 'pay_count' ] == 0 ) {
			return FALSE;
		}

		//合并两个数组
		$add = array_merge ( $add, $count );

		$result = $this->db_report->table ( 'tbl_pay_report' )
		                          ->insert ( $add );

		if ( $result == FALSE ) {
			$output->writeln ( "{$this->date} 充值日志入库失败" );
		}
		// 输出日志
		$msg = '华为报表:';
		$msg .= '时间: ' . $this->date . '小时:' . $this->hour;
		$msg .= ' - 渠道: ' . $add[ 'channel_id' ];
		$msg .= ' - 支付总笔数: ' . $add[ 'pay_count' ];
		$msg .= ' - 总金额: ' . $add[ 'pay_money_sum' ];
		$msg .= ' - 有效笔数: ' . $add[ 'valid_pay_count' ];
		$msg .= ' - 有效总金额: ' . $add[ 'valid_pay_money_sum' ];

		$output->writeln ( $msg );
		return  TRUE;
	}


	private function _deleteLog ( Output $output )
	{
		$output->writeln ( "日期:{$this->date};小时:{$this->hour} - 删除旧数据开始......" );

		$map = [
			'date_time' => "{$this->date} {$this->hour}:00:00",
		];
		//删除报表
		$result = $this->db_report->table ( 'tbl_pay_report' )
		                          ->where ( $map )
		                          ->delete ();
		if ( $result == FALSE ) {
			$output->writeln ( "日期: {$this->date} 小时: {$this->hour} tbl_pay_report 没有数据删除" );
			return;
		}
		$output->writeln ( "日期: {$this->date} 小时: {$this->hour} tbl_pay_report 删除成功" );
	}

}