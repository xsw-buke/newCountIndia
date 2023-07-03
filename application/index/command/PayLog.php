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

class PayLog extends Command
{
	const STATUS_END = 3;//已支付
	const STATUS_UPDATE = 5;//更新
	const STATUS_START = 1;//下单
	const TYPE_ORDER = 1;//下单
	const ORDER_TYPE_ORDINARY = 1;//普通订单
	const ORDER_TYPE_SUBSCRIBE = 2;//订阅订单
	const NOTIFICATION_TYPE_TRUE = 2;//续费订阅通知

	const TYPE_APPLE = 2;//报表类型苹果
	const TYPE_GOOGLE = 1; //报表类型谷歌
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
		$this->setName ( 'PayLog' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
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


		//数据库连接初始化
		$this->db_report = Db::connect ( 'database.report' );
		$this->db_center = Db::connect ( 'database.center' );

		//中心报表库连接
		$this->channelList = Db::connect ( 'database.portal' )
		                       ->table ( 'tp_channel' )
//		                       ->field ( 'channel_id' )
		                       ->distinct ( TRUE )
		                       ->select ();

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
	}

	private function _generateLog ( Output $output )
	{

		/*$output->writeln ( "日期:{$this->date} 小时:{$this->hour} - 生成支付小时日志开始...... " );
		//遍历渠道 统计各个渠道的小时数据
		foreach ( $this->channelList as $channelId ) {
			//生成谷歌报表
			$this->generateReport ( $channelId, $output );
			//生成苹果报表
			$this->generateAppleReport ( $channelId, $output );
		}*/

		$output->writeln ( "日期:{$this->date} 小时:{$this->hour} - 生成支付小时日志结束 " );

		foreach ( $this->channelList as $channel ) {

			//谷歌
			if ( $channel[ 'pay_merchant_id' ] == 1 ) {
				//生成谷歌报表 普通订单
				$this->generateReport ( $channel[ 'channel_id' ], $output );
			}
			//苹果
			elseif ( $channel[ 'pay_merchant_id' ] == 2 ) {

				//生成苹果报表 普通订单
				$this->generateAppleReport ( $channel[ 'channel_id' ], $output );
				//订阅
			}
		}

		$output->writeln ( "日期:{$this->date} 小时:{$this->hour} - 生成支付小时日志结束 " );
	}

	/**
	 * 获取上一个小时 获取时间段有充值订单的会员
	 *
	 * @param $channelId
	 *
	 * @return array|bool
	 */
	private function getRoleList ( $channelId )
	{
		//渠道ID  前期玩家不用分页
		$list = $this->db_center->table ( 'tbl_pay_order' )
		                        ->field ( [ 'role_id' ] )
		                        ->where ( [ 'channel_id' => $channelId ] )
		                        ->whereBetween ( 'update_time', $this->dateWhereBetween )
		                        ->distinct ( 'true' )
		                        ->select ();

		if ( empty( $list ) ) return FALSE;
		return array_column ( $list, 'role_id' );

	}


	/**
	 * 获取上一个小时 获取时间段有充值订单的会员
	 *
	 * @param $channelId
	 *
	 * @return array|bool
	 */
	private function getAppleRoleList ( $channelId )
	{
		//渠道ID  前期玩家不用分页
		$list = $this->db_center->table ( 'tbl_pay_order_apple' )
		                        ->field ( [ 'role_id' ] )
		                        ->where ( [ 'channel_id' => $channelId ] )
		                        ->whereBetween ( 'update_time', $this->dateWhereBetween )
		                        ->distinct ( 'true' )
		                        ->select ();

		if ( empty( $list ) ) return FALSE;
		return array_column ( $list, 'role_id' );

	}

	//谷歌报表
	private function generateReport ( $channelId, Output $output )
	{
		// 获取时间段有充值订单的会员
		$roleList = $this->getRoleList ( $channelId );
		//这个渠道这段时间没有人玩
		if ( empty( $roleList ) ) return;
		//遍历玩家列表  得到该玩家在上个小时 这个游戏  统计的数据
		foreach ( $roleList as $roleId ) {
			$map = [
				'role_id'    => $roleId,
				'type'       => self::TYPE_ORDER,//下单
				'order_type' => 1,
			];
			$add = [
				'date'       => $this->date,
				'role_id'    => $roleId,
				'date_time'  => $this->date . " {$this->hour}:00:00",
				'channel_id' => $channelId,
				'type'       => self::TYPE_GOOGLE,
			];

			//总共下了多少订单
			$count = $this->db_center->table ( 'tbl_pay_order_log' )
			                         ->field ( [
				                         'count(*) as pay_count', //总订单笔数
				                         'sum(price) as pay_money_sum', //总订单金额
			                         ] )
			                         ->where ( $map )
			                         ->whereBetween ( 'date_time', $this->dateWhereBetween )
			                         ->find ();
			if ( $count[ 'pay_count' ] == 0 ) {
				$count[ 'pay_money_sum' ] = 0;
			}

			//合并两个数组
			$add = array_merge ( $add, $count );
			//这个时间段付款的订单  大于等于发放
			$map[ 'type' ] = [ 'in', [ self::STATUS_END, self::STATUS_UPDATE ] ];
			//普通订单 不区分订单类型
			//			$map[ 'order_type' ] = self::ORDER_TYPE_ORDINARY;
			$count = $this->db_center->table ( 'tbl_pay_order_log' )
			                         ->field ( [
				                         'sum(price) as valid_pay_money_sum', //总付款有效金额
				                         'count(*) as valid_pay_count' //付款订单有效笔数
			                         ] )
			                         ->where ( $map )
			                         ->whereBetween ( 'date_time', $this->dateWhereBetween )
			                         ->find ();
			if ( $count[ 'valid_pay_count' ] == 0 ) {
				$count[ 'valid_pay_money_sum' ] = 0;
			}

			//合并两个数组
			$add = array_merge ( $add, $count );

			$result = $this->db_report->table ( 'tbl_pay_log' )
			                          ->insert ( $add );

			if ( $result == FALSE ) {
				$output->writeln ( "玩家ID:{$roleId} 充值日志入库失败" );
			}
			// 输出日志
			$msg = '谷歌报表:';
			$msg .= '时间: ' . $this->date . '小时:' . $this->hour;
			$msg .= ' - 会员: ' . $roleId;
			$msg .= ' - 支付总笔数: ' . $add[ 'pay_count' ];
			$msg .= ' - 总金额: ' . $add[ 'pay_money_sum' ];
			$msg .= ' - 有效笔数: ' . $add[ 'valid_pay_count' ];
			$msg .= ' - 有效总金额: ' . $add[ 'valid_pay_money_sum' ];

			$output->writeln ( $msg );
		}
	}


	private function generateAppleReport ( $channelId, Output $output )
	{
		// 获取时间段有充值订单的会员
		$roleList = $this->getAppleRoleList ( $channelId );
		//这个渠道这段时间没有人玩
		if ( empty( $roleList ) ) return;
		//遍历玩家列表  得到该玩家在上个小时 这个游戏  统计的数据
		foreach ( $roleList as $roleId ) {
			$map = [
				'role_id'    => $roleId,
				'type'       => self::TYPE_ORDER,//下单
				'order_type' => 1,
			];
			$add = [
				'date'       => $this->date,
				'role_id'    => $roleId,
				'date_time'  => $this->date . " {$this->hour}:00:00",
				'channel_id' => $channelId,
				'type'       => self::TYPE_APPLE,
			];

			//总共下了多少订单
			$count = $this->db_center->table ( 'tbl_pay_order_log_apple' )
			                         ->field ( [
				                         'count(*) as pay_count', //总订单笔数
				                         'sum(price) as pay_money_sum', //总订单金额
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
			$map[ 'type' ] = [ 'in', [ self::STATUS_END, self::STATUS_UPDATE ] ];
			//普通订单 不区分类型
			//			$map[ 'order_type' ] = self::ORDER_TYPE_ORDINARY;
			$count = $this->db_center->table ( 'tbl_pay_order_log_apple' )
			                         ->field ( [
				                         'sum(price) as valid_pay_money_sum', //总付款有效金额
				                         'count(*) as valid_pay_count' //付款订单有效笔数
			                         ] )
			                         ->where ( $map )
			                         ->whereBetween ( 'date_time', $this->dateWhereBetween )
			                         ->find ();
			if ( $count[ 'valid_pay_count' ] == 0 ) {
				$count[ 'valid_pay_money_sum' ] = 0;
			}

			//合并两个数组
			$add = array_merge ( $add, $count );

			$result = $this->db_report->table ( 'tbl_pay_log' )
			                          ->insert ( $add );

			if ( $result == FALSE ) {
				$output->writeln ( "玩家ID:{$roleId} 充值日志入库失败" );
			}
			// 输出日志
			$msg = '苹果报表:';
			$msg .= '时间: ' . $this->date . '小时:' . $this->hour;
			$msg .= ' - 会员: ' . $roleId;
			$msg .= ' - 支付总笔数: ' . $add[ 'pay_count' ];
			$msg .= ' - 总金额: ' . $add[ 'pay_money_sum' ];
			$msg .= ' - 有效笔数: ' . $add[ 'valid_pay_count' ];
			$msg .= ' - 有效总金额: ' . $add[ 'valid_pay_money_sum' ];

			$output->writeln ( $msg );
		}
	}


	private function _deleteLog ( Output $output )
	{
		$output->writeln ( "日期:{$this->date};小时:{$this->hour} - 删除旧数据开始......" );

		$map = [
			'date_time' => "{$this->date} {$this->hour}:00:00",
		];
		//删除报表
		$result = $this->db_report->table ( 'tbl_pay_log' )
		                          ->where ( $map )
		                          ->delete ();
		if ( $result == FALSE ) {
			$output->writeln ( "日期: {$this->date} 小时: {$this->hour} tbl_pay_log 没有数据删除" );
			return;
		}
		$output->writeln ( "日期: {$this->date} 小时: {$this->hour} tbl_pay_log 删除成功" );
	}

}