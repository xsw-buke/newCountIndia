<?php
/**
 * 玩家报表
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/9/23
 * Time: 18:52
 */

namespace app\index\command;

use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class UserReport extends Command
{

	const PAGE_SIZE = 500;

	//压注消耗
	const SPIN_COST = 201;
	//压注获得
	const SPIN_GET = 202;
	//freeSpin 获得
	const FREE_SPIN_GET = 203;
	//freeSpin 获得游戏道具, 小游戏获得
	const  TINY_GAME_GET = 204;
	//freeSpinTinyGameGet ;
	const  FREE_SPIN_TINY_GAME_GET = 205;
	//查询值
	const  CHANGE_FIELD = 'add_gold';

	//日期数字 找表用的
	private $dateYmd;
	private $db_report;
	private $db_log;
	private $date;
	private $dateWhereBetween;

	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'UserReport' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '玩家报表' )/**
		 * 定义形参
		 * Argument::REQUIRED = 1; 必选
		 * Argument::OPTIONAL = 2; 可选
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
		$phpStartTime = time ();
		//数据库连接初始化
		$this->db_report = Db::connect ( 'database.report' );
		$this->db_log    = Db::connect ( 'database.log' );

		//是定时任务
		if ( !empty( $input->getArgument ( 'cronTab' ) ) ) {
			$time = strtotime ( '-1 hour' );
			//时间
			$this->date                  = date ( 'Y-m-d', $time );
			$this->dateWhereBetween[ 0 ] = $this->date . " 00:00:00";
			$this->dateWhereBetween[ 1 ] = $this->date . " 23:59:59";
			//表后数字
			$this->dateYmd = date ( 'Ymd', $time );
			//删除这一个天的投注统计
			$this->_deleteLog ( $output );

			//定时任务生成上一个天入库
			$this->_generateLog ( $output );

			$timeSub = time () - $phpStartTime;
			$str     = "开始时间" . date ( 'Y-m-d H:i:s', $phpStartTime ) . " ,结束时间" . date ( 'Y-m-d H:i:s' ) . ", 总计费时 {$timeSub} 秒";
			$output->writeln ( $str );
			exit;
		}

		//获取传输的时间
		$start_date = $input->getArgument ( 'start_date' );
		$end_date   = $input->getArgument ( 'end_date' );
		//重跑$hour个天 的报表
		while ( $start_date <= $end_date ) {
			//时间
			$this->date                  = date ( 'Y-m-d', strtotime ( $start_date ) );
			$this->dateWhereBetween[ 0 ] = $this->date . " 00:00:00";
			$this->dateWhereBetween[ 1 ] = $this->date . " 23:59:59";

			//表后数字
			$this->dateYmd = date ( 'Ymd', strtotime ( $start_date ) );
			//删除这天的投注统计
			$this->_deleteLog ( $output );

			//定时任务生成上一个天入库
			$this->_generateLog ( $output );
			//时间加3600秒
			$start_date = date ( 'Y-m-d', strtotime ( "+1 day", strtotime ( $start_date ) ) );
		}

		$second = time () - $phpStartTime;
		$output->writeln ( "开始时间" . date ( 'Y-m-d H:i:s', $phpStartTime ) . " ,结束时间" . date ( 'Y-m-d H:i:s' ) . ", 总计费时{$second}秒" );

	}

	private function _generateLog ( Output $output )
	{
		//遍历服务器操作
		$server_list = Db::connect ( 'database.uwinslot' )
		                 ->table ( 'tp_server' )
		                 ->field ( 'id' )
		                 ->select ();
		foreach ( $server_list as $server ) {

			$output->writeln ( "日期:{$this->date} 服务器{$server[ 'id' ]} - 生成玩家/天日志开始...... " );


			//总登陆人数
			$count = $this->db_log->table ( 'tbl_login_log' )
			                      ->field ( [ 'role_id' ] )
			                      ->whereBetween ( 'create_time', $this->dateWhereBetween )
			                      ->where ( [ 'sid' => $server[ 'id' ] ] )
			                      ->distinct ( 'true' )
			                      ->count ();

			if ( empty( $count ) ) continue;

			//做分片处理
			$pageCount = ceil ( $count / self::PAGE_SIZE );


			for ( $page = 1; $page <= $pageCount; $page++ ) {

				// 获取时间段有登陆的会员
				$roleList = $list = $this->db_log->table ( 'tbl_login_log' )
				                                 ->field ( [ 'role_id' ,'channel_id'] )
				                                 ->whereBetween ( 'create_time', $this->dateWhereBetween )
				                                 ->where ( [ 'sid' => $server[ 'id' ] ] )
				                                 ->page ( $page, self::PAGE_SIZE )
				                                 ->distinct ( 'true' )
				                                 ->select ();

				foreach ( $roleList as $role ) {
					//记录 放到最后
					$add              = [
						'sid' => $server[ 'id' ],
						'channel_id' => $role[ 'channel_id' ],
						'role_id' => $role[ 'role_id' ],
						'date'    => $this->date,
					];
					$map[ 'role_id' ] = $role[ 'role_id' ];
					$map[ 'sid' ] = $server[ 'id' ];
					//最开始的数据
					$start_info = $this->db_log->table ( 'tbl_gold_log_' . $this->dateYmd )
					                           ->where ( $map )
					                           ->find ();

					$add[ 'start_gold' ]  = $start_info[ 'change_gold' ] ? bcsub ( $start_info[ 'change_gold' ], $start_info[ 'add_gold' ] ) : 0;            //开始的金额
					$add[ 'start_level' ] = $start_info[ 'level' ] ?? 0;            //开始的等级

					$end_info = $this->db_log->table ( 'tbl_gold_log_' . $this->dateYmd )
					                         ->where ( $map )
					                         ->order ( 'id desc' )
					                         ->find ();

					$add[ 'end_gold' ]  = $end_info[ 'change_gold' ] ?? 0;            //结束的金额
					$add[ 'end_level' ] = $end_info[ 'level' ] ?? 0;            //结束的等级
					$add[ 'sub_gold' ]  = bcsub ( $add[ 'end_gold' ], $add[ 'start_gold' ] );            //金额差额
					//结束的数据

					$map[ 'esrc' ] = self::SPIN_COST;//压注
					$spinInfo      = $this->db_log->table ( 'tbl_gold_log_' . $this->dateYmd )
					                              ->field ( [
						                              'count(*) as spin_count',
						                              'sum(add_gold) as spin_cost'
					                              ] )
					                              ->where ( $map )
					                              ->find ();

					$add[ 'spin_count' ] = $spinInfo[ 'spin_count' ] ?? 0;//投注次数
					$add[ 'spin_cost' ]  = $spinInfo[ 'spin_cost' ] ?? 0; //投注金额
					//压注获得
					$map[ 'esrc' ]     = self::SPIN_GET;
					$add[ 'spin_get' ] = $this->db_log->table ( 'tbl_gold_log_' . $this->dateYmd )
					                                  ->field ( self::CHANGE_FIELD )
					                                  ->where ( $map )
					                                  ->sum ( self::CHANGE_FIELD );

					//FREE_SPIN_GET 获得
					$map[ 'esrc' ]          = self::FREE_SPIN_GET;
					$add[ 'free_spin_get' ] = $this->db_log->table ( 'tbl_gold_log_' . $this->dateYmd )
					                                       ->field ( self::CHANGE_FIELD )
					                                       ->where ( $map )
					                                       ->sum ( self::CHANGE_FIELD ) ?? 0;

					//小游戏获取得
					$map[ 'esrc' ]          = self::TINY_GAME_GET;
					$add[ 'tiny_game_get' ] = $this->db_log->table ( 'tbl_gold_log_' . $this->dateYmd )
					                                       ->field ( self::CHANGE_FIELD )
					                                       ->where ( $map )
					                                       ->sum ( self::CHANGE_FIELD ) ?? 0;
					//FREE_SPIN_TINY_GAME_GET 小游戏获得
					$map[ 'esrc' ]                    = self::FREE_SPIN_TINY_GAME_GET;
					$add[ 'free_spin_tiny_game_get' ] = $this->db_log->table ( 'tbl_gold_log_' . $this->dateYmd )
					                                                 ->field ( self::CHANGE_FIELD )
					                                                 ->where ( $map )
					                                                 ->sum ( self::CHANGE_FIELD ) ?? 0;

					//回收内存
					unset( $map );
					$result = $this->db_report->table ( 'tbl_user_report' )
					                          ->insert ( $add );
					if ( $result == FALSE ) {
						$output->writeln ( "玩家ID:{$role['role_id']} 玩家日报入库失败" );
					}

					// 输出日志
					$msg = "玩家{$role['role_id']}日统计:" . json_encode ( $add );
					$output->writeln ( $msg );
				}
			}


		}
		$output->writeln ( "日期:{$this->date}    - 生成玩家/天日志结束 " );

	}


	private function _deleteLog ( Output $output )
	{
		$output->writeln ( "日期:{$this->date};   - 删除旧数据开始......" );

		$map = [
			'date' => $this->date,
		];


		//删除报表
		$result = $this->db_report->table ( 'tbl_user_report' )
		                          ->where ( $map )
		                          ->delete ();

		if ( $result == FALSE ) {
			$output->writeln ( "日期: {$this->date}   tbl_user_report 没有数据删除" );
			return;
		}
		$output->writeln ( "日期: {$this->date}   tbl_user_report 删除成功" );
	}


}