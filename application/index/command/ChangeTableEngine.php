<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/5/7
 * Time: 10:52
 */

/**
 * 创建表格
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/9/5
 * Time: 21:50
 */

namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class ChangeTableEngine extends Command
{

	private $db;
	private $db_datacenter;
	private $dateYmd;


	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'ChangeTableEngine' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '创建日志表命令' )/**
		 * 定义形参
		 * Argument::REQUIRED = 1; 必选
		 * Argument::OPTIONAL = 2;  可选
		 */
		     ->addArgument ( 'databases', Argument::OPTIONAL, '库名' )
		     ->setHelp ( "当不给开始结束时间,默认只跑当天;\n只给了开始时间,跑指定的一天;\n给了开始和结束时间,跑指定范围内所有数据;" );
	}


	/**
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
		if ( empty( $input->getArgument ( 'databases' ) ) ) {
			$output->writeln ( "databases不存在" );
			exit;
		}
		$database =  $input->getArgument ( 'databases' ) ;

		//数据库连接
		$this->db = Db::connect ( 'database.' .$database);
		$tables   = $this->db->query ( 'show tables;' );
		dump ($tables);
		foreach ( $tables as $value ) {
			$sql = $this->getSql ( $value[ 'Tables_in_'. $database] );
			$this->db->query ( $sql );
			$output->writeln ( $sql);
		}


	}

	private function getSql ( string $table )
	{
		return "ALTER TABLE `{$table}` ENGINE=MyISAM;";
	}
}