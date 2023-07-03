<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/3/29
 * Time: 17:40
 */


namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;
use think\Log;

class SvgUserGold extends Command
{
	private $query;
	private $AllCount;
	private $insertAll;
	private $rankRoleCount;
	const TYPE = [
		'all'    => [ 'chargeNum' => [ '>=', 0 ] ],
		'unpaid' => [ 'chargeNum' => 0 ],
		'small'  => [
			'chargeNum' => [
				[ '>', 0 ],
				[ '<=', 1000 ],
				'and'
			]
		],
		'big'    => [ 'chargeNum' => [ '>', 1000 ] ],
	];

	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'SvgUserGold' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
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
		bcscale ( 6 );
		//		$this->db = Db::connect ( 'database.pay' );
		//总人数获取
		$this->AllCount = Db::connect ( 'database.pay' )
		                    ->table ( 'ccc' )
		                    ->count ();

		for ( $min = 20; $min < 399; $min += 10 ) {
			//每个人数档次
			$this->insertLogOne ( $min );
		}
	}

	private function insertLogOne ( $min )
	{
		//平均数  每小段中玩家占比情况
		//全部
		$avg                 = Db::connect ( 'database.pay' )
		                         ->table ( 'ccc' )
		                         ->whereBetween ( 'level', [ $min, $min + 9 ] )
		                         ->field ( [
			                         'avg(money) as avg_money',
			                         'count(*) as count',
			                         'sum(money) as sum_money'
		                         ] )
		                         ->find ();
		$this->rankRoleCount = $avg[ 'count' ] ?? 0;
		if ( !empty( $avg[ 'avg' ] ) ) {
			return FALSE;
		}
		$insert = [
			'level'           => $min,
			'avg_money'       => $avg[ 'avg_money' ],
			'count'           => $avg[ 'count' ],
			'sum_money'       => $avg[ 'sum_money' ],
			'all_count'       => $this->AllCount,
			'role_proportion' => bcdiv ( $avg[ 'count' ], $this->AllCount ),
			'type'            => 'all'
		];
		$result = Db::connect ( 'database.pay' )
		            ->table ( 'avg_user_gold' )
		            ->insert ( $insert );
		//未付费
		$avg = Db::connect ( 'database.pay' )
		         ->table ( 'ccc' )
		         ->whereBetween ( 'level', [ $min, $min + 9 ] )
		         ->where ( [ 'chargeNum' => 0 ] )
		         ->field ( [
			         'avg(money) as avg_money',
			         'count(*) as count',
			         'sum(money) as sum_money'
		         ] )
		         ->find ();

		if ( !empty( $avg[ 'avg' ] ) ) {
			$avg = [
				'avg_money' => 0,
				'count'     => 0,
				'sum_money' => 0,
			];
		}

		$insert = [
			'level'           => $min,
			'avg_money'       => $avg[ 'avg_money' ],
			'count'           => $avg[ 'count' ],
			'sum_money'       => $avg[ 'sum_money' ],
			'all_count'       => $this->AllCount,
			'role_proportion' => bcdiv ( $avg[ 'count' ], $this->AllCount ),
			'type'            => 'unpaid'
		];
		$result = Db::connect ( 'database.pay' )
		            ->table ( 'avg_user_gold' )
		            ->insert ( $insert );
		//小额付费
		$avg = Db::connect ( 'database.pay' )
		         ->table ( 'ccc' )
		         ->whereBetween ( 'level', [ $min, $min + 9 ] )
		         ->where ( [
			         'chargeNum' => [
				         [ '>', 0 ],
				         [ '<=', 1000 ],
				         'and'
			         ]
		         ] )
		         ->field ( [
			         'avg(money) as avg_money',
			         'count(*) as count',
			         'sum(money) as sum_money'
		         ] )
		         ->find ();
		if ( !empty( $avg[ 'avg' ] ) ) {
			$avg = [
				'avg_money' => 0,
				'count'     => 0,
				'sum_money' => 0,
			];
		}
		$insert = [
			'level'           => $min,
			'avg_money'       => $avg[ 'avg_money' ],
			'count'           => $avg[ 'count' ],
			'sum_money'       => $avg[ 'sum_money' ],
			'all_count'       => $this->AllCount,
			'role_proportion' => bcdiv ( $avg[ 'count' ], $this->AllCount ),
			'type'            => 'small'
		];
		$result = Db::connect ( 'database.pay' )
		            ->table ( 'avg_user_gold' )
		            ->insert ( $insert );
		//大额付费

		$avg = Db::connect ( 'database.pay' )
		         ->table ( 'ccc' )
		         ->whereBetween ( 'level', [ $min, $min + 9 ] )
		         ->where ( [ 'chargeNum' => [ '>', 1000 ] ] )
		         ->field ( [
			         'avg(money) as avg_money',
			         'count(*) as count',
			         'sum(money) as sum_money'
		         ] )
		         ->find ();
		if ( !empty( $avg[ 'avg' ] ) ) {
			$avg = [
				'avg_money' => 0,
				'count'     => 0,
				'sum_money' => 0,
			];
		}
		$insert = [
			'level'           => $min,
			'avg_money'       => $avg[ 'avg_money' ],
			'count'           => $avg[ 'count' ],
			'sum_money'       => $avg[ 'sum_money' ],
			'all_count'       => $this->AllCount,
			'role_proportion' => bcdiv ( $avg[ 'count' ], $this->AllCount ),
			'type'            => 'big'
		];
		$result = Db::connect ( 'database.pay' )
		            ->table ( 'avg_user_gold' )
		            ->insert ( $insert );


		//所有玩家
		$this->insertLog ( [ 'chargeNum' => [ '>=', 0 ] ], $avg[ 'avg_money' ], 'all', $min );
		//不付费玩家
		$this->insertLog ( [ 'chargeNum' => 0 ], $avg[ 'avg_money' ], 'unpaid', $min );
		//付费玩家  0<= 10
		$this->insertLog ( [
			'chargeNum' => [
				[ '>', 0 ],
				[ '<=', 1000 ],
				'and'
			]
		], $avg[ 'avg_money' ], 'small', $min );
		//付费玩家 大于>10
		$this->insertLog ( [ 'chargeNum' => [ '>', 1000 ] ], $avg[ 'avg_money' ], 'big', $min );
	}

	/**
	 * @param array $map       //查询条件
	 * @param       $avg_gold  int 全部人均金币
	 * @param       $type      string 类型
	 * @param       $min       int  段位
	 *
	 * @throws \think\Exception
	 */
	private function insertLog ( array $map, $avg_gold, $type, $min )
	{
		//0-0.01 段
		$max = $min + 9;

		/*	dump (bcmul ( $avg_gold, 0.1 ));
			dump (bcmul ( $avg_gold, 0.5 ));
			dump (bcmul ( $avg_gold, 1 ));
			dump (bcmul ( $avg_gold, 2 ));
			dump (bcmul ( $avg_gold, 5));
			dump (bcmul ( $avg_gold, 10));
			dump (bcmul ( $avg_gold, 20 ));*/

		$map[ 'money' ] = [ '<=', intval ( bcmul ( $avg_gold, 0.1 ) ) ];
		//所有玩家
		$find = Db::connect ( 'database.pay' )
		          ->table ( 'ccc' )
		          ->whereBetween ( 'level', [ $min, $max ] )
		          ->where ( $map )
		          ->field ( [
			          'avg(money) as avg_money',
			          'count(*) as count',
			          'sum(money) as sum_money'
		          ] )
		          ->find ();


		$this->insertAll[] = [
			'type'            => $type,
			'level'           => $min,
			'rank'            => "0-0.1",//段位
			'count_role'      => $find[ 'count' ],
			'sum_money'       => $find[ 'sum_money' ],
			'avg_money'       => $find[ 'avg_money' ],
			'all_count'       => $this->AllCount,
			'role_proportion' => $this->rankRoleCount == 0 ? 0 : bcdiv ( $find[ 'count' ], $this->rankRoleCount ),
		];


		unset( $find );

		//0.1-0.5
		$map[ 'money' ] = [
			[ '>', intval ( bcmul ( $avg_gold, 0.1 ) ) ],
			[ '<=', intval ( bcmul ( $avg_gold, 0.5 ) ) ],
			'and'
		];
		$find           = Db::connect ( 'database.pay' )
		                    ->table ( 'ccc' )
		                    ->whereBetween ( 'level', [ $min, $max ] )
		                    ->where ( $map )
		                    ->field ( [
			                    'avg(money) as avg_money',
			                    'count(*) as count',
			                    'sum(money) as sum_money'
		                    ] )
		                    ->find ();
		/*dump ($find);
		die;*/


		//0.5-1.0
		$this->insertAll[] = [
			'type'            => $type,
			'level'           => $min,
			'rank'            => "0.1-0.5",//段位
			'count_role'      => $find[ 'count' ],
			'sum_money'       => $find[ 'sum_money' ],
			'avg_money'       => $find[ 'avg_money' ],
			'all_count'       => $this->AllCount,
			'role_proportion' => $this->rankRoleCount == 0 ? 0 : bcdiv ( $find[ 'count' ], $this->rankRoleCount ),
		];
		unset( $find );

		$map[ 'money' ] = [
			[ '>', intval ( bcmul ( $avg_gold, 0.5 ) ) ],
			[ '<=', intval ( bcmul ( $avg_gold, 1 ) ) ],
			'and'
		];
		$find           = Db::connect ( 'database.pay' )
		                    ->table ( 'ccc' )
		                    ->whereBetween ( 'level', [ $min, $max ] )
		                    ->where ( $map )
		                    ->field ( [
			                    'avg(money) as avg_money',
			                    'count(*) as count',
			                    'sum(money) as sum_money'
		                    ] )
		                    ->find ();

		$this->insertAll[] = [
			'type'            => $type,
			'level'           => $min,
			'rank'            => "0.5-1",//段位
			'count_role'      => $find[ 'count' ],
			'sum_money'       => $find[ 'sum_money' ],
			'avg_money'       => $find[ 'avg_money' ],
			'all_count'       => $this->AllCount,
			'role_proportion' => $this->rankRoleCount == 0 ? 0 : bcdiv ( $find[ 'count' ], $this->rankRoleCount ),
		];
		unset( $find );

		//1-2
		$map[ 'money' ]    = [
			[ '>', intval ( bcmul ( $avg_gold, 1 ) ) ],
			[ '<=', intval ( bcmul ( $avg_gold, 2 ) ) ],
			'and'
		];
		$find              = Db::connect ( 'database.pay' )
		                       ->table ( 'ccc' )
		                       ->whereBetween ( 'level', [ $min, $max ] )
		                       ->where ( $map )
		                       ->field ( [
			                       'avg(money) as avg_money',
			                       'count(*) as count',
			                       'sum(money) as sum_money'
		                       ] )
		                       ->find ();
		$this->insertAll[] = [
			'type'            => $type,
			'level'           => $min,
			'rank'            => "1-2",//段位
			'count_role'      => $find[ 'count' ],
			'sum_money'       => $find[ 'sum_money' ],
			'avg_money'       => $find[ 'avg_money' ],
			'all_count'       => $this->AllCount,
			'role_proportion' => $this->rankRoleCount == 0 ? 0 : bcdiv ( $find[ 'count' ], $this->rankRoleCount ),
		];
		unset( $find );
		//2-5
		$map[ 'money' ]    = [
			[ '>', intval ( bcmul ( $avg_gold, 2 ) ) ],
			[ '<=', intval ( bcmul ( $avg_gold, 5 ) ) ],
			'and'
		];
		$find              = Db::connect ( 'database.pay' )
		                       ->table ( 'ccc' )
		                       ->whereBetween ( 'level', [ $min, $max ] )
		                       ->where ( $map )
		                       ->field ( [
			                       'avg(money) as avg_money',
			                       'count(*) as count',
			                       'sum(money) as sum_money'
		                       ] )
		                       ->find ();
		$this->insertAll[] = [
			'type'            => $type,
			'level'           => $min,
			'rank'            => "2-5",//段位
			'count_role'      => $find[ 'count' ],
			'sum_money'       => $find[ 'sum_money' ],
			'avg_money'       => $find[ 'avg_money' ],
			'all_count'       => $this->AllCount,
			'role_proportion' => $this->rankRoleCount == 0 ? 0 : bcdiv ( $find[ 'count' ], $this->rankRoleCount ),
		];

		unset( $find );
		//5-10
		$map[ 'money' ] = [
			[ '>', intval ( bcmul ( $avg_gold, 5 ) ) ],
			[ '<=', intval ( bcmul ( $avg_gold, 10 ) ) ],
			'and'
		];

		$find              = Db::connect ( 'database.pay' )
		                       ->table ( 'ccc' )
		                       ->whereBetween ( 'level', [ $min, $max ] )
		                       ->where ( $map )
		                       ->field ( [
			                       'avg(money) as avg_money',
			                       'count(*) as count',
			                       'sum(money) as sum_money'
		                       ] )
		                       ->find ();
		$this->insertAll[] = [
			'type'            => $type,
			'level'           => $min,
			'rank'            => "5-10",//段位
			'count_role'      => $find[ 'count' ],
			'sum_money'       => $find[ 'sum_money' ],
			'avg_money'       => $find[ 'avg_money' ],
			'all_count'       => $this->AllCount,
			'role_proportion' => $this->rankRoleCount == 0 ? 0 : bcdiv ( $find[ 'count' ], $this->rankRoleCount ),
		];
		unset( $find );
		//10-20
		$map[ 'money' ]    = [
			[ '>', intval ( bcmul ( $avg_gold, 10 ) ) ],
			[ '<=', intval ( bcmul ( $avg_gold, 20 ) ) ],
			'and'
		];
		$find              = Db::connect ( 'database.pay' )
		                       ->table ( 'ccc' )
		                       ->whereBetween ( 'level', [ $min, $max ] )
		                       ->where ( $map )
		                       ->field ( [
			                       'avg(money) as avg_money',
			                       'count(*) as count',
			                       'sum(money) as sum_money'
		                       ] )
		                       ->find ();
		$this->insertAll[] = [
			'type'            => $type,
			'level'           => $min,
			'rank'            => "10-20",//段位
			'count_role'      => $find[ 'count' ],
			'sum_money'       => $find[ 'sum_money' ],
			'avg_money'       => $find[ 'avg_money' ],
			'all_count'       => $this->AllCount,
			'role_proportion' => $this->rankRoleCount == 0 ? 0 : bcdiv ( $find[ 'count' ], $this->rankRoleCount ),
		];
		unset( $find );
		//< 20
		$map[ 'money' ]    = [ '>', intval ( bcmul ( $avg_gold, 20 ) ) ];
		$find              = Db::connect ( 'database.pay' )
		                       ->table ( 'ccc' )
		                       ->whereBetween ( 'level', [ $min, $max ] )
		                       ->where ( $map )
		                       ->field ( [
			                       'avg(money) as avg_money',
			                       'count(*) as count',
			                       'sum(money) as sum_money'
		                       ] )
		                       ->find ();
		$this->insertAll[] = [
			'type'            => $type,
			'level'           => $min,
			'rank'            => ">20",//段位
			'count_role'      => $find[ 'count' ],
			'sum_money'       => $find[ 'sum_money' ],
			'avg_money'       => $find[ 'avg_money' ],
			'all_count'       => $this->AllCount,
			'role_proportion' => $this->rankRoleCount == 0 ? 0 : bcdiv ( $find[ 'count' ], $this->rankRoleCount ),
		];
		unset( $find );

		//入库
		$result = Db::connect ( 'database.pay' )
		            ->table ( 'rank_avg_gold' )
		            ->insertAll ( $this->insertAll );
		Log::write ( $result, 'crontab' );
		//清空操作
		$this->insertAll = [];

	}
}