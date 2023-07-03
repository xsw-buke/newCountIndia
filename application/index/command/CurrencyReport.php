<?php
/**
 * 币报表
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2022/1/8
 * Time: 20:33
 */

namespace app\index\command;

use app\index\model\SignatureHelper;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class CurrencyReport extends Command
{

	//日期
	const SIZE = 10;
	const B_SID = 7; //宝石的服务器id

	private $date;
	//日期数字 找表用的
	private $dateYmd;
	private $hour;
	private $dateWhereBetween;
	private $db_report;
	private $db_log;
	private $insertData;
	private $map;
	private $db_dataCenter;
	private $insertArray;
	private $db_newPortal;
	/**
	 * @var \think\db\Connection
	 */
	private $dbCenter;
	private $phoneAdminList;

	private static function LogToArray ( $value )
	{
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
	}


	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'CurrencyReport' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '生成币小时报表' )/**
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
		$this->db_log    = Db::connect ( 'database.log' );


		//是定时任务
		if ( $input->getArgument ( 'cronTab' ) == 1 ) {
			//延迟时间改为2小时
			$time = strtotime ( '-2 hour' );
			//时间
			$this->date = date ( 'Y-m-d', $time );
			//表后数字
			$this->dateYmd = date ( 'Ymd', $time );
			//目前小时
			$this->hour = date ( 'H', $time );

			$this->dateWhereBetween[ 0 ] = $this->date . " {$this->hour}:00:00";
			$this->dateWhereBetween[ 1 ] = $this->date . " {$this->hour}:59:59";
			//删除这一个小时的投注统计
			$this->_deleteLog ( $output );
			//定时任务生成上一个小时入库
			$this->_generateLog ( $output );

			//定时任务检测报表是否需要报警
			$this->checkLimit ( $output );
			$timeSub = time () - $phpStartTime;
			$str     = "开始时间" . date ( 'Y-m-d H:i:s', $phpStartTime ) . " ,结束时间" . date ( 'Y-m-d H:i:s' ) . ", 总计费时 {$timeSub} 秒";
			$output->writeln ( $str );
			return TRUE;
		}
		elseif ( $input->getArgument ( 'cronTab' ) == 0 ) {
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
			}
			$second = time () - $phpStartTime;
			$output->writeln ( "开始时间" . date ( 'Y-m-d H:i:s', $phpStartTime ) . " ,结束时间" . date ( 'Y-m-d H:i:s' ) . ", 总计费时{$second}秒" );
		}
		elseif ( $input->getArgument ( 'cronTab' ) == 2 ) {
			$output->writeln ( "重新拉取日志开始" );

			$this->db_dataCenter = Db::connect ( 'database.datacenter' );
			//数据重新拉取 从本地备用数据库拉取
			$start_date = $input->getArgument ( 'start_date' );
			//持续小时
			$end_date = $input->getArgument ( 'duration_hour' );
			while ( $start_date <= $end_date ) {
				//时间
				$this->date = date ( 'Y-m-d', strtotime ( $start_date ) );
				//表后数字
				$this->dateYmd = date ( 'Ymd', strtotime ( $start_date ) );
				//删除这天的投注统计
				$this->_deleteCurrencyLog ( $output );
				//定时任务生成上一个天入库
				$this->_pullCurrencyLog ( $output );
				//时间加3600秒
				$start_date = date ( 'Y-m-d', strtotime ( "+1 day", strtotime ( $start_date ) ) );
			}

			$second = time () - $phpStartTime;
			$output->writeln ( "开始时间" . date ( 'Y-m-d H:i:s', $phpStartTime ) . " ,结束时间" . date ( 'Y-m-d H:i:s' ) . ", 总计费时{$second}秒" );
		}
		die;
	}

	private function _generateLog ( Output $output )
	{
		// 获取时间段内 该游戏的压注玩家列表
		$roleList = $this->getRoleList ();
		//这个游戏这段时间没有人玩
		if ( empty( $roleList ) ) {
			$output->writeln ( "NO {$this->dateYmd}{$this->hour}小时 没有数据...... " );
			return FALSE;
		}
		//插入数组重置
		$this->insertArray = [];
		//遍历玩家列表  得到该玩家在上个小时  统计的数据
		foreach ( $roleList as $roleInfo ) {

			$roleReportInfo = $this->db_log->table ( 'tbl_currency_log_' . $this->dateYmd )
			                               ->field ( [
				                               'sum(add_gold) as add_gold',
				                               'count(*) as count',
				                               'esrc'
			                               ] )
			                               ->where ( [ 'role_id' => $roleInfo[ 'role_id' ],
			                                  'sid' => self::B_SID ] )
			                               ->whereBetween ( 'create_time', $this->dateWhereBetween )
			                               ->group ( 'esrc' )
			                               ->select ();

			$this->insertData = [
				'date_ymd'           => $this->date,
				'date_time'          => $this->date . " {$this->hour}:00:00",
				'sid'                => $roleInfo[ 'sid' ],
				'role_id'            => $roleInfo[ 'role_id' ],
				'level_up_get'       => 0,//数据初始化
				'level_up_count'     => 0,//数据初始化
				'withdraw_cost'      => 0,//数据初始化
				'withdraw_count'     => 0,//数据初始化
				'new_role_get'       => 0,//数据初始化
				'new_role_count'     => 0,//数据初始化
				'activity_cost'      => 0,//数据初始化
				'activity_count'     => 0,//数据初始化
				'gem_freeze'         => 0,//数据初始化
				'gem_freeze_count'   => 0,//数据初始化
				'gem_unfreeze'       => 0,//数据初始化
				'gem_unfreeze_count' => 0,//数据初始化
			];

			foreach ( $roleReportInfo as $value ) {
				$this->roleCurrencyReport ( $value );

				$this->insertData[ 'all_get' ]   = bcadd ( $this->insertData[ 'new_role_get' ], $this->insertData[ 'level_up_get' ] );
				$this->insertData[ 'all_count' ] = bcadd ( $this->insertData[ 'new_role_count' ], $this->insertData[ 'level_up_count' ] );
			}
			$this->insertArray[] = $this->insertData;
		}

		$result = $this->db_report->table ( 'tbl_currency_report' )
		                          ->insertAll ( $this->insertArray );
		if ( $result == FALSE ) {
			//获取所有游戏
			$output->writeln ( "NO {$this->dateYmd}{$this->hour}小时报表插入失败...... " );
			return FALSE;
		}
		//获取所有游戏
		$output->writeln ( "YES {$this->date} {$this->hour}小时报表插入成功...... " );
		return TRUE;
	}

	/**
	 * 获取上一个小时 所有游戏玩家列表
	 * @return array|bool
	 */
	private function getRoleList ()
	{
		//前期玩家不用分页
		$list = $this->db_log->table ( 'tbl_currency_log_' . $this->dateYmd )
		                     ->field ( [ 'role_id', 'sid' ] )
		                     ->whereBetween ( 'create_time', $this->dateWhereBetween )
		                     ->where ( [ 'sid' => self::B_SID ] )
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
		$result = $this->db_report->table ( 'tbl_currency_report' )
		                          ->where ( $map )
		                          ->delete ();
		if ( $result == FALSE ) {
			$output->writeln ( "日期: {$this->date} 小时: {$this->hour} tbl_currency_report 没有数据删除" );
			return;
		}
		$output->writeln ( "日期: {$this->date} 小时: {$this->hour} tbl_currency_report 删除成功" );
	}

	/**
	 * @param Output $output
	 *  删除币日志
	 */
	private function _deleteCurrencyLog ( Output $output )
	{
		$output->writeln ( "日期:{$this->date};- tbl_currency_log删除旧数据开始......" );
		$sql    = "TRUNCATE `tbl_currency_log_{$this->dateYmd}`";
		$result = $this->db_log->query ( $sql );
		if ( $result == FALSE ) {
			$output->writeln ( "tbl_currency_log_{$this->dateYmd} 没有数据删除" );
			return;
		}
		$output->writeln ( "tbl_currency_log_{$this->dateYmd} 截断表成功" );
	}

	/**
	 * @param Output $output
	 *   从本地备份总日志拉取币日志
	 */
	private function _pullCurrencyLog ( Output $output )
	{

		$where = [
			'eid' => 2010
		];
		//原始数据 直接入库
		$count = $this->db_dataCenter->table ( "t_logs_{$this->dateYmd}" )
		                             ->where ( $where )
		                             ->count ();
		//走到这里 如果条数等于0 说明只有一条记录
		if ( $count == 0 ) {
			return FALSE;
		}
		//计算分多少页
		$pageCount = ceil ( $count / self::SIZE );

		$output->writeln ( date ( 'Y-m-d H:i:s' ) . "本次共处理页数{$pageCount}" );

		//分页 页数小于总页数 页数上面再加一 代表开始的
		for ( $page = 1; $page <= $pageCount; $page++ ) {
			//获取一千条总日志 分别插入其他日志0
			$logs = $this->db_dataCenter->table ( "t_logs_{$this->dateYmd}" )
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
			                            ] )
			                            ->where ( $where )
			                            ->page ( $page, self::SIZE )
			                            ->select ();


			if ( empty( $logs ) ) {
				$output->writeln ( "t_logs_{$this->dateYmd}没有数据,本次返回" );
				return FALSE;
			}
			$this->insertArray = [];
			//改变数组中的值，传参的时候使用引用
			array_walk ( $logs, function ( &$val, $key ) {
				$val = [
					'sid'         => $val[ 'sid' ],
					'role_id'     => $val[ 'roleid' ],
					'eid'         => $val[ 'eid' ],
					'esrc'        => $val[ 'esrc' ],
					'create_time' => $val[ 'date' ],
					'add_gold'    => $val[ 'p1' ], //改变的币
					'change_gold' => $val[ 'p2' ], //变化后币
					'level'       => $val[ 'p3' ], //等级
					'game_id'     => $val[ 'p4' ], //游戏
				];
			} );

			$result = $this->db_log->table ( "tbl_currency_log_{$this->dateYmd}" )
			                       ->insertAll ( $logs );

			$output->writeln ( "tbl_currency_log_{$this->dateYmd} 表插入{$result}" );

			$output->writeln ( date ( 'Y-m-d H:i:s' ) . "第{$page}页,一共{$pageCount}页" );
		}

		return TRUE;
	}

	private function roleCurrencyReport ( $value )
	{
		switch ( $value[ 'esrc' ] ) {
			case 301: //--升级奖励获得(+)
				$this->insertData[ 'level_up_get' ]   = $value[ 'add_gold' ];
				$this->insertData[ 'level_up_count' ] = $value[ 'count' ];;
				return TRUE;
			case 206: //--提取宝石币时消耗(-)
				$this->insertData[ 'withdraw_cost' ]  = $value[ 'add_gold' ];
				$this->insertData[ 'withdraw_count' ] = $value[ 'count' ];;
				return TRUE;
			case 208: //--新角色赠送(+)
				$this->insertData[ 'new_role_get' ]   = $value[ 'add_gold' ];
				$this->insertData[ 'new_role_count' ] = $value[ 'count' ];;
				return TRUE;
			case 14: //--活动消耗(-)
				$this->insertData[ 'activity_cost' ]  = $value[ 'add_gold' ];
				$this->insertData[ 'activity_count' ] = $value[ 'count' ];;
				return TRUE;
			case 209: //--宝石被冻结(质押)
				$this->insertData[ 'gem_freeze' ]       = $value[ 'add_gold' ];
				$this->insertData[ 'gem_freeze_count' ] = $value[ 'count' ];;
				return TRUE;
			case 210: //--宝石解冻
				$this->insertData[ 'gem_unfreeze' ]       = $value[ 'add_gold' ];
				$this->insertData[ 'gem_unfreeze_count' ] = $value[ 'count' ];;
				return TRUE;
			default:
				return TRUE;
		}
	}

	/**
	 * @param Output $output
	 *   检测是否需要预警
	 */
	private function checkLimit ( Output $output )
	{
		$this->dbCenter       = Db::connect ( 'database.center' );
		$this->phoneAdminList = $this->dbCenter->table ( 'tbl_server_admin' )
		                                       ->select ();
		$this->db_newPortal   = Db::connect ( 'database.portal' );;

		$config = $this->db_newPortal->table ( 'tp_currency_config' )
		                             ->where ( [ 'id' => 1 ] )
		                             ->find ();
		$insert = [
			'hour_register'               => 0, //小时注册
			'hour_excavate'               => 0, //小时挖掘
			'stage'                       => 0, //阶段
			'register_warning'            => 2, //默认状态正常
			'excavate_warning'            => 2, //默认状态正常
			'stage_warning'               => 2, //默认状态正常
			'create_time'                 => date ( 'Y-m-d H:i:s' ),
			'check_time'                  => "{$this->date} {$this->hour}:00:00",
			'config_hour_register_limit'  => $config[ 'hour_register_limit' ],//当前小时注册配置',
			'config_hour_excavate_limit'  => $config[ 'hour_excavate_limit' ],//当前小时挖掘配置
			'config_currency_total'       => $config[ 'currency_total' ],//当前币总数配置',
			'config_stage_excavate_limit' => $config[ 'stage_excavate_limit' ],//当前预警阈值配置

		];
		//获取上个小时统计
		$count = $this->db_report->table ( 'tbl_currency_report' )
		                         ->field ( [
			                         'sum(new_role_count) as new_role_count',
			                         'sum(all_get) as all_get',
		                         ] )
		                         ->where ( [
			                         'date_time' => "{$this->date} {$this->hour}:00:00",
		                         ] )
		                         ->select ();

		$insert[ 'hour_register' ] = intval ( $count[ 0 ][ 'new_role_count' ] );
		$insert[ 'hour_excavate' ] = intval ( $count[ 0 ][ 'all_get' ] );


		//注册人数预警
		if ( $config[ 'hour_register_limit' ] <= $count[ 0 ][ 'new_role_count' ] ) {
			$insert[ 'register_warning' ] = 1;
			$this->sendMsg ( "{$this->date} {$this->hour}每小时币注册人数预警" );
			$this->insertServerMsg ( "{$this->date} {$this->hour}小时 {$count[0][ 'new_role_count' ]}每小时币注册人数预警", 0 );
			$output->writeln ( "{$this->date} {$this->hour}每小时币注册人数预警" );
		}


		//小时获取币超额预警
		if ( $config[ 'hour_excavate_limit' ] <= $count[ 0 ][ 'all_get' ] ) {
			$insert[ 'excavate_warning' ] = 1;
			$this->sendMsg ( "{$this->date} {$this->hour}小时 每小时挖掘数量预警" );
			$this->insertServerMsg ( "{$count[0][ 'all_get' ]} :每小时挖掘数量预警", 0 );
			$output->writeln ( "{$this->date} {$this->hour}小时 每小时挖掘数量预警" );
		}

		//阶段阀值预警
		$all_count                     = $this->db_report->table ( 'tbl_currency_report' )
		                                                 ->field ( [
			                                                 'sum(all_get) as all_get',//总挖掘
			                                                 'sum(withdraw_cost) as all_withdraw_cost',//总提取消耗
			                                                 'sum(activity_cost) as all_activity_cost',//总活动消耗
			                                                 'sum(gem_freeze) as all_gem_freeze',      //总活动消耗
			                                                 'sum(gem_unfreeze) as all_gem_unfreeze',  //总活动消耗
		                                                 ] )
		                                                 ->find ();
		$insert[ 'all_excavate' ]      = intval ( $all_count[ 'all_get' ] ); //总挖掘
		$insert[ 'all_withdraw_cost' ] = intval ( $all_count[ 'all_withdraw_cost' ] );//总提取消耗
		$insert[ 'all_activity_cost' ] = intval ( $all_count[ 'all_activity_cost' ] );//总活动消耗
		$insert[ 'all_gem_freeze' ]    = intval ( $all_count[ 'all_gem_freeze' ] );//总活动消耗
		$insert[ 'all_gem_unfreeze' ]  = intval ( $all_count[ 'all_gem_unfreeze' ] );//总活动消耗

		$stage             = bcdiv ( $insert[ 'all_excavate' ], $config[ 'stage_excavate_limit' ] );
		$insert[ 'stage' ] = $stage;
		//找到 相同阶段和相同预警阈值的
		$result = $this->db_newPortal->table ( 'tp_currency_hour_log' )
		                             ->where ( [
			                             'stage'                       => $stage,
			                             'config_stage_excavate_limit' => $config[ 'stage_excavate_limit' ]
		                             ] )
		                             ->find ();
		if ( $result == FALSE ) {
			//新的阈值阶段预警
			$insert[ 'stage_warning' ] = 1;
			$this->sendMsg ( "{$this->date} {$this->hour}小时 宝石币挖掘阶段{$stage}阈值预警" );
			$this->insertServerMsg ( "{$stage}宝石币挖掘阶段阈值预警", 0 );
			$output->writeln ( "{$this->date} {$this->hour}小时 宝石币挖掘阶段{$stage}阈值预警{$insert[ 'all_excavate' ] }" );
		}

		$result2 = $this->db_newPortal->table ( 'tp_currency_hour_log' )
		                              ->insert ( $insert );
		if ( $result2 == FALSE ) {
			$output->writeln ( '监控记录插入失败' );
		}
		$output->writeln ( '监控记录插入成功' );
	}


	//发送短信
	private function sendMsg ( $msg )
	{
		//TODO::测试
		echo $msg;
		$result       = (object)[];
		$result->Code = 'OK';
		return $result;


		// *** 需用户填写部分 ***
		//    必填：是否启用https
		$security = FALSE;
		//    必填: 请参阅 https://ak-console.aliyun.com/ 取得您的AK信息
		$accessKeyId     = "LTAI4GHuPYiamYsvVpJmgSx9";
		$accessKeySecret = "hrlcAtQbZbhKdWdbcCodQc9Gy11af4";

		//    必填: 待发送手机号。支持JSON格式的批量调用，批量上限为100个手机号码,批量调用相对于单条调用及时性稍有延迟,验证码类型的短信推荐使用单条调用的方式

		//    必填: 短信模板Code，应严格按"模板CODE"填写, 请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/template
		$params[ "TemplateCode" ] = "SMS_210075743";


		//    必填: 模板中的变量替换JSON串,如模板内容为"亲爱的${name},您的验证码为${code}"时,此处的值为
		// 友情提示:如果JSON中需要带换行符,请参照标准的JSON协议对换行符的要求,比如短信内容中包含\r 的情况在JSON中需要表示成\\r\ ,否则会导致JSON在服务端解析失败


		foreach ( $this->phoneAdminList as $phoneAdmin ) {
			$params[ "TemplateParamJson" ][] = [
				//				"sever"    => "亚马逊服务器" ,
				"sever"    => $msg,
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
		return  $this->dbCenter->table ( 'tbl_server_msg' )
		                                              ->insert ( $insert );
	}
}