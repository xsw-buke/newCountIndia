<?php
/**
 * gold游戏报表重新进入报表拉取
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/9/7
 * Time: 20:33
 */

namespace app\index\command;

use app\index\model\SignatureHelper;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;
use think\Log;
use tp5redis\Redis;


class GoldUtc8Pull extends Command
{
	//esrc类型
	const GAME_ESRC = [
		201,
		202,
		203,
		204,
		205,
	];

	const SUB_UTC = 13;
	const SERVER_ID = 6;

	//当前操作的表
	private $dateYmd;
	//命令触发时间
	private $create_date;

	//日志库链接
	private $db_log;
	private $insertArray;

	//每次拉取分页限制
	const SIZE = 1000;


	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'GoldUtc8Pull' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '北京游戏报表拉取' )/**
		 * 定义形参
		 * Argument::REQUIRED = 1; 必选
		 * Argument::OPTIONAL = 2;  可选
		 */
		     ->addArgument ( 'cronTab', Argument::OPTIONAL, '是否是定时任务' )
		     ->addArgument ( 'start_date', Argument::OPTIONAL, '开始日期:格式2012-12-12' )
		     ->addArgument ( 'duration_day', Argument::OPTIONAL, '重跑天数' )// 运行 "php think list" 时的简短描述
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
		//初始化数据库连接
		$this->db_log = Db::connect ( 'database.log' );
		//时间初始化 当天的时间
		$this->create_date = date ( 'Y-m-d H:i:s' );


		//是定时任务 非空
		if ( !empty( $input->getArgument ( 'cronTab' ) ) ) {
			//处理的表明尾缀
			$this->dateYmd = date ( 'Ymd', strtotime ( '-1 day' ) );

			//删除这一个小时的投注统计
			//			$this->_deleteLog ( $output );
			//处理表数据 这是昨天的 需要放到昨天的日志
			$this->handleLog ( 'tbl_gold_log_' . $this->dateYmd, $output );

			$output->writeln ( "---本次定时任务结束---" );
			$js_date = date ( 'Y-m-d H:i:s' );
			$sj      = time () - strtotime ( $this->create_date );
			$output->writeln ( "开始时间{$this->create_date} ,结束时间{$js_date}, 总计费时{$sj}秒" );
			//redis解锁
			exit;
		}


		//获取传输的时间
		$start_date = $input->getArgument ( 'start_date' );
		//持续小时
		$durationDay = $input->getArgument ( 'duration_day' );

		$time = strtotime ( $start_date );
		//做主要逻辑 重新读取报表
		for ( $i = $durationDay; $i > 0; $i-- ) {
			//处理的表明尾缀
			$this->dateYmd = date ( 'Ymd',  $time  );

			$this->handleLog ( 'tbl_gold_log_' . $this->dateYmd, $output );
			//时间加上1天
			$time += 3600 * 24;
		}

		$js_date = date ( 'Y-m-d H:i:s' );
		$sj      = time () - strtotime ( $this->create_date );
		$output->writeln ( "开始时间{$this->create_date} ,结束时间{$js_date}, 总计{$sj}秒" );
		exit;
	}


	private function handleLog ( $table, Output $output )
	{
		$where = [
			'sid'  => self::SERVER_ID,
			'esrc' => [ 'in', self::GAME_ESRC ],
		];

		//计算这次处理的条数
		$count = $this->db_log->table ( $table )
		                      ->where ( $where )->count();
		//走到这里 如果条数等于0 说明只有一条记录
		if ( $count == 0 ) {
			$count = 1;
		}
		//计算分多少页
		$pageCount = ceil ( $count / self::SIZE );
		$output->writeln ( date ( 'Y-m-d H:i:s' ) . "本次共处理页数{$pageCount}" );
		//分页 页数小于总页数 页数上面再加一 代表开始的
		for ( $page = 1; $page <= $pageCount; $page++ ) {

			//每次获取一千条日志
			$logs = $this->db_log->table ( $table )
			                     ->where ( $where )
			                     ->page ( $page, self::SIZE )
			                     ->select ();

			if ( empty( $logs ) ) {
				$output->writeln ( "t_logs_{$this->dateYmd}没有数据,本次返回" );
				return FALSE;
			}


			//日志入库分日志
			foreach ( $logs as $value ) {
				unset($value['id']);
				//时间 减去13个小时
				$time                   = bcsub ( strtotime ( $value[ 'create_time' ] ), self::SUB_UTC * 3600 );
				//重组插入报表
//				$insertTableYmd         = date ( 'Ymd', $time );
				//重组插入时间
				$value[ 'utc8_create_time' ] = date ( 'Y-m-d H:i:s', $time );

				//表名做键名
				$this->insertArray[ 'tbl_utc8_gold_log_' . $this->dateYmd ][] = $value;

			}

			//两天的报表数据插入
			foreach ( $this->insertArray as $insertTable => $insertAll ) {
				$result = $this->db_log->table ( $insertTable )
				                       ->insertAll ( $insertAll );
				$output->writeln ( $insertTable . '表插入' . $result );
			}

			$this->insertArray = [];
			$output->writeln ( date ( 'Y-m-d H:i:s' ) . "第{$page}页" );
		}
		return TRUE;
	}


}

