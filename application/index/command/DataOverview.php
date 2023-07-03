<?php
/**
 * 全局报表
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/9/7
 * Time: 22:14
 */


namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class DataOverview extends Command
{
	//日期
	private $date;
	private $dateYmd;
	//日期数字 找表用的
	private $hour;
	private $dateWhereBetween;
	private $dateWhereBetweenTimestamp;
	private $slotdatacenter_table;
	private $slotdatacenter_table_roles;
	//DB
	private $db_report;
	private $db_slotdatacenter;
	private $db_uwinslot;

	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		$this->setName ( 'DataOverview' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '生成数据概况报表' )/**
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
		//数据库连接初始化
		$this->db_report = Db::connect ( 'database.report' );
		$this->db_slotdatacenter = Db::connect ( 'database.slotdatacenter' );
		$this->db_uwinslot = Db::connect ( 'database.uwinslot' );

		//是定时任务
		if ( !empty( $input->getArgument ( 'cronTab' ) ) ) {
			//延迟时间改为2小时
			$time = strtotime ( '-1 hour' );
			//时间
			$this->date = date ( 'Y-m-d', $time );
			//表后数字
			$this->dateYmd = date ( 'Ymd', $time );
			//目前小时
			$this->hour                  = date ( 'H', $time );

			$this->dateWhereBetween[ 0 ] = $this->date . " {$this->hour}:00:00";
			$this->dateWhereBetween[ 1 ] = $this->date . " {$this->hour}:59:59";

            $this->dateWhereBetweenTimestamp[ 0 ] = strtotime($this->dateWhereBetween[ 0 ]);
            $this->dateWhereBetweenTimestamp[ 1 ] = strtotime($this->dateWhereBetween[ 1 ]);

			//删除这一个小时的数据
			$this->_deleteLog ( $output );
			//定时任务生成上一个小时入库
			$this->_generateLog ( $output );
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
            $this->dateWhereBetweenTimestamp[ 0 ] = strtotime($this->dateWhereBetween[ 0 ]);
            $this->dateWhereBetweenTimestamp[ 1 ] = strtotime($this->dateWhereBetween[ 1 ]);
			//删除这一个小时的投注统计
			$this->_deleteLog ( $output );

			//定时任务生成上一个小时入库
			$this->_generateLog ( $output );
			//时间加3600秒
			$time += 3600;
		}
	}

	private function _generateLog ( Output $output )
	{
		//遍历服务器操作
		$server_list = Db::connect ( 'database.uwinslot' )
		                 ->table ( 'tp_server' )
		                 ->field ( 'id' )
		                 ->select ();

        $this->slotdatacenter_table = "t_logs_".$this->dateYmd.$this->hour;
        $this->slotdatacenter_table_roles = "t_logs_".($this->dateYmd-1);
        foreach ( $server_list as $server ) {
            
			$output->writeln ( "日期:{$this->date} 小时:{$this->hour} - 生成数据概况日志开始...... " );
			$add = [
				'date'      => $this->date,
				'date_time' => "{$this->date} {$this->hour}:00:00",
				'sid'       => $server[ 'id' ]
			];

			//在日志表统计 数据概况
			$overViewData = $this->db_slotdatacenter->table ( $this->slotdatacenter_table )
			                              ->whereBetween ( 'date', $this->dateWhereBetween )
			                              ->where ( [ 'sid' => $server[ 'id' ] ] )
			                              ->field ( [
				                              'COUNT(DISTINCT id,IF(eid=1001,TRUE,NULL)) as dnu',
				                              'COUNT(DISTINCT roleid,IF(eid=1002,TRUE,NULL)) as dau',
				                              'SUM(IF(esrc=512,p1,0)) as registration_gift',
				                              'SUM(IF(esrc=514,p1,0)) as invite_gifts',
				                              'SUM(IF(esrc=515,p1,0)) as invite_rebate',
				                              'abs(SUM(IF(esrc=201,p1,0))) as code_weight',
				                              'SUM(IF(esrc=202,p1,0)) as code_weight202',
				                              'SUM(IF(esrc=203,p1,0)) as code_weight203',
				                              'SUM(IF(esrc=204,p1,0)) as code_weight204',
				                              'SUM(IF(esrc=205,p1,0)) as code_weight205',
			                              ] )
			                              ->find ();
			//每天01点统计昨日新增充值，新增提现
            if($this->hour == '01'){

                $beginYesterday = strtotime($this->dateWhereBetween[0])-90000;//昨日开始时间
                $endYesterday = strtotime($this->dateWhereBetween[1])-7200;//昨日结束时间

                $sql = "SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}00` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}01` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}02` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}03` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}04` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}05` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}06` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}07` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}08` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}09` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}10` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}11` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}12` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}13` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}14` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}15` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}16` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}17` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}18` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}19` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}20` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}21` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}22` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001 UNION ALL
SELECT `roleid` FROM `{$this->slotdatacenter_table_roles}23` WHERE  `time` BETWEEN {$beginYesterday} AND {$endYesterday}  AND `sid` = {$server['id']}  AND `eid` = 1001";

                $dnuRoleIdsData = $this->db_slotdatacenter->query($sql);//查找昨天注册的用户
                $dnuRoleIds = [];
                if($dnuRoleIdsData) foreach ($dnuRoleIdsData as $rolek=>$rolev){
                    $dnuRoleIds[]= $rolev['roleid'];
                }
                //统计昨日新增支付情况
                $newPayData = $this->db_uwinslot->table( 'new_pay_record' )
                    ->whereBetween ( 'insertTime', [$beginYesterday,$endYesterday] )
                    ->where ( [ 'serverId' => $server[ 'id' ], 'orderStatus' =>200 ] )
                    ->whereIn('roleId',$dnuRoleIds)
                    ->field ( [
                        'SUM(IF(payType=1,orderRealityAmount,0)) AS new_recharge',
                        'SUM(IF(payType=2,orderRealityAmount,0)) AS new_withdrawal',
                    ] )
                    ->find ();
            }else{
                $newPayData =[
                    'new_recharge' => 0,
                    'new_withdrawal' => 0
                ];
            }

            //统计支付数据
			$payData = $this->db_uwinslot->table( 'new_pay_record' )
                ->whereBetween ( 'insertTime', $this->dateWhereBetweenTimestamp )
                ->where ( [ 'serverId' => $server[ 'id' ], 'orderStatus' =>200 ] )
                ->field ( [
                    'SUM(IF(payType=1,orderRealityAmount,0)) AS recharge_amount',
                    'SUM(IF(payType=2,orderRealityAmount,0)) AS withdrawal_amount',
                ] )
                ->find ();

            $add = array_merge ( $add, $overViewData, $payData, $newPayData );
			foreach ($add as $K=>&$v){
			    $v = $v ? $v : 0;
            }

			$result = $this->db_report->table ( 'tbl_data_overview' )
			                          ->insert ( $add );
			if ( $result == FALSE ) {
				$output->writeln ( '时间: ' . $this->date . '小时:' . $this->hour . "日志入库失败" );
			}

			// 输出日志
			$msg = '时间: ' . $this->date . '小时:' . $this->hour;
			$msg .= ' - 账号新增: ' . $add[ 'dnu' ];
			$msg .= ' - 活跃: ' . $add[ 'dau' ];
			$msg .= ' - 充值金额: ' . $add[ 'recharge_amount' ];
			$msg .= ' - 提现金额: ' . $add[ 'withdrawal_amount' ];
			$msg .= ' - 注册赠送: ' . $add[ 'registration_gift' ];
			$msg .= ' - 邀请赠送: ' . $add[ 'invite_gifts' ];
			$msg .= ' - 邀请返利: ' . $add[ 'invite_rebate' ];
			$msg .= ' - 新增充值: ' . $add[ 'new_recharge' ];
			$msg .= ' - 新增提现: ' . $add[ 'new_withdrawal' ];
			$msg .= ' - 压码量: ' . $add[ 'code_weight' ];
			$msg .= ' - 202: ' . $add[ 'code_weight202' ];
			$msg .= ' - 203: ' . $add[ 'code_weight203' ];
			$msg .= ' - 204: ' . $add[ 'code_weight204' ];
			$msg .= ' - 205: ' . $add[ 'code_weight205' ];
			$msg .= "----------生成全局报表日志结束---------";
			$output->writeln ( $msg );
		}
	}


	private function _deleteLog ( Output $output )
	{
		$output->writeln ( "日期:{$this->date};小时:{$this->hour} - 删除旧数据开始......" );
		$map    = [
			'date_time' => "{$this->date} {$this->hour}:00:00",
		];

		$result = $this->db_report->table ( 'tbl_data_overview' )
		                          ->where ( $map )
		                          ->delete ();
		if ( $result == FALSE ) {
			$output->writeln ( "日期: {$this->date} 小时: {$this->hour} tbl_data_overview 没有数据删除" );
			return;
		}
		$output->writeln ( "日期: {$this->date} 小时: {$this->hour} tbl_data_overview 删除成功" );
	}

}