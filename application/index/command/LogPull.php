<?php
/**
 * 定时拉取日志
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/9/6
 * Time: 22:30
 */


namespace app\index\command;

use app\index\model\SignatureHelper;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;
use think\Exception;
use think\Log;
use tp5redis\Redis;
use function GuzzleHttp\json_decode;

/**
 * 定时拉取日志
 * Class LogPull
 * @package app\index\command
 */
class LogPull extends Command
{
	//ESRC 游戏新玩家状态
	const ESRC_GAME_REG = 90;
	//ESRC 小时房间活跃状态
	const ESRC_GAME_ROOM = 91;
	const SERVER_ID = 11;
	const AD_TYPE = [
		//		'a4',
		'a12',
		'a13',
		'a15',
		'a16',
	];
	private $phoneAdminList;
	//12
	//当前操作的表
	private $dateYmdH;
	private $dateYmd;
	//命令触发时间
	private $create_date;

	//center 线上库连接
	private $db_slotdatacenter;
	//自己日志库连接
	private $db_log;
	private $db_dataCenter;
	private $dbCenter;  //中心后台连接
	private $insertArray;

	//本脚本 处理日志上限
    const  MAX_SIZE = 2999;
//	const  MAX_SIZE = 9999999999;
	//每次拉取分页限制
	const SIZE = 1000;
	//	const SIZE = 100;
	//事件对应表
	const EID_TYPE = [
		//		0    => 'server_log',//未知来源日志
		1    => 'tbl_server_log',//服务器启动
		1001 => 'tbl_register_log',//注册
		1002 => 'tbl_login_log',//登录
		1003 => 'tbl_login_log',//登出
		1004 => 'tbl_login_log',//零点事件
		2001 => 'tbl_gold_log',//金币改变
		2002 => 'tbl_exp_log',//经验值改变
		2003 => 'tbl_level_log',//等级改变
		2004 => 'tbl_vip_exp_log',//vip经验值改变
		2005 => 'tbl_vip_level_log',//vip等级改变
		2006 => 'tbl_exp_buff_log',//经验双倍buff
		2007 => 'tbl_upgrade_bonus_buff_log',//升级奖励双倍buff
		3001 => 'tbl_room_log',//进入房间日志
		3002 => 'tbl_room_log',//离开房间日志
		2008 => 'tbl_prop_log', //获得道具
		2101 => 'tbl_email_log',//收到个人邮件
		2102 => 'tbl_email_log',//收到特殊个人邮件
		2103 => 'tbl_email_log',//打开个人邮件
		4001 => 'tbl_activity_log',//运营活动开始
		4002 => 'tbl_activity_log',//运营活动结束
		4003 => 'tbl_activity_log',//运营活动道具
		2010 => 'tbl_currency_log',
		1005 => 'tbl_gold_log',//游戏新增玩家
	];

	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'LogPull' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '读取总日志到分日志命令' )/**
		 * 定义形参
		 * Argument::REQUIRED = 1; 必选
		 * Argument::OPTIONAL = 2;  可选
		 */
		     ->addArgument ( 'cronTab', Argument::OPTIONAL, '是否是定时任务' )
		     ->addArgument ( 'start_date', Argument::OPTIONAL, '开始日期:格式2012-12-12' )
		     ->addArgument ( 'end_date', Argument::OPTIONAL, '结束日期' )// 运行 "php think list" 时的简短描述
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
		// 		$this->redisLockClear ();
		//初始化数据库连接
		$this->db_slotdatacenter = Db::connect ( 'database.slotdatacenter' );
		//		$this->db_slotdatacenter = Db::connect ( 'database.slotdatacenterNew' );
		$this->db_dataCenter = Db::connect ( 'database.datacenter' );

		$this->db_log = Db::connect ( 'database.log' );


		//时间初始化 当天的时间
		$this->create_date = date ( 'Y-m-d H:i:s' );

		//是定时任务 非空
		if ( !empty( $input->getArgument ( 'cronTab' ) ) ) {
			//处理的表明尾缀

			$this->dateYmdH = date ( 'YmdH', strtotime ( '-70 minute' ) );

			$this->dateYmd = date ( 'Ymd', strtotime ( '-70 minute' ) );

			//处理的表明尾缀
			/*$this->dateYmdH = date ( 'YmdH' );
			$this->dateYmd  = date ( 'Ymd' );*/

			//定时任务加锁  防止走进来
			$this->redisLock ( $output );

			$output->writeln ( "---{$this->create_date}定时任务开始:{$this->dateYmdH}---" );
			// 获取最新一条定时 获取总日志记录
			$log = $this->db_log->table ( 'tbl_pull_log' )
			                    ->order ( 'id desc' )
			                    ->find ();

			//如果时间是上一个小时 也就意味着跨表
			if ( $log[ 'table_suffix' ] != $this->dateYmdH ) {
				$Htable = 't_logs_' . $log[ 'table_suffix' ];
				//如果生成数据源日志表不存在
				$exist = $this->db_slotdatacenter->query ( "show tables like '{$Htable}'" );
				if ( empty( $exist ) ) {
					$table_time = strtotime ( '+1 hour', $log[ 'table_time' ] );
					$insert     = [
						'create_date'  => $this->create_date,
						'logs_id'      => 1000,//最小ID是1000开始的 TD
						'table_suffix' => date ( 'YmdH', $table_time ),
						'table_time'   => $table_time,
					];
					//下次的起点
					$result = $this->db_log->table ( 'tbl_pull_log' )
					                       ->insert ( $insert );
					$output->writeln ( "{$Htable}---{$insert['table_suffix']}:此小时段无数据 ,插入tbl_pull_log结果{$result}---" );

					//发送异常短信通知
					echo $this->checkServerLogStatus ( $Htable . '表不存在', $log[ 'create_date' ], $output );

					$this->redisLockClear ();
					exit;
				}

				//获取最新的一条日志id
				$maxLogId = $this->db_slotdatacenter->table ( "t_logs_" . $log[ 'table_suffix' ] )
				                                    ->max ( 'id' );
				//如果之前的 处理结束ID + 2999 <任然小于 最后一条 那么只处理3000条
				if ( ( $log[ 'logs_id' ] + self::MAX_SIZE ) < $maxLogId ) {
					//只更改logsID和任务时间 继续插入
					$insert = [
						'create_date'  => $this->create_date,
						'table_suffix' => $log[ 'table_suffix' ],//尾缀时间YmdH格式
						'table_time'   => $log[ 'table_time' ], //尾缀时间转时间戳
						'logs_id'      => $log[ 'logs_id' ] + self::MAX_SIZE + 1,
					];
					//拉取日志超过上限
					$maxLogId = $log[ 'logs_id' ] + self::MAX_SIZE;

				}
				else {
					//第二条的第一条开始 为1
					$table_time = strtotime ( '+1 hour', $log[ 'table_time' ] );
					$insert     = [
						'create_date'  => $this->create_date,
						'logs_id'      => 1000,//TD从1000开始
						'table_suffix' => date ( 'YmdH', $table_time ),
						'table_time'   => $table_time,
					];
				}
				//插入代表这一天的日志跑完了
				$this->db_log->table ( 'tbl_pull_log' )
				             ->insert ( $insert );
				$output->writeln ( "---{$log['table_suffix']}是跨小时日志已经插入最新读取logId---" );

				//处理表数据 这是昨天的 需要放到昨天的日志
				$this->handleLog ( "t_logs_" . $log[ 'table_suffix' ], $log, $maxLogId, $output );

			}
			else {
				$Htable = 't_logs_' . $log[ 'table_suffix' ];
				//生成数据源日志表 如果表不存在
				$exist = $this->db_slotdatacenter->query ( "show tables like '{$Htable}'" );

				if ( empty( $exist ) ) {
					$output->writeln ( "test---{$Htable}当前表不存在:此小时段无数据 ,没有操作---" );
					//发送异常短信通知
					echo $this->checkServerLogStatus ( '表不存在', $log[ 'create_date' ], $output );
					$this->redisLockClear ();
					exit;
				}
				//跑的是当前一小时 读取正在操作的总日志表最大Id
				$maxLogId = $this->db_slotdatacenter->table ( "t_logs_" . $this->dateYmdH )
				                                    ->max ( 'id' );

				if ( $maxLogId == NULL ) {
					$output->writeln ( "---没有日志是异常的,本次定时任务提前结束---" );
					//发送异常短信通知
					echo $this->checkServerLogStatus ( '无日志 ', $log[ 'create_date' ], $output );
					$this->redisLockClear ();
					exit;
				}
				elseif ( $log[ 'logs_id' ] > $maxLogId ) {
					//					dump ( $log[ 'logs_id' ] );
					$output->writeln ( "---没有日志新增,本次定时任务提前结束---" );
					$this->redisLockClear ();
					exit;
				}
				elseif ( ( $log[ 'logs_id' ] + self::MAX_SIZE ) < $maxLogId ) {
					//					//如果本次拉取的数据超过了上限
					$maxLogId = $log[ 'logs_id' ] + self::MAX_SIZE;
				}

				//获取
				$insert = [
					'create_date'  => $this->create_date,
					'logs_id'      => $maxLogId + 1, //下次起点高一个
					'table_suffix' => $log[ 'table_suffix' ],//尾缀时间YmdH格式
					'table_time'   => $log[ 'table_time' ], //尾缀时间转时间戳
				];

				//下次的起点
				$this->db_log->table ( 'tbl_pull_log' )
				             ->insert ( $insert );
				$output->writeln ( "---{$this->create_date}插入当天最新读取logId---" );
				//接收最大的ID
				$insert[ 'logs_id' ] = $this->handleLog ( "t_logs_" . $this->dateYmdH, $log, $maxLogId, $output );
			}
			$output->writeln ( "---本次定时任务结束---" );


			$js_date = date ( 'Y-m-d H:i:s' );
			$sj      = time () - strtotime ( $this->create_date );
			$output->writeln ( "开始时间{$this->create_date} ,结束时间{$js_date}, 总计费时{$sj}秒" );
			//redis解锁
			$this->redisLockClear ();
			exit;
		}
		//		重新读取报表
		//		TODO::先清空那些日志表
		//		TODO::当天的数据不做处理

		//获取传输的时间
		$start_date = $input->getArgument ( 'start_date' );
		//持续小时
		$durationHour = $input->getArgument ( 'end_date' );

		$time = strtotime ( $start_date );

		//做主要逻辑 重新读取报表
		for ( $i = $durationHour; $i > 0; $i-- ) {
			//TODO::日志表清空
			$this->dateYmd = date ( 'Ymd', $time );

			//TODO::数据重新插入
			$this->dateYmdH = date ( 'YmdH', $time );

			$Htable = 't_logs_' . $this->dateYmdH;
			$exist  = $this->db_slotdatacenter->query ( "show tables like '{$Htable}'" );
			if ( empty( $exist ) ) {
				$time += 3600;
				$output->writeln ( "{$this->dateYmdH}不存在" );
				continue;
			}
			$log[ 'logs_id' ]      = 1000;
			$log[ 'table_time' ]   = $time;
			$log[ 'table_suffix' ] = $this->dateYmd;
			$this->handleLog ( "t_logs_" . $this->dateYmdH, $log, 9999999999, $output );
			$start_date = date ( 'Y-m-d', strtotime ( "+1 day", strtotime ( $start_date ) ) );
			$time       += 3600;
		}

		$js_date = date ( 'Y-m-d H:i:s' );
		$sj      = time () - strtotime ( $this->create_date );
		$output->writeln ( "开始时间{$this->create_date} ,结束时间{$js_date}, 总计{$sj}秒" );
	}

	/**
	 * 获取插入的表格
	 *
	 * @param $eid
	 * @param $date
	 *
	 * @return mixed
	 */
	private static function getInsertTable ( $eid, $date )
	{
		$date_int = date ( 'Ymd', strtotime ( $date ) );
		$eidType  = [
			0    => 'tbl_server_log',//未知来源日志
			1    => 'tbl_server_log',//服务器启动
			1001 => 'tbl_register_log',//注册
			1002 => 'tbl_login_log',//登录
			1003 => 'tbl_login_log',//登出
			1004 => 'tbl_login_log',//零点
			2003 => 'tbl_level_log',//等级改变
			2005 => 'tbl_vip_level_log',//vip等级改变
			2007 => 'tbl_upgrade_bonus_buff_log',//升级奖励双倍buff
			2006 => 'tbl_exp_buff_log',//经验双倍buff

			2008 => 'tbl_prop_log', //获得道具 广告观看
			2002 => 'tbl_exp_log_' . $date_int,//经验值改变
			2001 => 'tbl_gold_log_' . $date_int,//金币改变
			2004 => 'tbl_vip_exp_log_' . $date_int,//vip经验值改变
			3001 => 'tbl_room_log_' . $date_int,//进入房间日志
			3002 => 'tbl_room_log_' . $date_int,//离开房间日志
			2101 => 'tbl_email_log_' . $date_int,//收到个人邮件
			2102 => 'tbl_email_log_' . $date_int,//收到特殊个人邮件
			2103 => 'tbl_email_log_' . $date_int,//打开个人邮件

			4001 => 'tbl_activity_log_' . $date_int,//活动开始
			4002 => 'tbl_activity_log_' . $date_int,//活动结束
			4003 => 'tbl_activity_log_' . $date_int,//活动道具获得
			2010 => 'tbl_currency_log_' . $date_int, //币数据

			1005 => 'tbl_gold_log_' . $date_int,//游戏新增 放入金币改变
		];
		return $eidType[ $eid ];
	}

	private function handleLog ( string $table, array $log, int $maxId, Output $output )
	{
		//计算这次处理的条数
		$count = $maxId - $log[ 'logs_id' ];
		//走到这里 如果条数等于0 说明只有一条记录
		if ( $count == 0 ) {
			$count = 1;
		}
		//计算分多少页
		$pageCount = ceil ( $count / self::SIZE );
		$output->writeln ( date ( 'Y-m-d H:i:s' ) . "本次共处理页数{$pageCount},起ID{$log['logs_id']},束ID{$maxId}" );
		//分页 页数小于总页数 页数上面再加一 代表开始的
		for ( $page = 1; $page <= $pageCount; $page++ ) {
			//分页的上行
			$lowerLimit = $log[ 'logs_id' ] + ( self::SIZE * ( $page - 1 ) );
			//如果是最后一页 分页的最小ID
			if ( $page == $pageCount ) {
				$upperLimit = $maxId;
			}
			else {
				$upperLimit = $lowerLimit + self::SIZE - 1;
			}


			//获取一千条总日志 分别插入其他日志0
			$logs = $this->db_slotdatacenter->table ( $table )
			                                ->field ( [
				                                'date',
				                                'sid',
				                                'roleid',
				                                'eid',
				                                'esrc',
				                                'p1',
				                                'p2',
				                                'p3',
				                                'p4',
				                                'p5',
				                                'p6',
				                                'ps1',
				                                'ps2',
				                                'ps3'
			                                ] )
			                                ->whereBetween ( 'id', [ $lowerLimit, $upperLimit ] )
			                                ->select ();

			if ( empty( $logs ) ) {
				$output->writeln ( "t_logs_{$this->dateYmdH}没有数据,本次返回" );
				return FALSE;
			}
			$output->writeln ( date ( 'Y-m-d H:i:s' ) . "拉取完毕" );
			$table_suffix = date ( 'Ymd', $log[ 'table_time' ] );

			/*
						dump ($table_suffix);
						sleep (3);*/
			//原始数据 直接入库
			$result = $this->db_dataCenter->table ( "t_logs_{$table_suffix}" )
			                              ->insertAll ( $logs );
			if ( !empty( $result ) ) {
				$output->writeln ( "t_logs_{$log[ 'table_suffix' ]}源数据入库成功 {$result}条" );
			}

			//日志入库分日志
			foreach ( $logs as $value ) {
				//   var_dump($value);
				//是已知的类型 入库
				if ( array_key_exists ( $value[ 'eid' ], self::EID_TYPE ) ) {
					//表名做键名
					$this->insertArray[ self::getInsertTable ( $value[ 'eid' ], $value[ 'date' ] ) ][] = $this->LogToValue ( $value[ 'eid' ], $value );

					//如果是登陆 更新注册 最后登陆时间 IP
					if ( $value[ 'eid' ] == 1002 ) {
						$result2 = $this->db_log->table ( 'tbl_register_log' )
						                        ->where ( [ 'role_id' => $value[ 'roleid' ] ] )
						                        ->update ( [
							                        'last_ip'    => $value[ 'ps1' ],
							                        'last_date'  => $value[ 'date' ],
							                        'last_grade' => $value[ 'p2' ],
						                        ] );
						if ( $result2 == 0 ) {
							//异常的用户?丢失登陆信息
						}
						//更新注册的最后登陆时间
						$output->writeln ( "玩家{$value['roleid']}最后登陆时间{$value['date']}登陆更新结果{$result2}" );
					}
					elseif ( $value[ 'esrc' ] == 104 && $value[ 'ps1' ] == 'a4' ) {
						$this->insertArray[ 'tbl_ad_log_' . date ( 'Ymd', strtotime ( $value[ 'date' ] ) ) ][] = [
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
					elseif ( $value[ 'eid' ] == 2008 && $value[ 'esrc' ] == 104 && in_array ( $value[ 'ps1' ], self::AD_TYPE ) ) {
						$this->insertArray[ 'tbl_ad_log_' . date ( 'Ymd', strtotime ( $value[ 'date' ] ) ) ][] = [
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
					elseif ( $value[ 'eid' ] == 2001 && $value[ 'esrc' ] == 501 ) {

						if ( empty( $value[ 'ps1' ] ) ){
							$this->insertArray[ 'tbl_h5_pay_order' ][] = [
								'sid'             => $value[ 'sid' ],
								'role_id'         => $value[ 'roleid' ],
								'eid'             => $value[ 'eid' ],
								'esrc'            => $value[ 'esrc' ],
								'create_time'     => $value[ 'date' ],
								'add_gold'        => $value[ 'p1' ],
								'change_gold'     => $value[ 'p2' ],
								'level'           => 0,
								'hall_id'         => 0,
								'channel_version' => 0,
								'channel_id'      => 0,
								'vip_level'       => 0,
							];
						}
						else {
							//h5充值
							$info                                      = json_decode ( $value[ 'ps1' ], TRUE );
							$this->insertArray[ 'tbl_h5_pay_order' ][] = [
								'sid'             => $value[ 'sid' ],
								'role_id'         => $value[ 'roleid' ],
								'eid'             => $value[ 'eid' ],
								'esrc'            => $value[ 'esrc' ],
								'create_time'     => $value[ 'date' ],
								'add_gold'        => $value[ 'p1' ],
								'change_gold'     => $value[ 'p2' ],
								'level'           => $info[ 'level' ] ?? 0,
								'hall_id'         => $info[ 'hall_id' ] ?? 0,
								'channel_version' => $info[ 'channel_version' ] ?? 0,
								'channel_id'      => $info[ 'channel_id' ] ?? 0,
								'vip_level'       => $info[ 'vip_level' ] ?? 0,
							];
						}
						unset( $info );
					}
				}
			}


			//已经组装好的数据入库
			foreach ( $this->insertArray as $insertTable => $insertAll ) {
				//   var_dump ($insertTable);
				$result = $this->db_log->table ( $insertTable )
				                       ->insertAll ( $insertAll );
				$output->writeln ( $insertTable . '表插入' . $result );
			}

			$this->insertArray = [];
			$output->writeln ( date ( 'Y-m-d H:i:s' ) . "第{$page}页,ID起{$lowerLimit},束ID{$upperLimit}" );
		}
		return TRUE;
	}


	private function LogToValue ( $eid, $value )
	{
		switch ( $eid ) {
			//注册
			case 1001:
				//注册补一个登陆
				$this->insertArray[ 'tbl_login_log' ][] = [
					'sid'         => $value[ 'sid' ],
					'role_id'     => $value[ 'roleid' ],
					'eid'         => 1002, //登陆
					'esrc'        => $value[ 'esrc' ],
					'create_time' => $value[ 'date' ],
					'last_ip'     => $this->analyJson($value[ 'ps1' ] ,'ip')?? '127.0.0.1',
					//					'last_ip'     => $this->analyJson(( $value[ 'ps1' ]) ??json_decode ( $value[ 'ps1' ], TRUE )[ 'ip' ],
					'channel_id'  => $value[ 'p1' ],

				];

				return [
					'sid'         => $value[ 'sid' ],
					'role_id'     => $value[ 'roleid' ],
					'eid'         => $value[ 'eid' ],
					'esrc'        => $value[ 'esrc' ],
					'create_time' => $value[ 'date' ],
					'update_time' => $value[ 'date' ],
					'channel_id'  => $value[ 'p1' ],
					'json_info'   => $value[ 'ps1' ],
				];
			//登录表
			case 1002: //登陆
			case 1003: //登出
			case 1004: //零点
				return [
					'sid'         => $value[ 'sid' ],
					'role_id'     => $value[ 'roleid' ],
					'eid'         => $value[ 'eid' ],
					'esrc'        => $value[ 'esrc' ],
					'create_time' => $value[ 'date' ],
					'last_ip'     => $value[ 'ps1' ],
					'channel_id'  => $value[ 'p1' ],

					//					'last_grade'  => $value[ 'p1' ],//TD还没加上
				];
			//金币表
			case 2001: //金币改变
				return [
					'sid'               => $value[ 'sid' ],
					'role_id'           => $value[ 'roleid' ],
					'eid'               => $value[ 'eid' ],
					'esrc'              => $value[ 'esrc' ],
					'create_time'       => $value[ 'date' ],
					'add_gold'          => $value[ 'p1' ], //改变的金币
					'change_gold'       => $value[ 'p2' ], //变化后金币
					'level'             => $value[ 'p3' ], //等级
					'game_id'           => $value[ 'p4' ], //游戏
					'small_game_number' => $value[ 'p5' ], //小游戏次数
					'activity_id'       => $value[ 'ps1' ], //活动ID];
				];
			//经验值表;
			case 2002:
				return [
					'sid'         => $value[ 'sid' ],
					'role_id'     => $value[ 'roleid' ],
					'eid'         => $value[ 'eid' ],
					'esrc'        => $value[ 'esrc' ],
					'create_time' => $value[ 'date' ],
					'add_exp'     => $value[ 'p1' ],
					'change_exp'  => $value[ 'p2' ],
					'add_level'   => $value[ 'p3' ],
					'game_id'     => $value[ 'p4' ],
				];
			//等级改变
			case 2003:
				return [
					'sid'          => $value[ 'sid' ],
					'role_id'      => $value[ 'roleid' ],
					'eid'          => $value[ 'eid' ],
					'esrc'         => $value[ 'esrc' ],
					'create_time'  => $value[ 'date' ],
					'add_level'    => $value[ 'p1' ],
					'change_level' => $value[ 'p2' ],
					'game_id'      => $value[ 'p3' ],
				];
			//vip经验值改变
			case 2004:
				return [
					'sid'         => $value[ 'sid' ],
					'role_id'     => $value[ 'roleid' ],
					'eid'         => $value[ 'eid' ],
					'esrc'        => $value[ 'esrc' ],
					'create_time' => $value[ 'date' ],
					'add_exp'     => $value[ 'p1' ],
					'change_exp'  => $value[ 'p2' ],
					'add_level'   => $value[ 'p3' ],
				];
			//vip等级改变
			case 2005:
				return [
					'sid'          => $value[ 'sid' ],
					'role_id'      => $value[ 'roleid' ],
					'eid'          => $value[ 'eid' ],
					'esrc'         => $value[ 'esrc' ],
					'create_time'  => $value[ 'date' ],
					'add_level'    => $value[ 'p1' ],
					'change_level' => $value[ 'p2' ],
					'activity_id'  => $value[ 'ps1' ],
				];

			//经验双倍buff
			case 2006:
				return [
					'sid'         => $value[ 'sid' ],
					'role_id'     => $value[ 'roleid' ],
					'eid'         => $value[ 'eid' ],
					'esrc'        => $value[ 'esrc' ],
					'create_time' => $value[ 'date' ],
					'add_time'    => $value[ 'p1' ],
					'change_time' => $value[ 'p2' ],
					'activity_id' => $value[ 'ps1' ],
				];
			//等级奖励双倍领取buf时间延长
			case 2007:
				return [
					'sid'         => $value[ 'sid' ],
					'role_id'     => $value[ 'roleid' ],
					'eid'         => $value[ 'eid' ],
					'esrc'        => $value[ 'esrc' ],
					'create_time' => $value[ 'date' ],
					'add_time'    => $value[ 'p1' ],
					'due_time'    => $value[ 'p2' ],
					'activity_id' => $value[ 'ps1' ],

				];
			//进入房间日志
			case 3001: //进房间
			case 3002: //出房间

				//补一个金币房间放进出日志 统计进入房间人数
				$this->insertArray[ self::getInsertTable ( 2001, $value[ 'date' ] ) ][] = [
					'sid'               => $value[ 'sid' ],
					'role_id'           => $value[ 'roleid' ],
					'eid'               => $value[ 'eid' ],
					'esrc'              => self::ESRC_GAME_ROOM,
					'create_time'       => $value[ 'date' ],
					'add_gold'          => 0, //改变的金币
					'change_gold'       => 0, //变化后金币
					'level'             => 0, //游戏
					'game_id'           => $value[ 'p1' ], //游戏
					'small_game_number' => 0, //小游戏次数
					'activity_id'       => 0, //活动ID];
				];


				return [
					'sid'         => $value[ 'sid' ],
					'role_id'     => $value[ 'roleid' ],
					'eid'         => $value[ 'eid' ],
					'esrc'        => $value[ 'esrc' ],
					'create_time' => $value[ 'date' ],
					'game_id'     => $value[ 'p1' ],
					'gold'        => $value[ 'p2' ],
				];
			//获得道具
			case 2008:
				return [
					'sid'         => $value[ 'sid' ],
					'role_id'     => $value[ 'roleid' ],
					'eid'         => $value[ 'eid' ],
					'esrc'        => $value[ 'esrc' ],
					'create_time' => $value[ 'date' ],
					'number'      => $value[ 'p1' ],
					'prop_id'     => $value[ 'ps1' ],
					'activity_id' => $value[ 'ps2' ],
				];
			//邮件日志
			case 2101: //收到个人邮件
			case 2102: //收到个人特殊邮件
			case 2103: //打开邮件
				return [
					'sid'         => $value[ 'sid' ],
					'role_id'     => $value[ 'roleid' ],
					'eid'         => $value[ 'eid' ],
					'esrc'        => $value[ 'esrc' ],
					'create_time' => $value[ 'date' ],
					'msgid'       => $value[ 'p1' ],
					'info'        => $value[ 'ps1' ],
					'activity_id' => $value[ 'ps2' ],
				];

			case 4001: //运营活动开始
			case 4002: //运营活动结束
			case 4003: //运营活动道具获得
				return [
					"create_time" => $value[ 'date' ],
					"sid"         => $value[ 'sid' ],
					"role_id"     => $value[ 'roleid' ],
					"eid"         => $value[ 'eid' ],
					"esrc"        => $value[ 'esrc' ],
					"level"       => $value[ 'p3' ],
					"activity_id" => $value[ 'ps1' ],
					"json_info"   => $value[ 'ps2' ],
					"add_gold"    => $value[ 'p1' ],
					"change_gold" => $value[ 'p2' ],
				];
			//宝石币表
			case 2010: //宝石币改变
				return [
					'sid'         => $value[ 'sid' ],
					'role_id'     => $value[ 'roleid' ],
					'eid'         => $value[ 'eid' ],
					'esrc'        => $value[ 'esrc' ],
					'create_time' => $value[ 'date' ],
					'add_gold'    => $value[ 'p1' ], //改变的币
					'change_gold' => $value[ 'p2' ], //变化后币
					'level'       => $value[ 'p3' ], //等级
					'game_id'     => $value[ 'p4' ], //游戏
				];
			//游戏新增
			case 1005:
				//补一个金币房间放进出日志 统计进入房间人数
				return [
					'sid'               => $value[ 'sid' ],
					'role_id'           => $value[ 'roleid' ],
					'eid'               => $value[ 'eid' ],
					'esrc'              => self::ESRC_GAME_REG,
					'create_time'       => $value[ 'date' ],
					'add_gold'          => 0, //改变的金币
					'change_gold'       => 0, //变化后金币
					'level'             => 0, //等级
					'game_id'           => $value[ 'p1' ], //游戏
					'small_game_number' => 0, //小游戏次数
					'activity_id'       => 0, //活动ID];
				];
			default:
				return $value;
		}
	}

	/**
	 * redis锁 防止走进来
	 *
	 * @param Output $output
	 */
	private function redisLock ( Output $output )
	{
		//redis订单锁 判断  key time value
		if ( Redis::exists ( 'logPull-crontab' ) ) {
			$output->writeln ( "{$this->create_date}logPull-crontab定时任务异常" );
			exit;
		}
		//订单锁 时间600秒
		$result = Redis::setex ( 'logPull-crontab', 600, '1' );

		//设置失败
		if ( !$result ) {
			$output->writeln ( "{$this->create_date}logPull-crontab定时任务锁设置失败" );
			exit;
		}
	}


	//清除缓存
	private function redisLockClear ()
	{
		Redis::del ( 'logPull-crontab' );
	}


	//发送异常短信通知
	private function checkServerLogStatus ( $msg, $lastLogTime, Output $output )
	{
		if ( Config::get ( 'site.environment' ) != 'test' ) {
			$this->dbCenter = Db::connect ( 'database.center' );
			$output->write ( $msg . '该时间段没有任何数据' );
			return $this->serverErrorSendMessage ( self::SERVER_ID, $lastLogTime, $msg );
		}
		$output->write ( $msg . '该时间段没有任何数据' );
		return '测试不通知服务器消息';
	}


	/**
	 * @param        $server_id
	 * @param        $lastLogTime
	 * @param string $type
	 *
	 * @return string
	 */
	private function serverErrorSendMessage ( $server_id, $lastLogTime, string $type )
	{
		$where = [
			'sid'       => $server_id,
			'send_time' => [ 'EGT', $lastLogTime ],
		];
		//先从短信表查有没有短信
		$count = $this->dbCenter->table ( 'tbl_server_msg' )
		                        ->where ( $where )
		                        ->count ();


		Log::write ( "已有条数{$count} ;最近一条正常日志 {$lastLogTime}", 'server' );

		if ( $count == 0 || $count == NULL ) {

			if ( $this->checkLastMsgOneDay () ) {
				return "success : 最近一天有通知过 {$type}了{$count}次";
			}

			//获取通知人
			$this->getPhoneAdmin ();
			$this->dateYmd = date ( 'Y-m-d H:i:s' );
			//发送信息
			$result = $this->sendMsg ();

			if ( $result->Code != 'OK' ) {
				Log::write ( '异常通知失败' . json_encode ( $result ), 'server' );
				return "异常通知失败" . json_encode ( $result );
				//状态异常 写错误日志
			}
			//发送成功,写短信发送记录
			$string = $this->insertServerMsg ( "{$type}异常,最近一条正常通知信息时间{$lastLogTime},这次通知是第1次", $server_id );


			Log::write ( $string, 'server' );
			return "{$type}异常,这次通知是第1次";

		}

		return "success : 此服务器消息已经通知{$type}了{$count}次";
	}

	//写短信发送记录
	private function insertServerMsg ( $msg, $sid )
	{

		$insert = [
			'msg'       => $msg,
			'send_time' => $this->dateYmd,
			'sid'       => $sid,
			'phone_num' => '',
			'name'      => '',
		];
		foreach ( $this->phoneAdminList as $phoneAdmin ) {
			$insert[ 'phone_num' ] .= '-' . $phoneAdmin[ 'phone_num' ];
			$insert[ 'name' ]      .= '-' . $phoneAdmin[ 'name' ];
		}

		//日志插入
		return $this->phoneAdminList = $this->dbCenter->table ( 'tbl_server_msg' )
		                                              ->insert ( $insert );
	}

	//发送短信
	private function sendMsg ()
	{

		/*	//TODO::测
			$result       = (object)[];
			$result->Code = 'OK';
			return $result;*/


		// *** 需用户填写部分 ***
		//    必填：是否启用https
		$security = FALSE;
		//    必填: 请参阅 https://ak-console.aliyun.com/ 取得您的AK信息
		$accessKeyId     = "LTAI4GHuPYiamYsvVpJmgSx9";
		$accessKeySecret = "hrlcAtQbZbhKdWdbcCodQc9Gy11af4";

		//    必填: 待发送手机号。支持JSON格式的批量调用，批量上限为100个手机号码,批量调用相对于单条调用及时性稍有延迟,验证码类型的短信推荐使用单条调用的方式
		//    必填: 短信模板Code，应严格按"模板CODE"填写, 请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/template
		$params[ "TemplateCode" ] = "SMS_212486653";

		//    必填: 模板中的变量替换JSON串,如模板内容为"亲爱的${name},您的验证码为${code}"时,此处的值为
		// 友情提示:如果JSON中需要带换行符,请参照标准的JSON协议对换行符的要求,比如短信内容中包含\r 的情况在JSON中需要表示成\\r\ ,否则会导致JSON在服务端解析失败


		foreach ( $this->phoneAdminList as $phoneAdmin ) {
			$params[ "TemplateParamJson" ][] = [
				"sever"    => "亚马逊数据统计拉取",
				"time"     => $this->dateYmd,
				"timezone" => '美东'
			];
			$params[ "SignNameJson" ][]      = "酷鸽服务器状态";
			$params[ "PhoneNumberJson" ][]   = $phoneAdmin[ 'phone_num' ];
		}

		// *** 需用户填写部分结束, 以下代码若无必要无需更改 ***
		$params[ "TemplateParamJson" ] = json_encode ( $params[ "TemplateParamJson" ], JSON_UNESCAPED_UNICODE );
		$params[ "SignNameJson" ]      = json_encode ( $params[ "SignNameJson" ], JSON_UNESCAPED_UNICODE );
		$params[ "PhoneNumberJson" ]   = json_encode ( $params[ "PhoneNumberJson" ], JSON_UNESCAPED_UNICODE );

		if ( !empty( $params[ "SmsUpExtendCodeJson" ] ) && is_array ( $params[ "SmsUpExtendCodeJson" ] ) ) {
			$params[ "SmsUpExtendCodeJson" ] = json_encode ( $params[ "SmsUpExtendCodeJson" ], JSON_UNESCAPED_UNICODE );
		}

		// 初始化SignatureHelper实例用于设置参数，签名以及发送请求
		$helper = new SignatureHelper();

		// 此处可能会抛出异常，注意catch
		$content = $helper->request ( $accessKeyId, $accessKeySecret, "dysmsapi.aliyuncs.com", array_merge ( $params, [
			"RegionId" => "cn-hangzhou",
			"Action"   => "SendBatchSms",
			"Version"  => "2017-05-25",
		] ), $security );

		return $content;
	}

	//获取手机联系人信息
	private function getPhoneAdmin ()
	{
		$this->phoneAdminList = $this->dbCenter->table ( 'tbl_server_admin' )
		                                       ->select ();


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
		if (empty($ps2)){
			return 0;
		}
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


	private function checkLastMsgOneDay ()
	{
		$msg = $this->dbCenter->table ( 'tbl_server_msg' )
		                      ->order ( 'id desc' )
		                      ->find ();

		//如果最近一条短信离现在不足一天 不需要再发送异常短信
		if ( ( time () - strtotime ( $msg[ 'send_time' ] ) ) < 86400 ) {
			return TRUE;
		}
		return FALSE;
	}


	/**
	 * 解析json串 返回目标字段
	 * @param  $json_str
	 * @return
	 */
	function analyJson($json_str ,$key) {
		try{
			$data = json_decode ($string ,true);
		}
		catch(Exception $e){
			return  '127.0.0.1';
		}
		return  $data[$key];
	}
}

