<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/12/3
 * Time: 18:33
 */

namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class MysqlDataInsert extends Command
{


	private $db;

	const CREATE_TABLE = 'CREATE TABLE';
	const TABLE_FIELD = [
		'tbl_exp_buff_log'           => [
			'date as create_time',
			'sid',
			'roleid as role_id',
			'eid',
			'esrc',
			'p1 as add_time',
			'p2 as change_time',
			'ps1 as activity_id'
		],
		'tbl_prop_log'               => [
			'date as create_time',
			'sid',
			'roleid as role_id',
			'eid',
			'esrc',
			'p1 as number',
			'ps1 as prop_id',
			'ps2 as activity_id',
		],
		'tbl_upgrade_bonus_buff_log' => [
			'date as create_time',
			'sid',
			'roleid as role_id',
			'eid',
			'esrc',
			'p1 as add_time',
			'p2 as due_time',
			'ps1 as activity_id',
		],
	];


	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'MysqlDataInsert' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '数据拉取' )/**
		 * 定义形参
		 * Argument::REQUIRED = 1; 必选
		 * Argument::OPTIONAL = 2;  可选
		 */
		     ->addArgument ( 'start_date', Argument::OPTIONAL, '开始日期' )
		     ->addArgument ( 'duration_day', Argument::OPTIONAL, '往后多少天' )
		     ->addArgument ( 'table_name', Argument::OPTIONAL, '表名' )
		     ->setHelp ( "开始日期,哪一天" );
	}


	/**
	 * 处理多个表插入到汇总表
	 *     * 生成所需的数据表
	 * php think test  调用的方法
	 *
	 * @param Input  $input  接收参数对象
	 * @param Output $output 操作命令
	 *
	 * @return int|null|void
	 * @throws \think\Exception
	 */
	protected function execute ( Input $input, Output $output )
	{
		//数据库连接
		$this->db = Db::connect ( 'database.log' );

		//不是定时任务
		//报表重跑  获得两个参数 ,一个为开始时间
		$startDateYmd = $input->getArgument ( 'start_date' );
		$startTime    = strtotime ( $startDateYmd );
		//持续小时
		$durationDay = $input->getArgument ( 'duration_day' );
		$table       = $input->getArgument ( 'table_name' );

		//重跑$hour个小时 的报表
		for ( $i = 0; $i < $durationDay; $i++ ) {
			$dateYmd = date ( 'Ymd', strtotime ( "+{$i} day", $startTime ) );
			//拉取表的名字
			$tableName = $table . '_' . $dateYmd;//时间
			$exist     = $this->db->query ( "show tables like '{$tableName}'" );
			if ( empty( $exist ) ) {
				$output->writeln ( "---{$tableName}:表不存在---" );
				die;
			}

			$data = $this->db->table ( $tableName )
			                 ->field ( self::TABLE_FIELD[ $table ] )
			                 ->select ();
			dump($data);die;
			$this->db->table ( $table )
			         ->insertAll ( $data );
			$output->writeln ( "{$tableName}表拉取插入成功" );

		}
		$output->writeln ( "结束" );

	}


}