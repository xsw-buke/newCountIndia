<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/12/29
 * Time: 18:21
 */

namespace app\index\command;


use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class RegistertInsert extends Command
{
	private $db_log;
	private $db_slot;
	private $dateYmd;
	private $db_logtest;
	const SIZE = 1000;
	const FIELD = [
		'sid',
		'roleid as role_id',
		'eid',
		'esrc',
		'date as create_time',
		'ps1 as json_info',
		'p1 as channel_id',

	];

	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'RegistertInsert' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '注册信息入库' )/**
		 * 定义形参
		 * Argument::REQUIRED = 1; 必选
		 * Argument::OPTIONAL = 2;  可选
		 */
		     ->addArgument ( 'start_date', Argument::OPTIONAL, '开始日期:格式2012-12-12' )
		     ->addArgument ( 'end_date', Argument::OPTIONAL, '结束日期' )// 运行 "php think list" 时的简短描述
		     ->setHelp ( "暂无" );
	}

	//	/**
	//	 * 执行方法  合并
	//	 *
	//	 * @param Input  $input
	//	 * @param Output $output
	//	 *
	//	 * @return int|null|void
	//	 * @throws \think\Exception
	//	 */
	//	protected function execute ( Input $input, Output $output )
	//	{
	//		//合并
	//		$this->db_slot    = Db::connect ( 'database.log' );
	//		$this->db_logtest = Db::connect ( 'database.log' );
	//		//		$this->db_logtest = Db::connect ( 'database.logtest' );
	//		$where = [
	//			'create_time' => [ 'GT', '2021-01-05 04:45:19' ],
	//		];
	//
	//		$field = [
	//			'create_time',
	//			'sid',
	//			'role_id',
	//			'eid',
	//			'esrc',
	//			'channel_id',
	//			'json_info',
	//			'last_ip',
	//			'last_date',
	//			'last_grade',
	//			'ban_date',
	//			'ban_time',
	//			'update_time',
	//		];
	//		$count = $this->db_logtest->table ( 'tbl_register_log_copy' )
	//		                          ->count ();
	//		//		dump($count);die;
	//		if ( $count == 0 ) {
	//			dump ( '错误' );
	//			exit;
	//		}
	//		//计算分多少页
	//		$pageCount = ceil ( $count / self::SIZE );
	//
	//		//分页 页数小于总页数 页数上面再加一 代表开始的
	//		for ( $page = 1; $page <= $pageCount; $page++ ) {
	//			$insertAll = [];
	//			$insertAll = $this->db_logtest->table ( 'tbl_register_log_copy' )
	//			                              ->field ( $field )
	//			                              ->page ( $page, self::SIZE )
	//			                              ->select ();
	//			$result = $this->db_slot->table ( 'tbl_register_log' )
	//			                        ->insertAll ( $insertAll );
	//			dump ( [ $result, $page] );
	//
	//		}
	//	}

	//	/**
	//	 * 执行方法  拉取
	//	 *
	//	 * @param Input  $input
	//	 * @param Output $output
	//	 *
	//	 * @return int|null|void
	//	 * @throws \think\Exception
	//	 */
	//	protected function execute ( Input $input, Output $output )
	//	{
	//		//初始化数据库连接  下面是注册日志再操作
	//		$this->db_slot = Db::connect ( 'database.slotdatatest' );
	//		$this->db_log  = Db::connect ( 'database.log' );
	//		//获取传输的时间
	//		$start_date = $input->getArgument ( 'start_date' );
	//		$end_date   = $input->getArgument ( 'end_date' );
	//
	//
	//		if ( empty( $start_date ) || empty( $end_date ) ) {
	//			$output->writeln ( "---start_date或者end_date未传入---" );
	//			exit;
	//		}
	//		$output->writeln ( "---定时任务开始---" );
	//		// 每次拉取五百条 然后一次入库
	//		while ( $start_date <= $end_date ) {
	//			$this->dateYmd = date ( 'Ymd', strtotime ( $start_date ) );
	//			//判断表表不存在
	//			$exist = $this->db_slot->query ( "show tables like 't_logs_{$this->dateYmd}'" );
	//			//			//如果不存在
	//			if ( empty( $exist ) ) {
	//				$output->writeln ( "---t_logs_{$this->dateYmd}日表不存在---" );
	//			}
	//			else {
	//				$where = [ 'eid' => 1001, ];//注册信息
	//				//计算条数
	//				$count = $this->db_slot->table ( "t_logs_{$this->dateYmd}" )
	//				                       ->where ( $where )
	//				                       ->count ();
	//				//计算分多少页
	//				$pageCount = ceil ( $count / self::SIZE );
	//
	//				$output->writeln ( "本次共处理页数{$pageCount}" );  //获取一千条总日志 分别插入其他日志0
	//				for ( $page = 1; $page <= $pageCount; $page++ ) {
	//					$logs = [];
	//					$logs = $this->db_slot->table ( "t_logs_{$this->dateYmd}" )
	//					                      ->field ( self::FIELD )
	//					                      ->where ( $where )
	//					                      ->page ( $page, self::SIZE )
	//					                      ->select ();
	//					if ( !empty( $logs ) ) {
	//						$result = $this->db_log->table ( 'tbl_register_log_copy' )
	//						                       ->insertAll ( $logs );
	//						$output->writeln ( $start_date . '第' . $page . '页---tbl_register_log表插入' . $result );
	//					}
	//				}
	//			}
	//			$output->writeln ( $start_date . '处理完成' );
	//			//日期加1
	//			$start_date = date ( 'Y-m-d', strtotime ( " + 1 day", strtotime ( $start_date ) ) );
	//		}
	//		$output->writeln ( "---本次定时任务结束---" );
	//
	//	}

	/**
	 * 执行方法  去重
	 *
	 * @param Input  $input
	 * @param Output $output
	 *
	 * @return int|null|void
	 * @throws \think\Exception
	 */
	protected function execute ( Input $input, Output $output )
	{
		//去重
		$this->db_slot = Db::connect ( 'database.log' );
		$field         = [
			'role_id',
			'count(*) as num'
		];

		$roleIds = $this->db_slot->table ( 'tbl_register_log' )
		                         ->field ( $field )
		                         ->group ( 'role_id' )
		                         ->having ( 'count(*)>1' )
		                         ->select ();
		dump ( count ( $roleIds ) );
		foreach ( $roleIds as $role ) {
			$result = $this->db_slot->table ( 'tbl_register_log' )
			                        ->field ( 'id' )
			                        ->where ( [ 'role_id' => $role[ 'role_id' ] ] )
			                        ->select ();
			unset( $result[ 0 ] );
			$where   = [
				'id' => [ 'in', array_column ( $result, 'id' ) ]
			];
			$result2 = $this->db_slot->table ( 'tbl_register_log' )
			                         ->where ( $where )

			                         ->delete ();

			dump ( [ $result2, $role ] );
		}
	}
}