<?php


namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class YiKuLtvReport extends Command
{
	//亿酷服务器定制报表
	const SID = 25;
	//渠道id
	const CHANNEL_ID = 25001;
	const PAY_STATUS_TRUE = 1; //支付状态1
	private $date;
	private $db_report;
	private $db_log;
	private $db_center;

	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'YiKuLtvReport' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '生成游戏小时报表' )/**
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
		$phpStartTime = time ();
		//数据库连接初始化
		$this->db_report    = Db::connect ( 'database.report' );
		$this->db_log       = Db::connect ( 'database.log' );
		$this->db_newPortal = Db::connect ( 'database.portal' );
		$this->db_center    = Db::connect ( 'database.center' );
		//获取所有服务器操作
		$this->server_list = Db::connect ( 'database.uwinslot' )
		                       ->table ( 'tp_server' )
		                       ->field ( 'id' )
		                       ->select ();
		//是定时任务
		if ( !empty( $input->getArgument ( 'cronTab' ) ) ) {
			$time = strtotime ( '-1 day' );
			//时间
			$this->date = date ( 'Y-m-d', $time );

			//表后数字
			$this->dateYmd = date ( 'Ymd', $time );
			//删除这一天的广告统计和分布
			$this->_deleteLog ( $output );

			//生成这个天入库
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
			$this->date = date ( 'Y-m-d', strtotime ( $start_date ) );
			//表后数字
			$this->dateYmd = date ( 'Ymd', strtotime ( $start_date ) );
			//删除这天的投注统计
			$this->_deleteLog ( $output );

			//定时任务生成上一个天入库
			$this->_generateLog ( $output );

			//时间加1天
			$start_date = date ( 'Y-m-d', strtotime ( "+1 day", strtotime ( $start_date ) ) );
		}

		$second = time () - $phpStartTime;
		$output->writeln ( "开始时间" . date ( 'Y-m-d H:i:s', $phpStartTime ) . " ,结束时间" . date ( 'Y-m-d H:i:s' ) . ", 总计费时{$second}秒" );

	}

	private function _deleteLog ( Output $output )
	{
		$output->writeln ( "日期:{$this->date};   - 删除旧数据开始......" );
		$map = [
			'date' => $this->date,
		];
		//删除报表
		$result = $this->db_report->table ( 'tbl_ltv_report' )
		                          ->where ( $map )
		                          ->delete ();

		if ( $result == FALSE ) {
			$output->writeln ( "日期: {$this->date}   tbl_ltv_report 没有数据删除" );
		}

		$output->writeln ( "日期: {$this->date}   tbl_ltv_report 删除成功" );
	}

	function _generateLog ( Output $output )
	{
		//留存靠联表
		$insert = [
			'sid'            => self::SID,
			'channel_id'     => self::CHANNEL_ID,
			'date'           => $this->date,
			'login_num'      => 0,//活跃用户数量
			'pay_count'      => 0,//付款笔数
			'pay_money_sum'  => 0,//付费金额总数
			'role_pay_count' => 0,//付费用户数
			'role_pay_svg'   => 0,//活跃用户付费率
			'arpu'           => 0, //所有用户平均付款
			'arppu'          => 0,    //付费用户平均付款
		];
		//查询活跃 就是登录用户 去重
		$where = [
			'channel_id'  => self::CHANNEL_ID,
			'create_time' => [
				'between',
				[
					$this->date . ' 00:00:00',
					$this->date . ' 23:59:59'
				],
			]
		];


		$where2 = [
			'channel_id' => self::CHANNEL_ID,
			'pay_status' => self::PAY_STATUS_TRUE,
			'date_time' => [
				'between',
				[
					$this->date . ' 00:00:00',
					$this->date . ' 23:59:59'
				],
			]
		];
		$field2 = [
			'count(*) as pay_count', //订单数
			'sum(price) as pay_money_sum', //总订单金额
			'count(distinct role_id) as role_count', //有效人数
		];
		//支付查询
		$payData = $this->db_center->table ( 'tbl_pay_order_log_huawei' )
		                           ->field ( $field2 )
		                           ->where ( $where2 )
		                           ->select ();
		if ( empty( $payData[ 'pay_count' ] ) ) {
			$insert[ 'pay_count' ] = intval ( $payData[ 'pay_count' ] );
		}
		if ( empty( $payData[ 'pay_money_sum' ] ) ) {
			$insert[ 'pay_money_sum' ] = $payData[ 'pay_money_sum' ];
		}
		if ( empty( $payData[ 'role_count' ] ) ) {
			$insert[ 'role_pay_count' ] = intval ( $payData[ 'role_count' ] );
		}

		//登录人数
		$loginRole = $this->db_log->table ( 'tbl_login_log' )
		                          ->where ( $where )
		                          ->count ( 'distinct role_id' );

		if ( $loginRole ) {
			$insert[ 'login_num' ] = $loginRole;
			//活跃付费率
			$insert[ 'role_pay_svg' ] = bcdiv ( $insert[ 'role_pay_count' ], $insert[ 'login_num' ], 4 );
			//ARPU=总收入/活跃用户数
			$insert[ 'arpu' ] = bcdiv ( $insert[ 'pay_money_sum' ], $insert[ 'login_num' ], 4 );
			//平均每付费用户收入，计算公式：总收入/活跃付费用户数
			$insert[ 'arppu' ] = bcdiv ( $insert[ 'pay_money_sum' ], $insert[ 'login_num' ], 4 );
		}
		$result = $this->db_report->table('tbl_ltv_report')->insert($insert);
		if (empty($result)){
			$output->writeln ( "日期:{$this->date}  - 生成ltv日志异常!!!!! " );
		}
		$output->writeln ( "日期:{$this->date}  - 生成ltv日志结束...... " );
	}
}