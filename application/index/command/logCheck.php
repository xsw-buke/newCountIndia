<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/1/21
 * Time: 20:46
 */


namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;
use think\File;

class logCheck extends Command
{

	private $db_pay;
	private $dateYmd;

	const H = [
		'00',
		'01',
		'02',
		'03',
		'04',
		'05',
		'06',
		'07',
		'08',
		'09',
		'10',
		'11',
		'12',
		'13',
		'14',
		'15',
		'16',
		'17',
		'18',
		'19',
		'20',
		'21',
		'22',
		'23',
	];

	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'logCheck' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '日志校验' )/**
		 * 定义形参
		 * Argument::REQUIRED = 1; 必选
		 * Argument::OPTIONAL = 2;  可选
		 */
		     ->addArgument ( 'dateYmd', Argument::OPTIONAL, '日期Ymd格式' )
		     ->setHelp ( "暂无" );
	}

	///	 *     * 生成所需的数据表
	//	 * php think test  调用的方法
	//	 *
	//	 * @param Input  $input  接收参数对象
	//	 * @param Output $output 操作命令

	protected function execute ( Input $input, Output $output )
	{
		$this->dateYmd = $input->getArgument ( 'dateYmd' );
		//数据库连接初始化
		//		$this->db_report = Db::connect ( 'database.report' );
		//		$this->db_log    = Db::connect('database.center'); //阿里测试用
		$this->db_pay = Db::connect ( 'database.pay' ); //线上用

		foreach ( self::H as $i ) {

			$file = fopen ( 'dump/dump_11_' . $this->dateYmd . $i . '.log', "r" );   //打开文件
			//检测指针是否到达文件的未端
			while ( !feof ( $file ) ) {
				$str = fgets ( $file );
				if ( empty( $str ) ) {
					break;
				}
				$result = explode ( '] (', $str );

				$str2 = substr ( $result[ 1 ], 0, strlen ( $result[ 1 ] ) - 2 );
				$str3 = str_replace ( "'", "", $str2 );

				$array   = explode ( ',', $str3 );
				$where   = [
					'date'   => $array[ 0 ],
					'sid'    => $array[ 1 ],
					'roleid' => $array[ 2 ],
					'eid'    => $array[ 3 ],
					'esrc'   => $array[ 4 ],
					'p1'     => $array[ 5 ],
					'p2'     => $array[ 6 ],
					'p3'     => $array[ 7 ],
					'p4'     => $array[ 8 ],
					'p5'     => $array[ 9 ],
					'p6'     => $array[ 10 ],
					'ps1'    => $array[ 11 ],
					'ps2'    => $array[ 12 ],
					'ps3'    => $array[ 13 ],
				];
				$result2 = $this->db_pay->table ( 't_logs_' . $this->dateYmd )
				                        ->insert ( $where );
				echo $result2;
			}
			fclose ( $file );//关闭被打开的文件
			$output->write ( '一次完成' );
		}
	}

	//读取TXT文件内容
	public function read ()
	{
		$file = file_get_contents ( 'data1.txt' );
		$rep  = str_replace ( "\r\n", ',', $file );
		$cont = explode ( ',', $rep );
		for ( $i = 0; $i < count ( $cont ); $i++ ) {
			$data  = [
				'code'   => $cont[ $i ],
				'status' => 0,
				'time'   => time ()
			];
			$inser = Db::name ( 'active' )
			           ->insert ( $data );
			if ( $inser ) {
				echo 'done';
			}
			else {
				echo 'fail';
			}
		}

	}

}