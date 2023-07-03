<?php
/**
 * 在线时长报表
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/9/7
 * Time: 20:33
 */

namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class OnlineTimeReport extends Command
{

	//日期
	private $date;
	//日期数字 找表用的
	private $db;
	private $dateWhereBetween;
	private $create_time;
	const ROLE_PAGE_SIZE = 1000;

	//日期数字 找表用的
	private $db_report;
	private $db_log;

	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'OnlineTimeReport' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '生成在线时长日报表' )/**
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
		$this->create_time = date ( 'Y-m-d H:i:s' );

		//数据库连接初始化
		$this->db_report = Db::connect ( 'database.report' );
//		$this->db_log    = Db::connect ( 'database.center' ); //阿里测试用
		$this->db_log = Db::connect('database.log'); //线上用

		//是定时任务
		if ( !empty( $input->getArgument ( 'cronTab' ) ) ) {
			$yesterday              = date ( 'Y-m-d', strtotime ( '-1 day' ) );
			$this->date             = $yesterday;
			$this->dateWhereBetween = [
				$yesterday . " 0:00:00",
				$yesterday . " 23:59:59",
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
		//遍历服务器操作
		$server_list = Db::connect ( 'database.uwinslot' )
		                 ->table ( 'tp_server' )
		                 ->field ( 'id' )
						//TODO::test
//						 ->where (['id'=>11 ])
		                 ->select ();
		foreach ( $server_list as $server ) {
			//获取在线玩家列表
			$roleList = $this->db_log->table ( 'tbl_login_log' )
			                         ->field ( 'role_id' )
			                         ->whereBetween ( 'create_time', $this->dateWhereBetween )
			                         ->where ( [ 'sid' => $server[ 'id' ]
			                                     //TODO::test
//			                                     , 'role_id' => 1984661505,
			                         ] )
			                         ->distinct ( TRUE )
			                         ->select ();

			if ( empty( $roleList ) ) {
				$output->writeln ( $this->date . "日,{$server['id']}服务器 没有登陆玩家" );
				continue;
			}
			foreach ( $roleList as $roleInfo ) {
				//计算单个玩家在线时间
				$roleOnlineTimeAdd = $this->getRoleOnlineTime ( $roleInfo, $server[ 'id' ] );
				if ( $roleOnlineTimeAdd == FALSE ) {
					//异常数据不记录
					continue;
				}
				$this->db_report->table ( 'tbl_role_day_count' )
				                ->insert ( $roleOnlineTimeAdd );
				$output->writeln ( $this->date . "日,{$server['id']}服务器 {$roleInfo['role_id']} 玩家{$this->date}上线时间为 {$roleOnlineTimeAdd['online_time']} 秒" );
			}
		}

	}


	private function _deleteLog ( Output $output )
	{
		$output->writeln ( "日期:{$this->date} - 删除旧数据开始......" );

		$result = $this->db_report->table ( 'tbl_role_day_count' )
		                          ->where ( [ 'date' => $this->date ] )
		                          ->delete ();
		if ( $result == FALSE ) {
			$output->writeln ( "日期: {$this->date} tbl_role_day_count 没有数据删除" );
			return;
		}
		$output->writeln ( "日期: {$this->date}  tbl_role_day_count 删除成功" );
	}


	private function getRoleOnlineTime ( $roleInfo, $sid )
	{
		//获取今天所有登陆记录
		$loginLogList = $this->db_log->table ( 'tbl_login_log' )
		                             ->field ( [ 'eid', 'create_time', 'role_id' ] )
		                             ->where ( [
			                             'role_id' => $roleInfo[ 'role_id' ],
			                             'sid'     => $sid
		                             ] )
		                             ->whereBetween ( 'create_time', $this->dateWhereBetween )
									 ->order('create_time asc')
		                             ->select ();
		if ( empty( $loginLogList ) ) {
			return FALSE;
		}
		$time       = 0;
		$start_time = NULL;
		$end_time   = NULL;
		foreach ( $loginLogList as $value ) {
			// 如果是零点

			if ($value[ 'eid' ] == 1004 &&  $start_time == NULL && $end_time == NULL){
				$start_time = strtotime ( $value[ 'create_time' ] );
			}
			//如果是登录
			if (  $value[ 'eid' ] == 1002  && $end_time == NULL ) {
				$start_time = strtotime ( $value[ 'create_time' ] );
//				dump ('start_time:'.$value[ 'create_time' ]);
			}
			//下线 并且 开始时间存在  苦逼滚蛋吧事故湖吧喔
			if ( $value[ 'eid' ] == 1003 && $start_time != NULL ) {
				$end_time = strtotime ( $value[ 'create_time' ] );
//				dump ('end_time:'.$value[ 'create_time' ]);
			}

			if ( !empty( $start_time ) && !empty( $end_time ) ) {
				//叠加 清空
				$time       += $end_time - $start_time;
				$start_time = NULL;
				$end_time   = NULL;
//				dump ('清空:'.$value[ 'create_time' ]);
//				dump ('结算:'.$time);
			}
		}
		//跑完循环 如果start_time 还存在 异常数据
		if ( $start_time != NULL ) {
//			$time += strtotime ( $this->date . " 23:59:59" ) - $start_time;
//			$time = 0;
			return  FALSE;
		}

		return [
			'role_id'     => $roleInfo[ 'role_id' ],
			'online_time' => $time,
			'date'        => $this->date,
			'create_time' => $this->create_time,
			'sid'         => $sid
		];
	}

}