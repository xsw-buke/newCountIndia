<?php
/**
 * 读取总日志到各个分日志中
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/9/6
 * Time: 22:30
 */


namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class ReadGeneralLog extends Command
{

	//12
	//当前操作的表
	private $dateYmd;
	//命令触发时间
	private $cmd_date_time;

	//center 线上库连接
	private $db_slotdatacenter;
	//自己日志库连接
	private $db_log;
	private $db_dataCenter;
	private $insertArray;

	//本脚本 处理日志上限
	//	const  MAX_SIZE = 2999;
	const  MAX_SIZE = 9999999999;
	//每次拉取分页限制
	const SIZE = 1000;
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
	];

	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'ReadGeneralLog' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
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
		//初始化数据库连接
		//		$this->db_slotdatacenter = Db::connect ( 'database.slotdatacenter' );

//		$this->db_slotdatacenter = Db::connect ( 'database.datacenter' );
		$this->db_slotdatacenter = Db::connect ( 'database.slotdatatest' );
		//		$this->db_dataCenter     = Db::connect ( 'database.datacenter' );


		$this->db_log = Db::connect ( 'database.log' );
		//时间初始化
		$this->dateYmd       = date ( 'Ymd' );
		$this->cmd_date_time = date ( 'Y-m-d H:i:s' );
		//是定时任务
		$output->writeln ( "---{$this->dateYmd}日定时任务开始---" );
		// 获取最新一条定时 获取总日志记录
		$log = $this->db_log->table ( 'tbl_read_logs_log' )
		                    ->order ( 'id desc' )
		                    ->find ();

		//如果时间不是今天 也就意味着跨表 		//把前一天日志跑完 ,添加日期今天
		if ( $log[ 'date_int' ] != $this->dateYmd ) {
			//获取最新的一条日志id
			$maxLogId = $this->db_slotdatacenter->table ( "t_logs_" . $log[ 'date_int' ] )
			                                    ->max ( 'id' );

			//如果之前的 处理结束ID + 2999 <任然小于 最后一条 那么只处理3000条
			if ( ( $log[ 'logs_id' ] + self::MAX_SIZE ) < $maxLogId ) {
				$insert = [
					'create_time' => $this->cmd_date_time,
					'date_int'    => $log[ 'date_int' ],
					'logs_id'     => $log[ 'logs_id' ] + self::MAX_SIZE + 1,
				];
				//拉取日志超过上限
				$maxLogId = $log[ 'logs_id' ] + self::MAX_SIZE;
			}
			else {
				//TODO:: +86400  问题所在点 改成加一天半 完美跨过 一个小时的夏令时问题了
				//第二条的第一条开始 为1
				$insert = [
					'create_time' => $this->cmd_date_time,
					'date_int'    => date ( 'Ymd', strtotime ( $log[ 'date_int' ] ) + 129600 ),
					'logs_id'     => 1,
				];
			}

			//追加tbl_read_logs_log的记录日志 跨天  下次id 为  0+1
			//插入代表这一天的日志跑完了
			$this->db_log->table ( 'tbl_read_logs_log' )
			             ->insert ( $insert );

			$output->writeln ( "---{$log['date_int']}是跨天日志已经插入最新读取logId---" );

			//处理表数据 这是昨天的 需要放到昨天的日志
			$this->handleLog ( "t_logs_" . $log[ 'date_int' ], $log, $maxLogId, $output );
		}
		else {
			//跑的是今天 读取正在操作的总日志表最大Id
			$maxLogId = $this->db_slotdatacenter->table ( "t_logs_" . $this->dateYmd )
			                                    ->max ( 'id' );
			if ( $maxLogId == NULL ) {
				$output->writeln ( "---今天没有日志,本次定时任务提前结束---" );
				exit;
			}
			elseif ( $log[ 'logs_id' ] > $maxLogId ) {
				$output->writeln ( "---没有日志新增,本次定时任务提前结束---" );
				exit;
			}
			elseif ( ( $log[ 'logs_id' ] + self::MAX_SIZE ) < $maxLogId ) {
				//					//如果本次拉取的数据超过了上限
				$maxLogId = $log[ 'logs_id' ] + self::MAX_SIZE;
			}
			//获取
			$insert = [
				'create_time' => $this->cmd_date_time,
				'date_int'    => date ( 'Ymd', strtotime ( $this->dateYmd ) ),
				'logs_id'     => $maxLogId + 1, //下次起点高一个
			];
			//下次的起点
			$this->db_log->table ( 'tbl_read_logs_log' )
			             ->insert ( $insert );
			$output->writeln ( "---{$this->cmd_date_time}插入当天最新读取logId---" );
			//接收最大的ID
			$insert[ 'logs_id' ] = $this->handleLog ( "t_logs_" . $this->dateYmd, $log, $maxLogId, $output );
		}
		$output->writeln ( "---本次定时任务结束---" );


		$js_date = date ( 'Y-m-d H:i:s' );
		$sj      = time () - strtotime ( $this->cmd_date_time );
		$output->writeln ( "开始时间{$this->cmd_date_time} ,结束时间{$js_date}, 总计费时{$sj}秒" );
		exit;

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
			2008 => 'tbl_prop_log', //获得道具
			2006 => 'tbl_exp_buff_log',//经验双倍buff

			2002 => 'tbl_exp_log_' . $date_int,//经验值改变
			2001 => 'tbl_gold_log_' . $date_int,//金币改变
			2004 => 'tbl_vip_exp_log_' . $date_int,//vip经验值改变
			3001 => 'tbl_room_log_' . $date_int,//进入房间日志
			3002 => 'tbl_room_log_' . $date_int,//离开房间日志
			2101 => 'tbl_email_log_' . $date_int,//收到个人邮件
			2102 => 'tbl_email_log_' . $date_int,//收到特殊个人邮件
			2103 => 'tbl_email_log_' . $date_int,//打开个人邮件
		];
		return $eidType[ $eid ];
	}


	/**
 * @param string $table
 * @param array  $log
 * @param int    $maxId
 * @param Output $output
 */
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

		$output->writeln ( "本次共处理页数{$pageCount},起ID{$log['logs_id']},束ID{$maxId}" );
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
				$output->writeln ( "t_logs_{$this->dateYmd}没有数据,本次返回" );
				continue;
			}

			/*	//原始数据 直接入库
				$result = $this->db_dataCenter->table ( "t_logs_{$log['date_int']}" )
											  ->insertAll ( $logs );
				if ( !empty( $result ) ) {
					$output->writeln ( "t_logs_{$this->dateYmd}源数据入库成功 {$result}条" );
				}*/


			//日志入库分日志
			foreach ( $logs as $value ) {
				//是已知的类型 入库
				if ( array_key_exists ( $value[ 'eid' ], self::EID_TYPE ) ) {
					//表名做键名
					$this->insertArray[ self::getInsertTable ( $value[ 'eid' ], $value[ 'date' ] ) ][] = $this->LogToValue ( $value[ 'eid' ], $value );
					//如果是登陆 更新注册 最后登陆时间 IP
					if ( $value[ 'eid' ] == 1002 ) {
						$result2 = $this->db_log->table ( 'tbl_register_log' )
						                        ->where ( [ 'role_id' => $value[ 'roleid' ] ] )
						                        ->update ( [
							                        'last_ip'   => $value[ 'ps1' ],
							                        'last_date' => $value[ 'date' ],
						                        ] );
						if ( $result2 == 0 ) {
							//异常的用户?丢失登陆信息
						}
						//更新注册的最后登陆时间
						$output->writeln ( "玩家{$value['roleid']}最后登陆时间{$value['date']}登陆更新结果{$result2}" );
					}
				}
			}


			//已经组装好的数据入库
			foreach ( $this->insertArray as $insertTable => $insertAll ) {
				$result = $this->db_log->table ( $insertTable )
				                       ->insertAll ( $insertAll );
				$output->writeln ( $insertTable . '表插入' . $result );
			}

			$this->insertArray = [];
			$output->writeln ( "第{$page}页,ID起{$lowerLimit},束ID{$upperLimit}" );
		}
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
					'last_ip'     => json_decode ( $value[ 'ps1' ], TRUE )[ 'ip' ],
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
					//					'last_grade'  => $value[ 'p1' ],//TD还没加上
				];
			//金币表
			case 2001: //金币改变
				return [
					'sid'         => $value[ 'sid' ],
					'role_id'     => $value[ 'roleid' ],
					'eid'         => $value[ 'eid' ],
					'esrc'        => $value[ 'esrc' ],
					'create_time' => $value[ 'date' ],
					'add_gold'    => $value[ 'p1' ], //改变的金币
					'change_gold' => $value[ 'p2' ], //变化后金币
					'level'       => $value[ 'p3' ], //等级
					'game_id'     => $value[ 'p4' ], //游戏
					'activity_id' => $value[ 'ps1' ], //活动ID];
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
			default:
				return $value;
		}
	}

}