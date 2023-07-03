<?php
/**
 * 日志补拉?
 *
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/3/11
 * Time: 17:04
 */

namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class ReadAdLog extends Command
{

	const AD_TYPE = [
		'a4',
		'a12',
		'a13',
	];
	private $db_slotdatacenter;
	private $db_log;
	private $insertArray;
	private $table;
	private $dateYmd;

	const SIZE = 1000;

	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'ReadAdLog' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '读取总日志到分日志命令' )/**
		 * 定义形参
		 * Argument::REQUIRED = 1; 必选
		 * Argument::OPTIONAL = 2;  可选
		 */
		     ->addArgument ( 'date', Argument::OPTIONAL, '是否是定时任务' )
		     ->setHelp ( "当不给开始结束时间,默认只跑当天;\n只给了开始时间,跑指定的一天;\n给了开始和结束时间,跑指定范围内所有数据;" );
	}


	protected function execute ( Input $input, Output $output )
	{
		$this->dateYmd = $input->getArgument ( 'date' );
		$this->table   = "t_logs_" . $this->dateYmd;
		//初始化数据库连接
		$this->db_slotdatacenter = Db::connect ( 'database.pay' );
		$this->db_log            = Db::connect ( 'database.log' );
		//
		//		$maxLogId = $this->db_slotdatacenter->table ( $this->table )
		//		                                    ->max ( 'id' );
		//处理表数据 这是昨天的 需要放到昨天的日志
		$this->handleLog ( $this->table, $output );

	}


	private function handleLog ( string $table, Output $output )
	{
		$map   = [
			'esrc' => 104,
			'ps1'  => [ 'in', self::AD_TYPE ]
		];
		$count = $this->db_slotdatacenter->table ( $table )
		                                 ->where ( $map )
		                                 ->count ();
		dump ( $map );
		//走到这里 如果条数等于0 说明只有一条记录
		if ( $count <= 0 ) {
			return FALSE;
		}
		//计算分多少页
		$pageCount = ceil ( $count / self::SIZE );
		//分页 页数小于总页数 页数上面再加一 代表开始的
		for ( $page = 1; $page <= $pageCount; $page++ ) {

			//获取一千条总日志 分别插入其他日志0
			$logs = $this->db_slotdatacenter->table ( $table )
			                                ->where ( $map )
			                                ->page ( $page, self::SIZE )
			                                ->select ();
			if ( empty( $logs ) ) {
				$output->writeln ( "t_logs_ 没有数据,本次返回" );
				continue;
			}


			//日志入库分日志
			foreach ( $logs as $value ) {
				//是已知的类型 入库
				//如果是登陆 更新注册 最后登陆时间 IP
				if ( $value[ 'esrc' ] == 104 && $value[ 'ps1' ] == 'a4' ) {
					$this->insertArray[] = [
						'sid'         => $value[ 'sid' ],
						'role_id'     => $value[ 'roleid' ],
						'eid'         => $value[ 'eid' ],
						'esrc'        => $value[ 'esrc' ],
						'create_time' => $value[ 'date' ],
						'ad_type'     => $value[ 'ps1' ],
						'msg'         => $value[ 'ps2' ],
						'pay_status'  => $this->getPayStatus ( $value[ 'ps2' ] )
					];
				}
				elseif ( $value[ 'esrc' ] == 104 && in_array ( $value[ 'ps1' ], self::AD_TYPE ) ) {
					$this->insertArray[] = [
						'sid'         => $value[ 'sid' ],
						'role_id'     => $value[ 'roleid' ],
						'eid'         => $value[ 'eid' ],
						'esrc'        => $value[ 'esrc' ],
						'create_time' => $value[ 'date' ],
						'ad_type'     => $value[ 'ps1' ],
						'msg'         => $value[ 'ps2' ],
						'pay_status'  => $this->getPayStatus ( $value[ 'ps3' ] )
					];
				}
			}
			if ( !empty( $this->insertArray ) ) {
				//已经组装好的数据入库
				$result = $this->db_log->table ( 'tbl_ad_log_' . $this->dateYmd )
				                       ->insertAll ( $this->insertArray );
				$output->writeln ( 'tbl_ad_log_' . $this->dateYmd . '表插入' . $result );
			}
			else {
				$output->writeln ( '无数据插入' );
			}
			$this->insertArray = [];
			$output->writeln ( date ( 'Y-m-d H:i:s' ) . "第{$page}页," );
		}
	}


	/**
	 * 获取玩家支付状态
	 *
	 * @param $ps2
	 *
	 * @return int
	 */
	private function getPayStatus ( $ps2 )
	{
		$array = json_decode ( $ps2, TRUE );

		if ( is_null ( $array ) ) {
			return 0;
		}
		if ( isset( $array[ 'charge' ] ) ) {
			return $array[ 'charge' ];
		}
		return 0;
		/*	//如果字符中包含等于0
			if ( strpos ( $ps2, '"charge":0' ) == TRUE ) {
				return 0;
			}    //如果字符中包含这个值
			elseif ( strpos ( $ps2, '"charge"' ) == TRUE ) {
				return 1;
			}
			//都不包含
			return 0;*/
	}

}