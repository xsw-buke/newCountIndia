<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/5/13
 * Time: 15:44
 */


namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class OperationalActivityReport extends Command
{


	//日期
	//每次拉取分页限制
	const SIZE = 1000;
	private $date;
	private $dateYmd;
	//日期数字 找表用的
	private $hour;
	private $dateWhereBetween;


	const EID_LOGIN = 1002;

	private $db_report;
	private $db_log;
	private $db_center;
	private $insertArray;

	//运营活动id对应
	const ACTIVITY_ID_CONFIG = [

		//充值活动1型
		8021 => 'tbl_complete_activity_log',
		8022 => 'tbl_complete_activity_log',
		8041 => 'tbl_complete_activity_log',
		//分级活动
		8031 => 'tbl_graded_activity_log',
		//连续付费活动
		8801 => 'tbl_continuous_activity_log',
		8802 => 'tbl_continuous_activity_log',
		8803 => 'tbl_continuous_activity_log',
		//小游戏
		9001 => 'tbl_games_activity_log',
		9011 => 'tbl_games_activity_log',
		//小游戏合并列车 MERGE TRAIN
		9031 => 'tbl_games_activity_log',
		//小游戏幸运蛋
		9021 => 'tbl_luckyeggs_activity_log',

	];

	const ACTIVITY_LIST = [
		'tbl_complete_activity_log', //完成类型
		'tbl_graded_activity_log',//分档次完成类型
		'tbl_continuous_activity_log',
		'tbl_games_activity_log'
	];

	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'OperationalActivityReport' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '生成运营活动报表' )/**
		 * 定义形参
		 * Argument::REQUIRED = 1; 必选
		 * Argument::OPTIONAL = 2;  可选
		 */
		     ->addArgument ( 'cronTab', Argument::OPTIONAL, '是否是定时任务' )
		     ->addArgument ( 'start_date', Argument::OPTIONAL, '开始日期:格式2012-12-12' )
		     ->addArgument ( 'end_date', Argument::OPTIONAL, '持续小时' )// 运行 "php think list" 时的简短描述
		     ->setHelp ( "暂无" );
	}

	private function _deleteLog ( Output $output )
	{
		foreach ( self::ACTIVITY_LIST as $value ) {
			$output->writeln ( "日期:{$this->date} - {$value}表删除旧数据开始......" );
			$result = $this->db_report->table ( $value )
			                          ->whereBetween ( 'create_time', $this->dateWhereBetween )
			                          ->delete ();
			if ( $result == FALSE ) {
				$output->writeln ( "日期: {$this->date} {$value} 没有数据删除" );
				//				return TRUE;
			}
			$output->writeln ( "日期: {$this->date}  {$value} 删除成功" );
		}
		$output->writeln ( "日期: {$this->date} 所有表 --- 删除成功" );
		return TRUE;

	}

	///	 *     * 生成所需的数据表
	//	 * php think test  调用的方法
	//	 *
	//	 * @param Input  $input  接收参数对象
	//	 * @param Output $output 操作命令

	protected function execute ( Input $input, Output $output )
	{
		$this->create_time = date ( 'Y-m-d H:i:s' );

		//数据库连接初始化
		$this->db_report = Db::connect ( 'database.report' );
		$this->db_log    = Db::connect ( 'database.log' );

		//是定时任务
		if ( !empty( $input->getArgument ( 'cronTab' ) ) ) {
			$this->date             = date ( 'Y-m-d', strtotime ( '-1 day' ) );
			$this->dateYmd          = date ( 'Ymd', strtotime ( '-1 day' ) );
			$this->dateWhereBetween = [
				$this->date . " 0:00:00",
				$this->date . " 23:59:59",
			];
			//删除这一个小时的投注统计
			$this->_deleteLog ( $output );
			//定时任务生成上一个小时入库
			$this->_generateLog ( $output );
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
			$this->date             = $start_date;
			$this->dateYmd          = date ( 'Ymd', strtotime ( $start_date ) );
			$this->dateWhereBetween = [
				$start_date . " 0:00:00",
				$start_date . " 23:59:59",
			];
			$output->writeln ( $this->date . "日 生成玩家登陆时长统计开始" );
			//删除这一个小时的投注统计
			$this->_deleteLog ( $output );
			//定时任务生成上一个小时入库
			$this->_generateLog ( $output );

			$output->writeln ( $this->date . "日 生成玩家登陆时长统计完毕......." );
			$start_date = date ( 'Y-m-d', strtotime ( "+1 day", strtotime ( $start_date ) ) );
		}
		$output->writeln ( "---本次定时任务结束---" );
	}

	private function _generateLog ( Output $output )
	{
		$table = 'tbl_activity_log_' . $this->dateYmd;
		//计算总id
		$maxId = $this->db_log->table ( $table )
		                      ->max ( 'id' );
		//走到这里 如果条数等于0 说明只有一条记录
		if ( $maxId == 0 ) {
			return FALSE;
		}

		//计算分多少页
		$pageCount = ceil ( $maxId / self::SIZE );
		$output->writeln ( date ( 'Y-m-d H:i:s' ) . "本次共处理页数{$pageCount},束ID{$maxId}" );
		//分页 页数小于总页数 页数上面再加一 代表开始的
		for ( $page = 1; $page <= $pageCount; $page++ ) {
			//分页的上行
			$lowerLimit = 1 + ( self::SIZE * ( $page - 1 ) );
			//如果是最后一页 分页的最小ID
			if ( $page == $pageCount ) {
				$upperLimit = $maxId;
			}
			else {
				$upperLimit = $lowerLimit + self::SIZE - 1;
			}

			//获取一千条总日志 分别插入其他日志0
			$logs = $this->db_log->table ( $table )
			                     ->whereBetween ( 'id', [ $lowerLimit, $upperLimit ] )
			                     ->select ();;
			if ( empty( $logs ) ) {
				$output->writeln ( "t_logs_{$this->dateYmd}没有数据,本次返回" );
				return FALSE;
			}


			foreach ( $logs as $value ) {
				//是已知的类型 入库
				if ( array_key_exists ( $value[ 'activity_id' ], self::ACTIVITY_ID_CONFIG ) ) {
					//表名做键名
					$this->insertArray[ self::ACTIVITY_ID_CONFIG[ $value[ 'activity_id' ] ] ][] = $this->LogToValue ( $value[ 'activity_id' ], $value );
				}

			}


		}
		if ( empty( $this->insertArray ) ) {

			$output->writeln ( date ( 'Y-m-d H:i:s' ) . "没有可以处理的活动数据" );

			return TRUE;
		}
		//已经组装好的数据入库
		foreach ( $this->insertArray as $insertTable => $insertAll ) {
			$result = $this->db_report->table ( $insertTable )
			                          ->insertAll ( $insertAll );
			$output->writeln ( $insertTable . '表插入' . $result );
		}
		$this->insertArray = [];


		$output->writeln ( date ( 'Y-m-d H:i:s' ) . "第{$page}页,ID起{$lowerLimit},束ID{$upperLimit}" );

		return TRUE;

	}


	private function LogToValue ( $activity_id, $value )
	{
		switch ( $activity_id ) {
			//充值活动
			case 8021:
			case 8022:
			case 8041:
				$result = json_decode ( $value[ 'json_info' ], TRUE );
				return [
					'create_time' => $value[ 'create_time' ],
					'sid'         => $value[ 'sid' ],
					'role_id'     => $value[ 'role_id' ],
					'eid'         => $value[ 'eid' ],
					'esrc'        => $value[ 'esrc' ],
					'level'       => $value[ 'level' ],
					'activity_id' => $value[ 'activity_id' ],
					'gold'        => $value[ 'add_gold' ],
					'channel_id'  => $result[ 'channelId' ],
					'spin_gold'   => $result[ 'spinMoney' ] ?? 0,
					'tb'          => $value[ 'add_gold' ] == 0 ? 0 : bcdiv ( $value[ 'add_gold' ], $result[ 'spinMoney' ], 2 ),
					'end_date'    => date ( 'Y-m-d', strtotime ( $result[ 'endTime' ] ) ),
					'start_date'  => date ( 'Y-m-d', strtotime ( $result[ 'beginTime' ] ) ),
				];

			case 8031:
				$result = json_decode ( $value[ 'json_info' ], TRUE );
				return [
					'create_time' => $value[ 'create_time' ],
					'sid'         => $value[ 'sid' ],
					'role_id'     => $value[ 'role_id' ],
					'eid'         => $value[ 'eid' ],
					'esrc'        => $value[ 'esrc' ],
					'level'       => $value[ 'level' ],
					'activity_id' => $value[ 'activity_id' ],
					'gold'        => $value[ 'add_gold' ],
					'channel_id'  => $result[ 'channelId' ],
					'dang'        => $result[ 'dang' ],
					'end_date'    => date ( 'Y-m-d', strtotime ( $result[ 'endTime' ] ) ),
					'start_date'  => date ( 'Y-m-d', strtotime ( $result[ 'beginTime' ] ) ),
				];

			case 8801:
			case 8802:
			case 8803:
				$result = json_decode ( $value[ 'json_info' ], TRUE );
				return [
					'create_time' => $value[ 'create_time' ],
					'sid'         => $value[ 'sid' ],
					'role_id'     => $value[ 'role_id' ],
					'eid'         => $value[ 'eid' ],
					'esrc'        => $value[ 'esrc' ],
					'level'       => $value[ 'level' ],
					'activity_id' => $value[ 'activity_id' ],
					'gold'        => $value[ 'add_gold' ],
					'channel_id'  => $result[ 'channelId' ],
					'step'        => $result[ 'step' ],
					'end_date'    => date ( 'Y-m-d', strtotime ( $result[ 'endTime' ] ) ),
					'start_date'  => date ( 'Y-m-d', strtotime ( $result[ 'beginTime' ] ) ),
				];
			//金矿
			case 9001:
				//海盗
			case 9011:
				//火车
			case 9031:
				//幸运蛋活动
			case 9021:
				$gold   = NULL;
				$result = json_decode ( $value[ 'json_info' ], TRUE );
				if ( isset( $result[ 'type' ] ) ) {
					if ( $result[ 'type' ] == 'charge' ) {
						$type = 1;
						//获取道具数量
						$props = $result    [ 'props' ][ 1 ];

						$gold = $result[ 'getInfo' ][ 'gold' ];
					}
					elseif ( $result[ 'type' ] == 'spin' ) {
						$type = 2;
						//获取道具数量
						$props = $result    [ 'props' ][ 1 ];
					}
					elseif ( $result[ 'type' ] == 'egg' ) {
						$type  = 3;
						$props = 0;
					}
					else {
						$type  = 4;
						$props = 0;
					}
				}
				else {
					$type  = 0;
					$props = 0;
				}

				return [
					'create_time' => $value[ 'create_time' ],
					'sid'         => $value[ 'sid' ],
					'role_id'     => $value[ 'role_id' ],
					'eid'         => $value[ 'eid' ],
					'esrc'        => $value[ 'esrc' ],
					'level'       => $value[ 'level' ],
					'activity_id' => $value[ 'activity_id' ],
					'gold'        => $gold ?? $value[ 'add_gold' ],
					'channel_id'  => $result[ 'channelId' ],
					'dang'        => $result[ 'cur' ] ?? 0,
					'type'        => $type,
					'props'       => $props,
					'end_date'    => date ( 'Y-m-d', strtotime ( $result[ 'endTime' ] ) ),
					'start_date'  => date ( 'Y-m-d', strtotime ( $result[ 'beginTime' ] ) ),
				];


		}
		return FALSE;
	}


}

