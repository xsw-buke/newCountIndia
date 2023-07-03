<?php


namespace app\index\command;


use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class GameKeepReport extends Command
{
	const SID = 6;
	const SID_ARRAY = [
		//H5
		6 => [
			'910012',
			'910037',
			'910007',
			'910013',
			'910018',
			'910020',
			'910017',
		],
		//22蜗牛  25亿酷

		//中至
		23 => [
//			'2210015',
//			'2210020',
//			'2210013',
			'2410037',
			'2310037',
			'2410029',
			'2310029',
			'2410020',
			'2310020',
			'2410018',
			'2310018',
			'2410017',
			'2310017',
			'2410013',
			'2310013',
			'24910012',
			'23910012',
			'2410007',
			'2310007',
		]
	];
	const H5_GAME_LIST = [
		//H5
		'910012',
		'910037',
		'910007',
		'910013',
		'910018',
		'910020',
		'910017',

	];
	private $db_center;
	private $db_log;
	private $db_report;
	private $date;
	private $date_time;
	private $channel_id_list;
	private $countWhereBetween;
	private $dateYmd;

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
	 * @var int
	 */
	private $start_time;
	/**
	 * @var false|string
	 */

	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'GameKeepReport' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( 'h5游戏留存报表' )/**
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
		$this->db_center  = Db::connect ( 'database.center' );
		$this->db_log     = Db::connect ( 'database.log' );
		$this->db_report  = Db::connect ( 'database.report' );
		$this->start_time = time ();
		//获取现有渠道ID
		$this->channel_id_list = Db::connect ( 'database.portal' )
		                           ->table ( 'tp_channel' )
		                           ->field ( 'channel_id' )
		                           ->distinct ( TRUE )
		                           ->select ();
		//是定时任务
		if ( !empty( $input->getArgument ( 'cronTab' ) ) ) {
			//统计昨天 注册日
			$this->date      = date ( 'Y-m-d', strtotime ( '-1 day' ) );
			$this->dateYmd   = date ( 'Ymd', strtotime ( '-1 day' ) );
			$this->date_time = strtotime ( $this->date );
			//统计的范围
			$this->countWhereBetween = [ "{$this->date} 0:00:00", "{$this->date} 23:59:59" ];

			$this->_deleteRegLog ( $output );

			$this->_reg ( $output );

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

		while ( $start_date <= $end_date ) {
			//统计昨天 注册日
			$this->date = date ( 'Y-m-d', strtotime ( $start_date ) );

			//当前日时间
			$this->dateYmd   = date ( 'Ymd', strtotime ( $start_date ) );
			$this->date_time = strtotime ( $this->date );

			//统计的范围
			$this->countWhereBetween = [ "{$this->date} 0:00:00", "{$this->date} 23:59:59" ];
			$this->_reg ( $output );

			$this->_deleteLog ( $output );

			$this->_generateLog ( $output );

			//日期推后一天
			$start_date = date ( 'Y-m-d', strtotime ( "+1 day", strtotime ( $start_date ) ) );
		}
		$output->writeln ( "---本次定时任务结束---" );
	}

	private function _generateLog ( Output $output )
	{
		$output->writeln ( "--{$this->date} 日更新留存数据开始--" );
		foreach ( self::SID_ARRAY as $sid => $game_array ) {

			foreach ( $game_array as $game_id ) {
				//多次请求该接口 处理 不重复插入
				$map[ 'date' ]    = $this->date;
				$map[ 'game_id' ] = $game_id;
				$map[ 'sid' ]     = $sid;

				$keepReport = $this->db_report->table ( 'tbl_game_keep_report' )
				                              ->where ( $map )
				                              ->find ();


				if ( empty( $keepReport ) ) {
					//获取注册人数
					$reg_count             = $this->db_log->table ( 'tbl_game_reg_log' )
					                                      ->where ( $map )
					                                      ->count ();
					$add[ 'register_num' ] = intval ( $reg_count );
					//注册日期
					$add[ 'date' ]    = $map[ 'date' ];
					$add[ 'game_id' ] = $game_id;
					$add[ 'sid' ]     = $sid;
					// var_dump($add);die;
					//插入昨天的留存几基本数据
					$result = $this->db_report->table ( 'tbl_game_keep_report' )
					                          ->insert ( $add );
					if ( empty( $result ) ) {
						$output->writeln ( "--{$this->date}游戏 {$game_id}生成的留存根数据失败--" );
					}
					$output->writeln ( "--{$this->date}游戏 {$game_id}生成的留存根数据成功--" );
				}
				else {
					//注册表
					$update[ 'register_num' ] = $this->db_log->table ( 'tbl_game_reg_log' )
					                                         ->where ( $map )
					                                         ->count ();

					//更新注册人数
					$result = $this->db_report->table ( 'tbl_game_keep_report' )
					                          ->where ( $map )
					                          ->update ( $update );
					if ( empty( $result ) ) {
						$output->writeln ( "--{$this->date} 游戏 {$game_id}注册人数没有变化--" );
					}
					else {
						$output->writeln ( "--{$this->date}游戏 {$game_id}注册人数更新成功--" );
					}
				}
				//遍历该游戏 15日中每一天 留存
				foreach ( self::KEEP_DAYS as $day => $field ) {
					$this->countKeep ( $day, $field, $game_id, $output );
				}
			}

		}
		$output->writeln ( "--{$this->date} 游戏 {$game_id}日更新留存数据结束--" );
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

	private function _deleteRegLog ( Output $output )
	{
		$output->writeln ( "日期:{$this->date}; - 删除旧数据开始......" );
		$map    = [
			'date' => $this->date,
		];
		$result = $this->db_log->table ( 'tbl_game_reg_log' )
		                       ->where ( $map )
		                       ->delete ();
		if ( $result == FALSE ) {
			$output->writeln ( "日期: {$this->date}  tbl_game_reg_log 没有数据删除" );
			return;
		}
		$output->writeln ( "日期: {$this->date}  tbl_game_reg_log 删除成功" );
	}

	private function countKeep ( $day, $field, $game_id, Output $output )
	{
		//时间超过今天 则直接返回0
		$reg_date = date ( 'Y-m-d', strtotime ( "-{$day} day", $this->date_time ) );

		//统计那天的日期
		$map[ 'date' ]    = $reg_date;
		$map[ 'game_id' ] = $game_id;
		$keepReport       = $this->db_report->table ( 'tbl_game_keep_report' )
		                                    ->where ( $map )
		                                    ->find ();
		//	var_dump($map);die;
		//如果那那天的留存报表不存在
		if ( empty( $keepReport ) ) {
			return;
		}
		//获取当天注册的人数
		$where[ 'date' ]    = $map[ 'date' ];
		$where[ 'game_id' ] = $game_id;
		$role_res           = $this->db_log->table ( 'tbl_game_reg_log' )
		                                   ->where ( $where )
		                                   ->field ( "role_id" )
		                                   ->select ();


		//如果没有注册 直接返回
		if ( empty( $role_res ) ) {
			return;
		}
		//玩家列表
		$role_list = array_column ( $role_res, 'role_id' );
		//玩家列表分页总页数
		$page = ceil ( count ( $role_list ) / self::ROLE_PAGE_SIZE );

		$count   = 0;
		$dateYmd = date ( 'Ymd', strtotime ( $map[ 'date' ] ) );
		//分页获取玩家登陆 $k 页数偏移量
		for ( $k = 0; $k < $page; $k++ ) {
			// 数组 , 开始元素下标 ,截取长度
			$tmp_role = array_slice ( $role_list, $k * self::ROLE_PAGE_SIZE, self::ROLE_PAGE_SIZE );

			$where = [
				'role_id' => [ 'in', $tmp_role ],
				'game_id' => $game_id,
			];

			//玩家在这个时间范围内登陆登出
			$count += $this->db_log->table ( 'tbl_room_log_' . $this->dateYmd )
			                       ->where ( $where )
				//									->fetchSql()
				                   ->count ( 'distinct role_id' );


		}

		//拼接 更新字段名
		$update = [
			$field => $count
		];
		/*if ($day == 2){
			dump ($map);
			dump ($update);
			$result = $this->db_report->table ( 'tbl_game_keep_report' )
			                          ->where ( $map )->fetchSql()
			                          ->update ( $update );
			dump ($result);die;
		}*/
		$result = $this->db_report->table ( 'tbl_game_keep_report' )
		                          ->where ( $map )
		                          ->update ( $update );
		if ( empty( $result ) ) {
			$output->writeln ( "--{$map['date']}游戏 {$game_id} {$field}数量没有变化 --" );
		}
		//存在  更新报表
	}


	private function _reg ( Output $output )
	{
		foreach ( self::SID_ARRAY as $sid => $game_array ) {

			$field = [
				'room.sid',
				'room.role_id',
				'room.game_id',
				'reg.sid as reg_sid'
			];
			$list  = $this->db_log->table ( 'tbl_room_log_' . $this->dateYmd )
			                      ->alias ( 'room' ) //别名
			                      ->field ( $field )
			                      ->join ( 'tbl_game_reg_log reg', 'reg.role_id = room.role_id and reg.game_id =room.game_id', 'LEFT' )
			                      ->where ( [ 'room.sid' => $sid ] )
			                      ->group ( 'role_id,game_id' )
			                      ->having ( 'ISNULL(reg_sid)' )
			                      ->select ();


			if ( empty( $list ) ) {
				$output->writeln ( "{$this->dateYmd}room没有数据" );
				return FALSE;
			}
			$arr = [
				'create_time' => date ( 'Y-m-d H:i:s' ),
				'date'        => $this->date,
			];
			//多数组函数操作
			array_walk ( $list, function ( &$value, $key, $arr ) {
				unset( $value[ 'reg_sid' ] );
				$value = array_merge ( $value, $arr );
			}, $arr );
			$result = $this->db_log->table ( 'tbl_game_reg_log' )
			                       ->insertAll ( $list );
			if ( empty( $result ) ) {
				$output->write ( '插入tbl_game_reg_log异常' );
				//			return FALSE;
				continue;
			}
		}
		return TRUE;

	}
}