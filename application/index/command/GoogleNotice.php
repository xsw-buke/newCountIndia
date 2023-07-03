<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/1/14
 * Time: 21:02
 */

namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class GoogleNotice extends Command
{

	const NOTIFICATION_TYPES_UPDATE = [
		1,//从帐号保留状态恢复了订阅。
		2,//续订了处于活动状态的订阅。
		4,//购买了新的订阅。
		6,//订阅已进入宽限期（    ）。
		3,//自愿或非自愿地取消了订阅。如果是自愿取消，在用户取消时发送。
		12, //订阅撤销
		10,//订阅暂停
	];
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
	];
	const PAY_STATUS_TRUE_TIMEOUT = 3;//过期
	const PAY_STATUS_TRUE_PAYMENT = 1;
	const STATUS_GRANT = 3; //已发放
	const STATUS_UPDATE = 5; //更新订阅
	const ORDER_TYPE_SUBSCRIBE = 2; //订阅购买

	const  PAYMENT_STATE_PAY = 1; //付费状态成功
	const  PAYMENT_STATE_NO = 0; //付费状态 失败

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
		$this->setName ( 'GoogleNotice' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '谷歌支付日志转化处理' )/**
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
		$this->db   = Db::connect ( 'database.pay' );
		$noticeList = $this->db->table ( 'tbl_google_notification_log' )
		                       ->select ();
		foreach ( $noticeList as $notice ) {
			$msg               = '开始 : ';
			$notification_type = $notice[ 'notification_type' ];
			$time              = $notice[ 'date_time' ]; //接到通知时间 我方服务器
			$query_info        = json_decode ( $notice[ 'query_info' ], TRUE );


			//根据事件类型 开始处理  不需要处理的状态
			if ( !in_array ( $notification_type, self::NOTIFICATION_TYPES_UPDATE ) ) {
				continue;
			}

			//获取订单
			$order_info = $this->db->table ( 'tbl_pay_order' )
			                       ->where ( [ 'order' => $query_info[ 'developerPayload' ] ] )
			                       ->order ( 'id desc' )
			                       ->find ();
			if ( !empty( $order_info ) ) {
				$msg .= '获取成功';
			}
			else {
				$msg .= '获取订单失败';
			}

			//time处理
			$update = [
				'update_time'         => date ( 'Y-m-d H:i:s', $time ),
				//更新四个字段 + 1个附加字段
				'end_time'            => $query_info[ 'expiryTimeMillis' ],
				//结束时间 时间戳毫秒
				'subscription_status' => $notification_type,
				'pay_status'          => $query_info[ 'paymentState' ],
				//订阅状态
			];
			//如果此次通知 订阅状态付费
			if ($update['pay_status'] == self::PAY_STATUS_TRUE_PAYMENT){
				$update['payment_state'] = self::PAYMENT_STATE_PAY;
			}

			//记录
			$orderLogData = [
				'order'               => $order_info[ 'order' ],
				'message'             => '已更新',
				'date_time'           => date ( 'Y-m-d H:i:s', $time ),
				//如果是续费 日志类型变为发放 不是则是更新
				'type'                => 5,//更新
				'role_id'             => $order_info[ 'role_id' ],
				'price'               => $order_info[ 'price' ],
				'sid'                 => $order_info[ 'sid' ],
				'pay_order'           => $order_info[ 'pay_order' ],
				'order_type'          => 2,
				'update_info'         => json_encode ( $update ),
				'pay_status'          => $query_info[ 'paymentState' ],
				'subscription_status' => $notification_type,
				'end_time'            => $query_info[ 'expiryTimeMillis' ],//结束时间 时间戳毫秒
			];

			//更新订单
			$updateResult = $this->db->table ( 'tbl_pay_order' )
			                         ->where ( [ 'order' => $order_info[ 'order' ] ] )
			                         ->update ( $update );

			if ( !empty( $updateResult ) ) {
				$msg    .= '--更新订单成功';
				$result = $this->db->table ( 'tbl_pay_order_log' )
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