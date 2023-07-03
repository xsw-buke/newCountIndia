<?php
/**活动报表
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/9/7
 * Time: 21:57
 */

namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class ActivityReport extends Command
{


	//ESCR 活动获得
	const ACTIVITY_GET = 13;
	//改变值  金币变更
	const  CHANGE_FIELD = 'add_gold';

	//ACTIVITY_ID
	//  其他来源
	const  ACTIVITY_OTHER_SOURCES = [
		'IN',
		[ 101, 102, 103, 104, 105, 106, 301, 302, 303 ]
	];
	//其他来源插入用
	const  ACTIVITY_OTHER_SOURCES_ID = 1;
	//  后台充值 查询用
	const ACTIVITY_BACKGROUND_RECHARGE = 12;
	//  报表活动ID
	const ACTIVITY_BACKGROUND_RECHARGE_ID = 2;
	// 邮件领取
	const  ACTIVITY_MAIL_GET = 401;
	//  报表活动ID
	const  ACTIVITY_MAIL_GET_ID = 3;

	//日期
	private $date;
	//日期数字 找表用的
	private $dateYmd;
	private $hour;
	private $dateWhereBetween;
	private $activityList;
	private $db_report;
	private $db_log;

	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'ActivityReport' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '生成活动小时报表' )/**
		 * 定义形参
		 * Argument::REQUIRED = 1; 必选
		 * Argument::OPTIONAL = 2;  可选
		 */
		     ->addArgument ( 'cronTab', Argument::OPTIONAL, '是否是定时任务' )
		     ->addArgument ( 'start_date', Argument::OPTIONAL, '开始日期:格式2012-12-12' )
		     ->addArgument ( 'duration_hour', Argument::OPTIONAL, '持续小时' )// 运行 "php think list" 时的简短描述
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
		//		$this->db_log    = Db::connect('database.center'); //阿里测试用
		$this->db_log = Db::connect ( 'database.log' ); //线上用
		//活动列表的获取
		$activityList       = Db::connect ( 'database.portal' )
		                        ->table ( 'tp_activity_config' )
		                        ->field ( 'activity_id' )
		                        ->distinct ( TRUE )
		                        ->select ();
		$this->activityList = array_column ( $activityList, 'activity_id' );

		//是定时任务
		if ( !empty( $input->getArgument ( 'cronTab' ) ) ) {
			//延迟时间改为2小时
			$time = strtotime ( '-2 hour' );
			//时间
			$this->date = date ( 'Y-m-d', $time );
			//表后数字
			$this->dateYmd = date ( 'Ymd', $time );
			//目前小时
			$this->hour                  = date ( 'H', $time );
			$this->dateWhereBetween[ 0 ] = $this->date . " {$this->hour}:00:00";
			$this->dateWhereBetween[ 1 ] = $this->date . " {$this->hour}:59:59";
			//删除这一个小时的投注统计 防止异常
			$this->_deleteLog ( $output );
			//定时任务生成上一个小时入库
			$this->_generateLog ( $output );

			$timeSub = time () - $phpStartTime;
			$str     = "开始时间" . date ( 'Y-m-d H:i:s', $phpStartTime ) . " ,结束时间" . date ( 'Y-m-d H:i:s' ) . ", 总计费时 {$timeSub} 秒";
			$output->writeln ( $str );
			exit;
		}
		//不是定时任务
		//报表重跑  获得两个参数 ,一个为开始时间
		$startDate = $input->getArgument ( 'start_date' );
		//持续小时
		$durationHour = $input->getArgument ( 'duration_hour' );

		$time = strtotime ( $startDate );
		//重跑$hour个小时 的报表
		for ( $i = $durationHour; $i > 0; $i-- ) {
			//时间
			$this->date = date ( 'Y-m-d', $time );
			//表后数字
			$this->dateYmd = date ( 'Ymd', $time );
			//目前小时
			$this->hour                  = date ( 'H', $time );
			$this->dateWhereBetween[ 0 ] = $this->date . " {$this->hour}:00:00";
			$this->dateWhereBetween[ 1 ] = $this->date . " {$this->hour}:59:59";
			//删除这一个小时的投注统计
			$this->_deleteLog ( $output );
			//定时任务生成上一个小时入库
			$this->_generateLog ( $output );
			//时间加3600秒
			$time += 3600;

			$output->write ( "计划小时{$durationHour},目前计数{$i}" );
		}
		$second = time () - $phpStartTime;
		$output->writeln ( "开始时间" . date ( 'Y-m-d H:i:s', $phpStartTime ) . " ,结束时间" . date ( 'Y-m-d H:i:s' ) . ", 总计费时{$second}秒" );

	}

	private function _generateLog ( Output $output )
	{

		$output->writeln ( "日期:{$this->date} 小时:{$this->hour} - 生成活动小时日志开始...... " );
		//其他获取金币入库
		$this->generateReport ( $output, self::ACTIVITY_OTHER_SOURCES, self::ACTIVITY_OTHER_SOURCES_ID );
		//后台充值
		$this->generateReport ( $output, self::ACTIVITY_BACKGROUND_RECHARGE, self::ACTIVITY_BACKGROUND_RECHARGE_ID );
		//邮件获取金币入库
		$this->generateReport ( $output, self::ACTIVITY_MAIL_GET, self::ACTIVITY_MAIL_GET_ID );
		//运营活动获取的游戏
		foreach ( $this->activityList as $activity_id ) {
			$output->writeln ( "活动ID开始:{$activity_id} " );
			$this->generateReport ( $output, self::ACTIVITY_GET, $activity_id, $activity_id );
		}
		$output->writeln ( "日期:{$this->date} 小时:{$this->hour} - 生成活动小时日志结束...... " );
	}


	private function _deleteLog ( Output $output )
	{
		$output->writeln ( "日期:{$this->date};小时:{$this->hour} - 删除旧数据开始......" );
		$map    = [
			'date_time' => "{$this->date} {$this->hour}:00:00",
		];
		$result = $this->db_report->table ( 'tbl_activity_log' )
		                          ->where ( $map )
		                          ->delete ();
		if ( $result == FALSE ) {
			$output->writeln ( "日期: {$this->date} 小时: {$this->hour} tbl_activity_log 没有数据删除" );
			return;
		}
		$output->writeln ( "日期: {$this->date} 小时: {$this->hour} tbl_activity_log 删除成功" );
	}


	/**
	 * 获取上一个小时 所有参加活动获取金币玩家列表
	 *
	 * @param $activity_id
	 * @param $esrc
	 *
	 * @return array|bool
	 */
	private function getRoleList ( $esrc, $activity_id = NULL )
	{
		//游戏ID为这个 有压注消耗的
		$map[ 'esrc' ] = $esrc; //活动获得金币
		!empty( $activity_id ) && $map[ 'activity_id' ] = $activity_id;//活动id字段 特殊的报表没有活动ID

		//前期玩家不用分页 TODO::后期可能玩家要分页
		$list = $this->db_log->table ( 'tbl_gold_log_' . $this->dateYmd )
		                     ->field ( [ 'role_id' ] )
		                     ->where ( $map )
		                     ->whereBetween ( 'create_time', $this->dateWhereBetween )
		                     ->distinct ( 'true' )
		                     ->select ();

		if ( empty( $list ) ) return FALSE;
		return array_column ( $list, 'role_id' );
	}


	/**
	 * 生成报表
	 *
	 * @param Output $output                object  查询条件
	 * @param        $esrc                  int|array  查询条件
	 * @param        $activity_id_insert    int 插入数据库的活动ID
	 * @param        $activity_id           int 运营活动的ID
	 */
	private function generateReport ( Output $output, $esrc, $activity_id_insert, $activity_id = NULL )
	{
		// 获取时间段内 改游戏的 活动获取金币的玩家列表
		$roleList = $this->getRoleList ( $esrc, $activity_id );
		//这个活动这段时间没有人玩
		if ( empty( $roleList ) ) return;
		//遍历玩家列表  得到该玩家在上个小时 这个游戏  统计的数据
		foreach ( $roleList as $roleId ) {
			$map = [
				'role_id' => $roleId,
				'esrc'    => $esrc,//子类型 活动获得
			];
			!empty( $activity_id ) && $map[ 'activity_id' ] = $activity_id;//活动id字段 特殊的报表没有活动ID
			$add = [
				'date_ymd'    => $this->date,
				'role_id'     => $roleId,
				'activity_id' => $activity_id_insert,
				'date_time'   => $this->date . " {$this->hour}:00:00",
			];

			//spin_cost投注消耗 和 spin_count投注总次数
			$count = $this->db_log->table ( 'tbl_gold_log_' . $this->dateYmd )
			                      ->field ( [
				                      'count(*) as get_count',
				                      'sum(add_gold) as get_gold'
			                      ] )
			                      ->where ( $map )
			                      ->whereBetween ( 'create_time', $this->dateWhereBetween )
			                      ->find ();
			$add   = array_merge ( $add, $count );


			$result = $this->db_report->table ( 'tbl_activity_log' )
			                          ->insert ( $add );
			if ( $result == FALSE ) {

				$output->writeln ( "玩家ID:{$roleId};活动ID:{$activity_id_insert}日志入库失败" );
			}

			// 输出日志
			$msg = '时间: ' . $add[ 'date_time' ];
			$msg .= ' - 会员: ' . $roleId;
			$msg .= ' - 活动: ' . $activity_id_insert;
			$msg .= ' - 获取次数: ' . $add[ 'get_count' ];
			$msg .= ' - 总获取金币: ' . $add[ 'get_gold' ];
			$output->writeln ( $msg );
		}
	}
}