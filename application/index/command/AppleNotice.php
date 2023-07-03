<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/12/24
 * Time: 16:18
 */


namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class AppleNotice extends Command
{

	private $db;
	//取消类型
	const NOTICE_PAY_STATUS_FALSE = [
		'CANCEL',//订阅取消
		'REFUND',//退款
		'RENEWAL',//2021 将要废弃的 续费
	];

	//续订类型
	const NOTIFICATION_TYPE_RENEW = [
		'DID_RENEW',
		'INTERACTIVE_RENEWAL',
		'RENEWAL',//2021 将要废弃的 续费
		'DID_RECOVER'
	];
	const PAY_STATUS_TRUE_TIMEOUT = 3;//过期
	const PAY_STATUS_TRUE_PAYMENT = 1;
	const STATUS_GRANT = 3; //已发放
	const STATUS_UPDATE = 5; //更新订阅
	const ORDER_TYPE_SUBSCRIBE = 2; //订阅购买
	const  PAYMENT_STATE_PAY = 1; //付费状态
	const  PAYMENT_STATE_TRIAL = 2; //试用状态

	//INITIAL_BUY首次购买
	const NOTIFICATION_TYPE_ARRAY = [
		'DID_RENEW',// 沙盒续订
		'INTERACTIVE_RENEWAL',//续订
		'DID_FAIL_TO_RENEW',//进入宽限期
		'CANCEL',//订阅取消
		'DID_RECOVER',//App Store通过计费重试恢复了已过期的订阅
		'REFUND',//退款
		'RENEWAL',//2021 将要废弃的 续费
	];

	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'AppleNotice' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '苹果支付日志转化处理' )/**
		 * 定义形参
		 * Argument::REQUIRED = 1; 必选
		 * Argument::OPTIONAL = 2;  可选
		 */
		     ->addArgument ( 'cronTab', Argument::OPTIONAL, '是否是定时任务' )
		     ->addArgument ( 'start_date', Argument::OPTIONAL, '开始日期:格式2012-12-12' )
		     ->addArgument ( 'end_date', Argument::OPTIONAL, '结束日期' )// 运行 "php think list" 时的简短描述
		     ->setHelp ( "当不给开始结束时间,默认只跑当天;\n只给了开始时间,跑指定的一天;\n给了开始和结束时间,跑指定范围内所有数据;" );
	}

	protected function execute ( Input $input, Output $output )
	{
		//数据库连接
		$this->db = Db::connect ( 'database.pay' );
		$noticeList = $this->db->table ( 'tbl_apple_notification_log' )
		                       ->select ();
		foreach ( $noticeList as $notice ) {
			$msg               = '开始 : ';
			$notification_type = $notice[ 'notification_type' ];
			$time              = $notice[ 'date_time' ]; //接到通知时间 我方服务器
			$query_info        = json_decode ( $notice[ 'query_info' ], TRUE );

			//获取订单
			$order_info = $this->db->table ( 'tbl_pay_order_apple' )
			                       ->where ( [ 'pay_order' => $query_info[ 'latest_receipt_info' ][ 0 ][ 'original_transaction_id' ] ] )
			                       ->order ( 'id desc' )
			                       ->find ();
			if ( !empty( $order_info ) ) {
				$msg .= '获取成功';
			}
			$update = [
				'update_time'         => $time,
				//更新四个字段 + 1个附加字段
				'end_time'            => $query_info[ 'latest_receipt_info' ][ 0 ][ 'expires_date_ms' ],//结束时间 时间戳毫秒
				'subscription_status' => $notification_type,
			];
			//如果此次是

			//记录
			$orderLogData = [
				'order'               => $order_info[ 'order' ],
				'message'             => '已更新',
				'date_time'           => $time,
				//如果是续费 日志类型变为发放 不是则是更新
				'type'                => 5,//更新 TODO::
				'role_id'             => $order_info[ 'role_id' ],
				'price'               => $order_info[ 'price' ],
				'sid'                 => $order_info[ 'sid' ],
				'pay_order'           => $order_info[ 'pay_order' ],
				'order_type'          => 2,
				'update_info'         => json_encode ( $update ),
				'pay_status'          => 0,//默认不是付款信息
				'subscription_status' => $notification_type,
				'end_time'            => $query_info[ 'latest_receipt_info' ][ 0 ][ 'expires_date_ms' ],//结束时间 时间戳毫秒
			];
			//如果是续费
			if ( in_array ( $notification_type, self::NOTIFICATION_TYPE_RENEW ) ) {
				//日志 真实付款状态 方便做报表
				$orderLogData[ 'pay_status' ] = self::PAY_STATUS_TRUE_PAYMENT;
				$update[ 'pay_status' ]       = self::PAY_STATUS_TRUE_PAYMENT;
				$update[ 'payment_state' ]       = self::PAYMENT_STATE_PAY;
			}
			//更新订单
			$updateResult = $this->db->table ( 'tbl_pay_order_apple' )
			                         ->where ( [ 'order' => $order_info[ 'order' ] ] )
			                         ->update ( $update );

			if ( !empty( $updateResult ) ) {
				$msg .= '--更新订单成功';
				$result = $this->db->table ( 'tbl_pay_order_log_apple' )
				                   ->insert ( $orderLogData );


				if ( !empty( $result ) ) {
					$msg .= '--插入日志成功';
				}
			}
			//订单操作记录
			$output->writeln ( $msg );
		}


		die;


	}

	function msecdate ( $time )
	{
		$tag  = 'Y-m-d H:i:s';
		$a    = substr ( $time, 0, 10 );
		$b    = substr ( $time, 10 );
		$date = date ( $tag, $a ) . '.' . $b;
		return $date;
	}
}