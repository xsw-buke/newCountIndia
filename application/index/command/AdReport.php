<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/3/3
 * Time: 15:41
 */

namespace app\index\command;


use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;
use think\Log;

class AdReport extends Command
{
	//日期
	const PAGE_SIZE = 300;
	const AD_TYPE = [
		'a4',
		'a12',
		'a13',
		'a15',
		'a16',
	];

	const LEVEL = [
		[ 1, 5 ],
		[ 6, 10 ],
		[ 11, 15 ],
		[ 16, 20 ],
		[ 21, 25 ],
		[ 16, 20 ],
		[ 31, 35 ],
		[ 36, 40 ],
		[ 41, 45 ],
		[ 46, 50 ],
		[ 61, 65 ],
		[ 16, 20 ],
		[ 21, 25 ],
		[ 16, 20 ],
		[ 21, 25 ],
		[ 16, 20 ],
		[ 21, 25 ],
		[ 16, 20 ],
		[ 21, 25 ],
	];
	const PAY_TRUE = 1;
	const PAY_TRUE_WHERE = [ 'GT', 0 ];
	const PAY_STATUS_FALSE = 2;
	const PAY_FALSE = 0;
	private $date;
	private $dateYmd;
	private $db_report;
	private $db_log;
	private $db_newPortal;
	private $server_list;

	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'AdReport' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '广告日报表' )/**
		 * 定义形参
		 * Argument::REQUIRED = 1; 必选
		 * Argument::OPTIONAL = 2; 可选
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

			//生成这个天入库  一级报表处理 php /www/wwwroot/newCount/think AdReport 1
			$this->_generateLog ( $output );
			//生成这天的广告分布 二级报表处理
			$this->_generateReport ( $output );
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

			//生成这天的广告分布
			$this->_generateReport ( $output );
			//时间加1天
			$start_date = date ( 'Y-m-d', strtotime ( "+1 day", strtotime ( $start_date ) ) );
		}

		$second = time () - $phpStartTime;
		$output->writeln ( "开始时间" . date ( 'Y-m-d H:i:s', $phpStartTime ) . " ,结束时间" . date ( 'Y-m-d H:i:s' ) . ", 总计费时{$second}秒" );

	}

	/**
	 * 生成广告报表记录
	 *
	 * @param Output $output
	 *
	 * @return bool
	 */
	private function _generateLog ( Output $output )
	{
		$output->writeln ( "日期:{$this->date}    - 生成玩家/天日志开始...... " );
		foreach ( $this->server_list as $server ) {
			//总广告人数
			$count = $this->db_log->table ( 'tbl_ad_log_' . $this->dateYmd )
			                      ->field ( [ 'role_id' ] )
			                      ->where ( [
					                      'ad_type' => [ 'in', self::AD_TYPE ],
					                      'sid'     => $server[ 'id' ],//加服务器
				                      ]

			                      )
			                      ->distinct ( 'true' )
			                      ->count ();
			if ( empty( $count ) ) return FALSE;

			//做分片处理
			$pageCount = ceil ( $count / self::PAGE_SIZE );


			for ( $page = 1; $page <= $pageCount; $page++ ) {
				// 获取时间段有看广告的会员
				$roleList = $list = $this->db_log->table ( 'tbl_ad_log_' . $this->dateYmd )
				                                 ->field ( [ 'role_id', 'sid' ] )
				                                 ->where ( [ 'sid' => $server[ 'id' ] ] )
				                                 ->page ( $page, self::PAGE_SIZE )
				                                 ->distinct ( 'true' )
				                                 ->select ();


				//单个玩家处理
				foreach ( $roleList as $role ) {
					$map = [
						'role_id' => $role[ 'role_id' ],
						'sid'     => $server[ 'id' ]
					];
					//这个玩家的付费状态
					$payStatus = $this->db_log->table ( 'tbl_ad_log_' . $this->dateYmd )
					                          ->where ( $map )
					                          ->max ( 'pay_status' );

					$channel_id  = $this->getChannelIdByRoleId ( $role );
					$addAll      = [];
					$addSumCount = 0;
					// 每种类型
					foreach ( self::AD_TYPE as $TYPE ) {
						$map[ 'ad_type' ] = $TYPE;
						$adCount          = $this->db_log->table ( 'tbl_ad_log_' . $this->dateYmd )
						                                 ->where ( $map )
						                                 ->count ();

						if ( empty( $adCount ) ) {
							continue;
						}
						$addAll[]    = [
							'sid'        => $server[ 'id' ],
							'date'       => $this->date,
							'type'       => $TYPE,
							'role_id'    => $role[ 'role_id' ],
							'ad_count'   => $adCount,
							'pay_status' => $payStatus,
							'channel_id' => $channel_id,
							'count'      => 0,
						];
						$addSumCount += $adCount;
					}
					//总计加入
					$addAll[] = [
						'sid'        => $server[ 'id' ],
						'date'       => $this->date,
						'type'       => 'count',
						'role_id'    => $role[ 'role_id' ],
						'ad_count'   => $addSumCount,
						'pay_status' => $payStatus,
						'channel_id' => $channel_id,
						'count'      => 1,
					];
					$result   = $this->db_report->table ( 'tbl_ad_report' )
					                            ->insertAll ( $addAll );
					if ( $result == FALSE ) {
						$output->writeln ( "玩家ID:{$role['role_id']} 玩家日报入库失败" );
					}
					$msg = "玩家{$role['role_id']}{$this->date}日统计:{$result}" . json_encode ( $addAll );

					unset( $addAll );
					$output->writeln ( $msg );
				}
			}
		}
		$output->writeln ( "日期:{$this->date}    - 生成玩家/天广告日志结束 " );
		return TRUE;
	}


	private function _deleteLog ( Output $output )
	{
		$output->writeln ( "日期:{$this->date};   - 删除旧数据开始......" );
		$map = [
			'date' => $this->date,
		];
		//删除报表
		$result = $this->db_report->table ( 'tbl_ad_report' )
		                          ->where ( $map )
		                          ->delete ();

		if ( $result == FALSE ) {
			$output->writeln ( "日期: {$this->date}   tbl_ad_report 没有数据删除" );
		}
		//删除报表
		$result = $this->db_report->table ( 'tbl_ad_spread_report' )
		                          ->where ( $map )
		                          ->delete ();

		if ( $result == FALSE ) {
			$output->writeln ( "日期: {$this->date}   tbl_ad_report 没有数据删除" );
		}
		$output->writeln ( "日期: {$this->date}   tbl_ad_report 删除成功" );
	}

	private function _generateReport ( Output $output )
	{
		//获取所有渠道 分渠道统计
		$channelIds = $this->db_newPortal->table ( 'tp_channel' )
		                                 ->field ( 'channel_id' )
		                                 ->group ( 'channel_id' )
		                                 ->select ();

		foreach ( $channelIds as $channel ) {
			$insertAll = [];
			//每个类型
			for ( $level = 1; $level <= 20; $level++ ) {
				foreach ( self::AD_TYPE as $type ) {
					//每个级别 支付
					$find = $this->db_report->table ( 'tbl_ad_report' )
					                        ->field ( [
						                        'count(distinct role_id) as role_count',
						                        'sum(ad_count) as ad_count',
						                        'sum(pay_status) as pay_status', //单个类型的总计 也不会偏差
					                        ] )
					                        ->where ( [
						                        'date'       => $this->date,
						                        'type'       => $type,
						                        'pay_status' => self::PAY_TRUE_WHERE,//付款金额大于0的
						                        'channel_id' => $channel[ 'channel_id' ],
						                        'count'      => 0,   //非统计ad数据
					                        ] )
					                        ->whereBetween ( 'ad_count', [
						                        $level * 5 - 4,
						                        $level * 5
					                        ] )
					                        ->find ();

					if ( !empty( $find ) ) {
						$insertAll[] = [
							'date'       => $this->date,
							'level'      => $level,
							'type'       => $type,
							'ad_count'   => $find[ 'ad_count' ] ?? 0,
							'role_count' => $find[ 'role_count' ] ?? 0,
							'pay_money'  => $find[ 'pay_status' ] ?? 0, //支付状态 0  或者累加
							'pay_status' => 1, //支付状态 0  或者累加
							'channel_id' => $channel[ 'channel_id' ],
						];

					}

					$find = $this->db_report->table ( 'tbl_ad_report' )
					                        ->field ( [
						                        'count(distinct role_id) as role_count',
						                        'sum(ad_count) as ad_count'
					                        ] )
					                        ->where ( [
						                        'date'       => $this->date,
						                        'type'       => $type,
						                        'pay_status' => self::PAY_FALSE,
						                        'channel_id' => $channel[ 'channel_id' ],
						                        'count'      => 0,   //非统计ad数据
					                        ] )
					                        ->whereBetween ( 'ad_count', [
						                        $level * 5 - 4,
						                        $level * 5
					                        ] )
					                        ->find ();
					if ( !empty( $find ) ) {
						$insertAll[] = [
							'date'       => $this->date,
							'level'      => $level,
							'type'       => $type,
							'ad_count'   => $find[ 'ad_count' ] ?? 0,
							'role_count' => $find[ 'role_count' ] ?? 0,
							'pay_money'  => 0,
							'pay_status' => 2, //未付费
							'channel_id' => $channel[ 'channel_id' ],
						];

					}
					//渠道每个类型走完
				}

				$find        = $this->db_report->table ( 'tbl_ad_report' )
				                               ->field ( [
					                               'count(distinct role_id) as role_count',
					                               'sum(ad_count) as ad_count',
					                               'sum(pay_status) as pay_status', //单个类型的总计 也不会偏差
				                               ] )
				                               ->where ( [
					                               'date'       => $this->date,
					                               'pay_status' => self::PAY_TRUE_WHERE,//付款金额大于0的
					                               'channel_id' => $channel[ 'channel_id' ],
					                               'count'      => 0,   //非统计ad数据
				                               ] )
				                               ->whereBetween ( 'ad_count', [
					                               $level * 5 - 4,
					                               $level * 5
				                               ] )
				                               ->find ();
				$insertAll[] = [
					'date'       => $this->date,
					'level'      => $level,
					'type'       => 'count',
					'ad_count'   => $find[ "ad_count" ] ?? 0,
					'role_count' => $find[ "role_count" ] ?? 0,
					'pay_money'  => $find[ "pay_status" ] ?? 0,
					'pay_status' => 1, //付费
					'channel_id' => $channel[ 'channel_id' ],
				];


				$find        = $this->db_report->table ( 'tbl_ad_report' )
				                               ->field ( [
					                               'count(distinct role_id) as role_count',
					                               'sum(ad_count) as ad_count'
				                               ] )
				                               ->where ( [
					                               'date'       => $this->date,
					                               'pay_status' => self::PAY_FALSE,
					                               'channel_id' => $channel[ 'channel_id' ],
					                               'count'      => 0,   //非统计ad数据
				                               ] )
				                               ->whereBetween ( 'ad_count', [
					                               $level * 5 - 4,
					                               $level * 5
				                               ] )
				                               ->find ();
				$insertAll[] = [
					'date'       => $this->date,
					'level'      => $level,
					'type'       => 'count',
					'ad_count'   => $find[ 'ad_count' ] ?? 0,
					'role_count' => $find[ 'role_count' ] ?? 0,
					'pay_money'  => 0,
					'pay_status' => 2, //未付费
					'channel_id' => $channel[ 'channel_id' ],
				];
				$result      = $this->db_report->table ( 'tbl_ad_spread_report' )
				                               ->insertAll ( $insertAll );


				if ( $result == FALSE ) {
					$output->writeln ( "玩家ID:{$channel['channel_id']} AD分布日报入库失败" );
				}
				$msg = "{$this->date}日统计渠道:{$channel['channel_id']} 入库结果 :{$result}";
				unset( $insertAll );
				$output->writeln ( $msg );
			}
		}

	}

	//获取这个玩家的channel_id
	private function getChannelIdByRoleId ( $role )
	{
		$result = $this->db_log->table ( 'tbl_register_log' )
		                       ->where ( $role )
		                       ->find ();
		if ( !empty( $result ) ) {
			return $result[ 'channel_id' ];
		}

		//如果有了就不插入 避免唯一重复
		$result2 = $this->db_log->table ( 'tbl_register_lose' )
		                        ->where ( $role )
		                        ->find ();
		if ( !empty( $result2 ) ) {
			return 0;
		}

		$result2 = $this->db_log->table ( 'tbl_register_lose' )
		                        ->insert ( $role );
		if ( empty( $result2 ) ) {
			Log::write ( '补登插入异常' . json_encode ( $role ), 'crontab' );
		}
		//记录到异常表
		return 0;
	}


}