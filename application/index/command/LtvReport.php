<?php
/**
 * LTV报表
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/9/7
 * Time: 22:38
 */

namespace app\index\command;


use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class LtvReport extends Command
{
	const PAY_STATUS_TRUE = 1; //支付状态1
	private $db_center;
	private $db_log;
	private $db_report;
	private $date;
	private $date_time;
	private $countWhereBetween;
	//昨天日期
	const CREATE_INFO_DAY = 1;
	const ROLE_PAGE_SIZE = 1000;
	//留存日期
	const KEEP_DAYS = [
		1  => 'tow_day_money',//+1次日
		2  => 'three_day_money',//三日
		3  => 'four_day_money', //4
		4  => 'five_day_money',//5
		5  => 'six_day_money',//6
		6  => 'seven_day_money',//7
		7  => 'eight_day_money',//8
		8  => 'nine_day_money',//9
		9  => 'ten_day_money',//10
		14 => 'fifteen_day_money', //+14 第十五日留存  当日是剔除的
	];


	//亿酷服务器定制报表
	const SID = 25;
	//渠道id
	const CHANNEL_ID = 25001;

	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'LtvReport' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '生成Ltv报表' )/**
		 * 定义形参
		 * Argument::REQUIRED = 1; 必选
		 * Argument::OPTIONAL = 2;  可选
		 */
		     ->addArgument ( 'cronTab', Argument::OPTIONAL, '是否是定时任务' )
		     ->addArgument ( 'start_date', Argument::OPTIONAL, '开始日期:格式2012-12-12' )
		     ->addArgument ( 'end_date', Argument::OPTIONAL, '结束日期' )// 运行 "php think list" 时的简短描述
		     ->setHelp ( "暂无" );
	}

	///	 *     * 生成所需的数据表
	//	 * php think test  调用的方法
	//	 *
	//	 * @param Input  $input  接收参数对象
	//	 * @param Output $output 操作命令

	protected function execute ( Input $input, Output $output )
	{
		$this->db_center = Db::connect ( 'database.center' );
		$this->db_log    = Db::connect ( 'database.log' );
		$this->db_report = Db::connect ( 'database.report' );
		/*	//获取现有渠道ID
			$this->channel_id_list = Db::connect ( 'database.portal' )
									   ->table ( 'tp_channel' )
									   ->field ( 'channel_id' )
									   ->distinct ( TRUE )
									   ->select ();*/
		//是定时任务
		if ( !empty( $input->getArgument ( 'cronTab' ) ) ) {
			//统计昨天 注册日
			$this->date      = date ( 'Y-m-d', strtotime ( '-1 day' ) );
			$this->date_time = strtotime ( $this->date );
			//统计的范围
			$this->countWhereBetween = [ "{$this->date} 0:00:00", "{$this->date} 23:59:59" ];


			$this->_deleteLog ( $output );

			$this->_generateLog ( $output );

			$output->writeln ( "---本次定时任务结束---" );
			exit;
		}
		//获取传输的时间
		$start_date = $input->getArgument ( 'start_date' );
		$end_date   = $input->getArgument ( 'end_date' );

		if ( empty( $start_date ) || empty( $end_date ) ) {
			$output->writeln ( "---start_date或者end_date未传入---" );
			exit;
		}
		// 		var_dump(11);die;

		while ( $start_date <= $end_date ) {
			//  	var_dump(strtotime ( '-1 day',$start_date ) );die;
			//统计昨天 注册日
			$this->date = date ( 'Y-m-d', strtotime ( '-1 day', strtotime ( $start_date ) ) );

			$this->date_time = strtotime ( $this->date );

			//统计的范围
			$this->countWhereBetween = [ "{$this->date} 0:00:00", "{$this->date} 23:59:59" ];

			$this->_deleteLog ( $output );

			$this->_generateLog ( $output );

			//日期推后一天
			$start_date = date ( 'Y-m-d', strtotime ( "+1 day", strtotime ( $start_date ) ) );
		}
		$output->writeln ( "---本次定时任务结束---" );
	}

	private function _generateLog ( Output $output )
	{
		$output->writeln ( "--{$this->date} 日更新玩家留存详情开始--" );

		$insert = [
			'date'              => $this->date,
			'sid'               => self::SID,
			'channel_id'        => self::CHANNEL_ID,
			'register_num'      => 0, //注册人数
			'tow_day_money'     => 0,//次日ltv消费
			'three_day_money'   => 0,//三日ltv消费
			'four_day_money'    => 0,//四日ltv消费
			'five_day_money'    => 0,//五日ltv消费
			'six_day_money'     => 0,//六日ltv消费
			'seven_day_money'   => 0,//七日ltv消费
			'eight_day_money'   => 0,//八日ltv消费
			'nine_day_money'    => 0,//九日ltv消费
			'ten_day_money'     => 0,//十日ltv消费
			'fifteen_day_money' => 0,//十五日ltv消费
		];


		//获取该渠道注册玩家
		$role_register = $this->db_log->table ( 'tbl_register_log' )
		                              ->field ( 'role_id' )
		                              ->whereBetween ( 'create_time', $this->countWhereBetween )
		                              ->where ( [ 'channel_id' => self::CHANNEL_ID ] )
		                              ->count ();
		if ( !empty( $role_register ) ) {
			$insert[ 'register_num' ] = $role_register;
		}
		$where3 = [
			'date'       => $this->date,
			'channel_id' => self::CHANNEL_ID
		];
		$find   = $this->db_report->table ( 'tbl_pay_ltv_report' )
		                          ->where ( $where3 )
		                          ->find ();
		if ( empty( $find ) ) {
			//插入昨天的留存几基本数据
			$result = $this->db_report->table ( 'tbl_pay_ltv_report' )
			                          ->insert ( $insert );
			if ( empty( $result ) ) {
				$output->writeln ( "--{$this->date}渠道玩家Ltv根失败--" );
			}
			else {
				$output->writeln ( "--{$this->date} 玩家留存根成功--" );
			}
		}

		//遍历数组中每一天  更新玩家是否留存
		foreach ( self::KEEP_DAYS as $day => $field ) {
			$this->countKeep ( $day, $field, $output );
		}

		$output->writeln ( "--{$this->date}日更新Ltv数据结束--" );
	}


	private function _deleteLog ( Output $output )
	{
		/*	$output->writeln ( "日期:{$this->date};小时:{$this->hour} - 删除旧数据开始......" );
			$map    = [
				'date_time' => "{$this->date} {$this->hour}:00:00",
			];
			$result = $this->log->table ( 'tbl_game_log' )
									  ->where ( $map )
									  ->delete ();
			if ( $result == FALSE ) {
				$output->writeln ( "日期: {$this->date} 小时: {$this->hour} tbl_game_log 没有数据删除" );
				return;
			}
			$output->writeln ( "日期: {$this->date} 小时: {$this->hour} tbl_game_log 删除成功" );*/
	}

	private function countKeep ( $day, $field, Output $output )
	{
		//时间超过今天 则直接返回0
		//统计那天的日期
		$map[ 'date' ]       = date ( 'Y-m-d', strtotime ( "-{$day} day", $this->date_time ) );
		$map[ 'channel_id' ] = self::CHANNEL_ID;

		$keepReport = $this->db_report->table ( 'tbl_pay_ltv_report' )
		                              ->where ( $map )
		                              ->find ();
		//	var_dump($map);die;
		//如果那那天的留存报表不存在
		if ( empty( $keepReport ) ) {
			return;
		}
		//ltv总充值金额
		$role_res = $this->db_log->table ( 'tbl_register_log' )
		                         ->whereBetween ( 'create_time', [
			                         "{$map['date']} 0:00:00",
			                         "{$map['date']} 23:59:59"
		                         ] )
		                         ->where ( [ 'channel_id' => self::CHANNEL_ID ] )
		                         ->field ( "role_id" )
		                         ->select ();
		if ( empty( $role_res ) ) {
			return;
		}
		//玩家列表
		$role_list = array_column ( $role_res, 'role_id' );
		//玩家列表分页总页数
		$page = ceil ( count ( $role_list ) / self::ROLE_PAGE_SIZE );

		$sum_money = 0;
		//分页获取玩家登陆 $k 页数偏移量
		for ( $k = 0; $k < $page; $k++ ) {
			// 数组 , 开始元素下标 ,截取长度
			$tmp_role  = array_slice ( $role_list, $k * self::ROLE_PAGE_SIZE, self::ROLE_PAGE_SIZE );
			$where     = [
				'role_id'    => [ 'in', $tmp_role ],
				'channel_id' => self::CHANNEL_ID,
				'pay_status' => self::PAY_STATUS_TRUE,
				'date_time'  => [
					'between',
					[
						$map[ 'date' ] . ' 00:00:00', //开始那天的日期
						$this->date . ' 23:59:59'  //当前运行 计算的日期
					],
				]
			];
			$money     = $this->db_center->table ( 'tbl_pay_order_log_huawei' )
			                             ->where ( $where )
			                             ->sum ( 'price' );
			$sum_money = bcadd ( $sum_money, $money, 2 );
		}
		//拼接 更新字段名
		$update = [
			$field => $sum_money
		];

		$result = $this->db_report->table ( 'tbl_pay_ltv_report' )
		                          ->where ( $map )
		                          ->update ( $update );
		if ( empty( $result ) ) {
			$output->writeln ( "--{$map['date']} {$field}数量没有变化 --" );
		}
		//存在  更新报表
	}
}