<?php
/**
 * TODO::留存统计 未调整完善
 * 留存报表
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

class KeepReport extends Command
{
	private $db_center;
	private $db_log;
	private $db_report;
	private $date;
	private $date_time;
	private $channel_id_list;
	private $countWhereBetween;
	//昨天日期
	const CREATE_INFO_DAY = 1;
	const ROLE_PAGE_SIZE = 1000;
	//留存日期
	const KEEP_DAYS = [
		1  => 'tow_day_num',//+1次日
		2  => 'three_day_num',//三日
		3  => 'four_day_num', //4
		4  => 'five_day_num',//5
		5  => 'six_day_num',//6
		6  => 'seven_day_num',//7
		7  => 'eight_day_num',//8
		8  => 'nine_day_num',//9
		9  => 'ten_day_num',//10
		14 => 'fifteen_day_num', //+14 第十五日留存  当日是剔除的
	];

	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'KeepReport' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '生成留存报表' )/**
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
		//获取现有渠道ID
		$this->channel_id_list = Db::connect('database.uwinslot')
            ->table('tp_channel_server')
            ->where(['status' => 1])
            ->field(['channel_id', 'sid'])
            ->select();
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

		foreach ( $this->channel_id_list as $value ) {
			//该日留存已经插入过 该日不重复插入
				//没有玩家留存  全部插入
				//  var_dump(empty($keepReport));die;
				//获取该渠道注册玩家
				$insertArray = $this->db_log->table ( 'tbl_register_log' )
													 ->field(['channel_id','role_id'])
				                                      ->whereBetween ( 'create_time', $this->countWhereBetween )
				                                      ->where ( [ 'channel_id' => $value[ 'channel_id' ] ] )
				                                      ->select ();
				foreach ($insertArray as $key2 => $value2){
					$insertArray[$key2]['date'] = $this->date;
				}
				// var_dump($add);die;
				//插入昨天的留存几基本数据
				$result = $this->db_report->table ( 'tbl_user_retention_details' )
				                          ->insertAll ( $insertArray ,'IGNORE');
				if ( empty( $result ) ) {
					$output->writeln ( "--{$this->date}渠道 {$value['channel_id']}玩家留存根失败--" );
				}
				$output->writeln ( "--{$this->date}渠道 {$value['channel_id']}玩家留存根成功--" );

		}

		//遍历数组中每一天  更新玩家是否留存
		foreach ( self::KEEP_DAYS as $day => $field ) {
			foreach ( $this->channel_id_list as $channel ) {
				$this->countKeep ( $day, $field, $channel[ 'channel_id' ], $output );
			}
		}

		$output->writeln ( "--{$this->date} 渠道 {$value['channel_id']}日更新留存数据结束--" );
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

	private function countKeep ( $day, $field, $channel_id, Output $output )
	{
		//时间超过今天 则直接返回0
		//统计那天的日期
		$map[ 'date' ]       = date ( 'Y-m-d', strtotime ( "-{$day} day", $this->date_time ) );
		$map[ 'channel_id' ] = $channel_id;

		$keepReport = $this->db_report->table ( 'tbl_keep_report' )
		                              ->where ( $map )
		                              ->find ();
		//	var_dump($map);die;
		//如果那那天的留存报表不存在
		if ( empty( $keepReport ) ) {
			return;
		}
		//这个是注册当日的玩家列表 日期为注册日
		/*$role_res = $this->db->table('tbl_register_log')->jion('tbl_login_log')
		                     ->whereBetween('date', ["{$map['date']} 0:00:00", "{$map['date']} 23:59:59"])
		                     ->where(['channel_id' => $channel_id])
		                     ->field("role_id")
		                     ->select();*/

		$role_res = $this->db_log->table ( 'tbl_register_log' )
		                         ->whereBetween ( 'create_time', [
			                         "{$map['date']} 0:00:00",
			                         "{$map['date']} 23:59:59"
		                         ] )
		                         ->where ( [ 'channel_id' => $channel_id ] )
		                         ->field ( "role_id" )
		                         ->select ();
		//  var_dump(11);die;
		//如果没有注册 直接返回
		if ( empty( $role_res ) ) {
			return;
		}
		//玩家列表
		$role_list = array_column ( $role_res, 'role_id' );
		//玩家列表分页总页数
		$page = ceil ( count ( $role_list ) / self::ROLE_PAGE_SIZE );

		$count = 0;
		//分页获取玩家登陆 $k 页数偏移量
		for ( $k = 0; $k < $page; $k++ ) {
			// 数组 , 开始元素下标 ,截取长度
			$tmp_role = array_slice ( $role_list, $k * self::ROLE_PAGE_SIZE, self::ROLE_PAGE_SIZE );

			$where = [
				'role_id'    => [ 'in', $tmp_role ],
				'channel_id' => $channel_id
			];
			$count += $this->db_log->table ( 'tbl_login_log' )
			                       ->where ( $where )
			                       ->whereBetween ( 'create_time', $this->countWhereBetween )
			                       ->count ( 'distinct role_id' );
		}
		//拼接 更新字段名
		$update = [
			$field => $count
		];

		$result = $this->db_report->table ( 'tbl_keep_report' )
		                          ->where ( $map )
		                          ->update ( $update );
		if ( empty( $result ) ) {
			$output->writeln ( "--{$map['date']}渠道{$channel_id} {$field}数量没有变化 --" );
		}
		//存在  更新报表
	}
}