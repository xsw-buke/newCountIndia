<?php
/**
 * 游戏报表
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/9/7
 * Time: 20:33
 */

namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;


class GameReport extends Command
{


	//压注消耗
	const SPIN_COST = 201;
	//压注获
	const SPIN_GET = 202;
	//freeSpin 获
	const FREE_SPIN_GET = 203;
	//freeSpin 获得游戏道具, 小游戏获
	const  TINY_GAME_GET = 204;
	//freeSpinTinyGameGet
	const  FREE_SPIN_TINY_GAME_GET = 205;
	//游戏新玩家状态
	const  NEW_GAME_STATUS = 90;
	//小时房间活跃状态
	const  ROOM_STATUS = 91;
	//所有押注类型
	const SPIN_ROLE_ESRC = [
		201,
		202,
		203,
		204,
		205,
		90, //游戏新增
		91, //进出房间
	];

	//改变值
	const  CHANGE_FIELD = 'add_gold';
	const TYPE_NEW_USER = 1;
	const TYPE_OLD_USER = 2;
	const TYPE_MESSAGE = [
		1 => '新玩家',
		2 => '老玩家',
	];
	const NEW_LEVEL = 20;
	//	const SUB_UTC = 13; //冬令时
	const SUB_UTC = 12;//夏令时

	//日期
	private $date;
	//日期数字 找表用的
	private $dateYmd;
	private $hour;
	private $dateWhereBetween;
	private $gameList;
	private $db_report;
	private $db_log;
	private $insertData;
	private $map;
	private $utc8Ymd;
	private $utc8hour;
	private $utc8date;

	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'GameReport' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '生成游戏小时报表' )/**
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
		//		$this->db_log = Db::connect ( 'database.log' ); //线上用
		$this->db_log = Db::connect ( 'database.log' ); //测试用


		//游戏列表的获取
		$gameList       = Db::connect ( 'database.portal' )
		                    ->table ( 'tp_game' )
		                    ->field ( 'game_id' )
		                    ->distinct ( TRUE )
		                    ->select ();
		$this->gameList = array_column ( $gameList, 'game_id' );

		//是定时任务
		if ( !empty( $input->getArgument ( 'cronTab' ) ) ) {
			//延迟时间改为2小时
			$time = strtotime ( '-2 hour' );
			//时间
			$this->date = date ( 'Y-m-d', $time );
			//表后数字
			$this->dateYmd = date ( 'Ymd', $time );
			//目前小时
			$this->hour = date ( 'H', $time );
			//减少13小时的ymd
			$utc8time                    = bcadd ( $time, self::SUB_UTC * 3600 );
			$this->utc8Ymd               = date ( 'Ymd', $utc8time );
			$this->utc8hour              = date ( 'H', $utc8time );
			$this->utc8date              = date ( 'Y-m-d', $utc8time );
			$this->dateWhereBetween[ 0 ] = $this->date . " {$this->hour}:00:00";
			$this->dateWhereBetween[ 1 ] = $this->date . " {$this->hour}:59:59";
			//删除这一个小时的投注统计
			$this->_deleteLog ( $output );
			//定时任务生成上一个小时入库
			$this->_generateLog ( $output );

			$timeSub = time () - $phpStartTime;
			$str     = "开始时间" . date ( 'Y-m-d H:i:s', $phpStartTime ) . " ,结束时间" . date ( 'Y-m-d H:i:s' ) . ", 总计费时 {$timeSub} 秒";
			$output->writeln ( $str );
			return TRUE;
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
			$this->hour = date ( 'H', $time );
			//减少13小时的ymd
			$utc8time       = bcadd ( $time, self::SUB_UTC * 3600 );
			$this->utc8Ymd  = date ( 'Ymd', $utc8time );
			$this->utc8hour = date ( 'H', $utc8time );
			$this->utc8date = date ( 'Y-m-d', $utc8time );

			$this->dateWhereBetween[ 0 ] = $this->date . " {$this->hour}:00:00";
			$this->dateWhereBetween[ 1 ] = $this->date . " {$this->hour}:59:59";


			//删除这一个小时的投注统计
			$this->_deleteLog ( $output );


			//定时任务生成上一个小时入库
			$this->_generateLog ( $output );
			//时间加3600秒
			$time += 3600;
		}
		$second = time () - $phpStartTime;
		$output->writeln ( "开始时间" . date ( 'Y-m-d H:i:s', $phpStartTime ) . " ,结束时间" . date ( 'Y-m-d H:i:s' ) . ", 总计费时{$second}秒" );
		die;
	}

	private function _generateLog ( Output $output )
	{

		$output->writeln ( "日期:{$this->date} 小时:{$this->hour} - 生成游戏小时日志开始...... " );
		//遍历服务器操作
		$server_list = Db::connect ( 'database.uwinslot' )
		                 ->table ( 'tp_server' )
		                 ->field ( 'id' )
		                 ->select ();
		//   $server_list= [['id'=>6]];

		//		dump ($this->gameList);die;
		foreach ( $server_list as $server ) {
			$output->writeln ( "服务器id{$server['id']}开始...... " );

			//$this->gameList  = [910037];
			//迭代游戏
			foreach ( $this->gameList as $game_id ) {
				//   dump($server['id']);
				// 获取时间段内 该游戏的压注玩家列表
				$roleList = $this->getRoleList ( $game_id, $server[ 'id' ] );

				/*
				if ( !empty( $roleList ) ) {
			    	dump ($roleList);
				    sleep(1);
				}*/
				// continue;
				//这个游戏这段时间没有人玩
				if ( empty( $roleList ) ) continue;

				//遍历玩家列表  得到该玩家在上个小时 这个游戏  统计的数据
				foreach ( $roleList as $roleInfo ) {
					//新玩家  等级小于等于20
					$this->map = [
						'role_id' => $roleInfo[ 'role_id' ],
						'esrc'    => self::SPIN_COST,//压注
						'level'   => [
							'ELT',//小于等于
							self::NEW_LEVEL
						],
						'game_id' => $game_id,
						'sid'     => $server[ 'id' ]
					];
					//判断新玩家 老玩家
					$this->insertData = [
						'date_ymd'       => $this->date,
						'role_id'        => $roleInfo[ 'role_id' ],
						'sid'            => $roleInfo[ 'sid' ],
						'game_id'        => $game_id,
						'date_time'      => $this->date . " {$this->hour}:00:00",
						'utc8_date_ymd'  => $this->utc8Ymd,
						'utc8_date_time' => $this->utc8date . " {$this->utc8hour}:00:00",
					];
					//新玩家统计入库
					$this->userCount ( $output, $roleInfo[ 'role_id' ], $game_id, self::TYPE_NEW_USER, $roleInfo[ 'sid' ] );

					$this->map = [
						'role_id' => $roleInfo[ 'role_id' ],
						'game_id' => $game_id,
						'sid'     => $server[ 'id' ],
						'esrc'    => self::SPIN_COST,//压注
						'level'   => [
							'GT', //大于
							self::NEW_LEVEL
						],
					];
					//判断新玩家 老玩家
					$this->insertData = [
						'date_ymd'       => $this->date,
						'role_id'        => $roleInfo[ 'role_id' ],
						'sid'            => $roleInfo[ 'sid' ],
						'game_id'        => $game_id,
						'date_time'      => $this->date . " {$this->hour}:00:00",
						'utc8_date_ymd'  => $this->utc8Ymd,
						'utc8_date_time' => $this->utc8date . " {$this->utc8hour}:00:00",
					];
					//老玩家统计入库
					$this->userCount ( $output, $roleInfo[ 'role_id' ], $game_id, self::TYPE_OLD_USER, $roleInfo[ 'sid' ] );
				}
				//获取所有游戏
			}
			$output->writeln ( " {$server[ 'id' ]}服务器完毕...... " );
		}
		$output->writeln ( " 本天定时任务结束...... " );
	}

	/**
	 * 获取上一个小时 所有游戏玩家列表
	 *
	 * @param $game_id
	 *
	 * @return array|bool
	 */
	private function getRoleList ( $game_id, $server_id )
	{
		//游戏ID为这个 有压注消耗的
		$map = [
			'game_id' => $game_id,
			'sid'     => $server_id,
			'esrc'    =>
				[ 'IN', self::SPIN_ROLE_ESRC ]

			, //压注消耗 等等
		];
		//前期玩家不用分页
		$list = $this->db_log->table ( 'tbl_gold_log_' . $this->dateYmd )
		                     ->field ( [ 'role_id', 'sid' ] )
		                     ->where ( $map )
		                     ->whereBetween ( 'create_time', $this->dateWhereBetween )
		                     ->distinct ( 'true' )
		                     ->select ();
		if ( empty( $list ) ) return FALSE;

		return $list;

	}

	private function _deleteLog ( Output $output )
	{
		$output->writeln ( "日期:{$this->date};小时:{$this->hour} - 删除旧数据开始......" );
		$map    = [
			'date_time' => "{$this->date} {$this->hour}:00:00",
		];
		$result = $this->db_report->table ( 'tbl_game_log' )
		                          ->where ( $map )
		                          ->delete ();
		if ( $result == FALSE ) {
			$output->writeln ( "日期: {$this->date} 小时: {$this->hour} tbl_game_log 没有数据删除" );
			return;
		}
		$output->writeln ( "日期: {$this->date} 小时: {$this->hour} tbl_game_log 删除成功" );
	}

	private function userCount ( Output $output, $role_id, $game_id, $type, $server_id )
	{

		$this->insertData[ 'type' ] = $type;
		//spin_cost投注消耗 和 spin_count投注总次数
		$count = $this->db_log->table ( 'tbl_gold_log_' . $this->dateYmd )
		                      ->field ( [
			                      'count(*) as spin_count',
			                      'sum(add_gold) as spin_cost'
		                      ] )
		                      ->where ( $this->map )
		                      ->whereBetween ( 'create_time', $this->dateWhereBetween )
		                      ->find ();
		/*	if ( $count[ 'spin_count' ] == 0 ) {
				//说明玩家这时候这个等级分类不存在  直接跳过
				return FALSE;
			}
	*/
		$this->insertData  [ 'spin_cost' ] = abs ( intval ( $count[ 'spin_cost' ] ) );
		$this->insertData [ 'spin_count' ] = $count[ 'spin_count' ];
		//压注获得
		$this->map[ 'esrc' ]            = self::SPIN_GET;
		$this->insertData[ 'spin_get' ] = $this->db_log->table ( 'tbl_gold_log_' . $this->dateYmd )
		                                               ->field ( self::CHANGE_FIELD )
		                                               ->where ( $this->map )
		                                               ->whereBetween ( 'create_time', $this->dateWhereBetween )
		                                               ->sum ( self::CHANGE_FIELD );
		//FREE_SPIN_GET 获得金币 和次数
		$this->map[ 'esrc' ] = self::FREE_SPIN_GET;
		$free_spin_count     = $this->db_log->table ( 'tbl_gold_log_' . $this->dateYmd )
		                                    ->field ( [
			                                    'count(*) as free_spin_count',
			                                    'sum(add_gold) as free_spin_get'
		                                    ] )
		                                    ->where ( $this->map )
		                                    ->whereBetween ( 'create_time', $this->dateWhereBetween )
		                                    ->find ();


		$this->insertData[ 'free_spin_get' ]   = intval ( $free_spin_count[ 'free_spin_get' ] );
		$this->insertData[ 'free_spin_count' ] = $free_spin_count[ 'free_spin_count' ];


		//小游戏获取得
		$this->map[ 'esrc' ]                   = self::TINY_GAME_GET;
		$tiny_game_get                         = $this->db_log->table ( 'tbl_gold_log_' . $this->dateYmd )
		                                                      ->field ( [
			                                                      'count(*) as tiny_game_count',
			                                                      'sum(add_gold) as tiny_game_get'
		                                                      ] )
		                                                      ->where ( $this->map )
		                                                      ->whereBetween ( 'create_time', $this->dateWhereBetween )
		                                                      ->find ();
		$this->insertData[ 'tiny_game_get' ]   = intval ( $tiny_game_get[ 'tiny_game_get' ] );
		$this->insertData[ 'tiny_game_count' ] = $tiny_game_get[ 'tiny_game_count' ];
		//FREE_SPIN_TINY_GAME_GET 小游戏获得
		$this->map[ 'esrc' ]                             = self::FREE_SPIN_TINY_GAME_GET;
		$free_spin_tiny_game_count                       = $this->db_log->table ( 'tbl_gold_log_' . $this->dateYmd )
		                                                                ->field ( [
			                                                                'count(*) as free_spin_tiny_game_count',
			                                                                //免费spin小游戏次数
			                                                                'sum(add_gold) as free_spin_tiny_game_get'
		                                                                ] )
		                                                                ->where ( $this->map )
		                                                                ->whereBetween ( 'create_time', $this->dateWhereBetween )
		                                                                ->find ();
		$this->insertData[ 'free_spin_tiny_game_get' ]   = intval ( $free_spin_tiny_game_count[ 'free_spin_tiny_game_get' ] );
		$this->insertData[ 'free_spin_tiny_game_count' ] = $free_spin_tiny_game_count[ 'free_spin_tiny_game_count' ];

		$get_sum = array_sum ( [
			$this->insertData[ 'spin_get' ],
			$this->insertData[ 'free_spin_get' ],
			$this->insertData[ 'tiny_game_get' ],
			$this->insertData[ 'free_spin_tiny_game_get' ],
		] );
		if ( $get_sum == 0 ) {
			$this->insertData[ 'tb' ] = 0;
		}
		else {
			$this->insertData[ 'tb' ] = 0;

			//WIN MONEY / SPIN MONEY  /次数
			/*bcdiv (
			bcdiv ( $get_sum, $this->insertData[ 'spin_cost' ], 2 ),
			$this->insertData[ 'spin_count' ], 2 );*/

			// bcdiv ( $get_sum, $this->insertData[ 'spin_cost' ], 2 );
		}
		//游戏新玩家状态
		$this->map['esrc'] = self::NEW_GAME_STATUS;

		$newGameStatus =$this->db_log->table ( 'tbl_gold_log_' . $this->dateYmd )

		             ->where ( $this->map )
		             ->whereBetween ( 'create_time', $this->dateWhereBetween )
		             ->find ();
		if (empty($newGameStatus)){
			$this->insertData['new_game_status']  = null ;
		}else{
			$this->insertData['new_game_status']  = $this->map['role_id'] ;
		}

		//小时玩家活跃状态
		$this->map['esrc'] = self::ROOM_STATUS;
		$roomStatus =$this->db_log->table ( 'tbl_gold_log_' . $this->dateYmd )
		                             ->where ( $this->map )
		                             ->whereBetween ( 'create_time', $this->dateWhereBetween )
		                             ->find ();
		if (empty($roomStatus)){
			$this->insertData['room_status']  = null ;
		}else{
			$this->insertData['room_status']  = $this->map['role_id']  ;
		}


		$result = $this->db_report->table ( 'tbl_game_log' )
		                          ->insert ( $this->insertData );
		if ( $result == FALSE ) {
			$output->writeln ( self::TYPE_MESSAGE[ $type ] . ";的ID:{$role_id};游戏ID:{$game_id};日志入库失败" );
		}

		// 输出日志
		$msg = '日期: ' . $this->date . '小时:' . $this->hour;
		$msg .= ' - 会员: ' . $role_id;
		$msg .= ' - 游戏: ' . $game_id;
		$msg .= ' - 玩家类型: ' . self::TYPE_MESSAGE[ $type ];
		$msg .= ' - 投注次数: ' . $this->insertData[ 'spin_count' ];
		$msg .= ' - 总投注: ' . $this->insertData[ 'spin_cost' ];
		$msg .= ' - 投注获得: ' . $this->insertData[ 'spin_get' ];
		$msg .= ' - 免费压注获得: ' . $this->insertData[ 'free_spin_get' ];
		$msg .= ' - 小游戏获得: ' . $this->insertData[ 'tiny_game_get' ];
		$msg .= ' - 免费压注小游戏获得: ' . $this->insertData[ 'free_spin_tiny_game_get' ];
		$msg .= ' - 免费压注次数: ' . $this->insertData[ 'free_spin_count' ];
		$msg .= ' - 小游戏次数: ' . $this->insertData[ 'tiny_game_count' ];
		$msg .= ' - 免费压注小游戏次数: ' . $this->insertData[ 'free_spin_tiny_game_count' ];
		$msg .= ' - 游戏新玩家状态: ' . $this->insertData[ 'new_game_status' ];
		$msg .= ' - 小时玩家进入状态: ' . $this->insertData[ 'room_status' ];
		$output->writeln ( $msg );
		return TRUE;
	}

}