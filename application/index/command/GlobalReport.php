<?php
/**
 * 全局报表
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/9/7
 * Time: 22:14
 */


namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class GlobalReport extends Command
{
	//日期
	private $date;
	private $dateYmd;
	//日期数字 找表用的
	private $hour;
	private $dateWhereBetween;


	const EID_LOGIN = 1002;

	private $db_report;
	private $db_log;
	private $db_center;

	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'GlobalReport' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '生成游戏小时报表' )/**
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
		$this->db_log    = Db::connect ( 'database.log' ); //线上用
		//		$this->db_log = Db::connect('database.center'); //阿里测试用

		//是定时任务
		if ( !empty( $input->getArgument ( 'cronTab' ) ) ) {
			//延迟时间改为2小时
			$time = strtotime ( '-2 hour' );
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
		//遍历服务器操作
		$server_list = Db::connect ( 'database.uwinslot' )
		                 ->table ( 'tp_server' )
		                 ->field ( 'id' )
		                 ->select ();
		foreach ( $server_list as $server ) {

			$output->writeln ( "日期:{$this->date} 小时:{$this->hour} - 生成全局报表日志开始...... " );
			$add = [
				'date'      => $this->date,
				'date_time' => "{$this->date} {$this->hour}:00:00",
				'sid'       => $server[ 'id' ]
			];

			//在线人数 这个范围内最多的人数 峰值 谷值 online_role_min_count
			$onlineInfo = $this->db_center->table ( 'tbl_online_info' )
			                              ->whereBetween ( 'date_time', $this->dateWhereBetween )
			                              ->where ( [ 'server_id' => $server[ 'id' ] ] )
			                              ->field ( [
				                              'max(online_num) as online_role_count',
				                              'min(online_num) as online_role_min_count'
			                              ] )
			                              ->find ();


			$add[ 'online_role_count' ]     = intval ( $onlineInfo[ 'online_role_count' ] );//峰值
			$add[ 'online_role_min_count' ] = intval ( $onlineInfo[ 'online_role_min_count' ] );//峰值

			$map = [
				'eid' => self::EID_LOGIN,
				'sid' => $server[ 'id' ]
			];
			//登陆人数
			$add[ 'login_role_count' ] = $this->db_log->table ( 'tbl_login_log' )
			                                          ->whereBetween ( 'create_time', $this->dateWhereBetween )
			                                          ->where ( $map )
			                                          ->count ( 'DISTINCT role_id' );

			//注册人数 考虑到注册不会重复 也就是不需要筛选条件 where 就不需要了
			$add[ 'register_role_count' ] = $this->db_log->table ( 'tbl_register_log' )
			                                             ->whereBetween ( 'create_time', $this->dateWhereBetween )
			                                             ->count ();

			//有效充值人数  谷歌
			$map             = [ 'type' => 3 ];//已发放
			$validOrderCount = $this->db_center->table ( 'tbl_pay_order_log' )
			                                   ->field ( [
				                                   'count(*) as valid_recharge_count', //有效订单数
				                                   'coalesce(sum(price),0) as valid_recharge_sum', //有效充值金额总计
				                                   'count(distinct role_id) as recharge_role_count'  //有效充值人数
			                                   ] )
			                                   ->whereBetween ( 'date_time', $this->dateWhereBetween )
			                                   ->where ( $map )
			                                   ->find ();

			// 下单人数 谷歌
			$map        = [ 'type' => 1 ];//下单
			$orderCount = $this->db_center->table ( 'tbl_pay_order_log' )
			                              ->field ( [
				                              'count(*) as recharge_count', //总订单数
				                              'coalesce(sum(price),0) as recharge_sum', //总订单金额
			                              ] )
			                              ->whereBetween ( 'date_time', $this->dateWhereBetween )
			                              ->where ( $map )
			                              ->find ();


			//有效充值人数  谷歌
			$map                  = [ 'type' => 3 ];//已发放
			$appleValidOrderCount = $this->db_center->table ( 'tbl_pay_order_log_apple' )
			                                        ->field ( [
				                                        'count(*) as valid_recharge_count_apple', //有效订单数
				                                        'coalesce(sum(price),0) as valid_recharge_sum_apple', //有效充值金额总计
				                                        'count(distinct role_id) as recharge_role_count_apple'  //有效充值人数
			                                        ] )
			                                        ->whereBetween ( 'date_time', $this->dateWhereBetween )
			                                        ->where ( $map )
			                                        ->find ();

			// 下单人数 谷歌
			$map             = [ 'type' => 1 ];//下单
			$appleOrderCount = $this->db_center->table ( 'tbl_pay_order_log_apple' )
			                                   ->field ( [
				                                   'count(*) as recharge_count_apple', //总订单数
				                                   'coalesce(sum(price),0) as recharge_sum_apple', //总订单金额
			                                   ] )
			                                   ->whereBetween ( 'date_time', $this->dateWhereBetween )
			                                   ->where ( $map )
			                                   ->find ();


			$add    = array_merge ( $add, $validOrderCount, $orderCount, $appleValidOrderCount, $appleOrderCount );
			$result = $this->db_report->table ( 'tbl_global_report' )
			                          ->insert ( $add );
			if ( $result == FALSE ) {
				$output->writeln ( '时间: ' . $this->date . '小时:' . $this->hour . "日志入库失败" );
			}

			// 输出日志
			$msg = '时间: ' . $this->date . '小时:' . $this->hour;
			$msg .= ' - 在线人数: ' . $add[ 'online_role_count' ];
			$msg .= ' - 登陆人数: ' . $add[ 'login_role_count' ];
			$msg .= ' - 注册人数: ' . $add[ 'register_role_count' ];
			$msg .= ' - 安卓有效订单人数: ' . $add[ 'recharge_role_count' ];
			$msg .= ' - 安卓有效订单数: ' . $add[ 'valid_recharge_count' ];
			$msg .= ' - 安卓有效订单金额: ' . $add[ 'valid_recharge_sum' ];
			$msg .= ' - 安卓订单数: ' . $add[ 'recharge_count' ];
			$msg .= ' - 安卓订单金额: ' . $add[ 'recharge_sum' ];
			$msg .= ' - 苹果有效订单人数: ' . $add[ 'recharge_role_count_apple' ];
			$msg .= ' - 苹果有效订单数: ' . $add[ 'valid_recharge_count_apple' ];
			$msg .= ' - 苹果有效订单金额: ' . $add[ 'valid_recharge_sum_apple' ];
			$msg .= ' - 苹果订单数: ' . $add[ 'recharge_count_apple' ];
			$msg .= ' - 苹果订单金额: ' . $add[ 'recharge_sum_apple' ];
			$msg .= "----------生成全局报表日志结束---------";
			$output->writeln ( $msg );
		}
	}


	private function _deleteLog ( Output $output )
	{

		$output->writeln ( "日期:{$this->date};小时:{$this->hour} - 删除旧数据开始......" );
		$map    = [
			'date_time' => "{$this->date} {$this->hour}:00:00",
		];
		$result = $this->db_report->table ( 'tbl_global_report' )
		                          ->where ( $map )
		                          ->delete ();
		if ( $result == FALSE ) {
			$output->writeln ( "日期: {$this->date} 小时: {$this->hour} tbl_global_report 没有数据删除" );
			return;
		}
		$output->writeln ( "日期: {$this->date} 小时: {$this->hour} tbl_global_report 删除成功" );
	}

}