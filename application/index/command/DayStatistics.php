<?php
/**
 * 每天统计运营数据
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/9/7
 * Time: 22:14
 */


namespace app\index\command;

use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class DayStatistics extends Command
{
    private $log_table;
    //DB
    private $db_report;
    private $db_log;
    private $db_uwinslot;

    private $date;
    private $dayTime;
    private $yesterdayTime;
    private $day2Time;
    private $day3Time;
    private $day4Time;
    private $day5Time;
    private $day6Time;
    private $day7Time;
    private $day8Time;
    private $day9Time;
    private $day10Time;
    private $day11Time;
    private $day12Time;
    private $day13Time;
    private $day14Time;
    private $day20Time;
    private $day30Time;
    private $dayTimeTimestamp;
    private $yesterdayTimeTimestamp;
    private $day2TimeTimestamp;
    private $day3TimeTimestamp;
    private $day4TimeTimestamp;
    private $day5TimeTimestamp;
    private $day6TimeTimestamp;
    private $day7TimeTimestamp;
    private $day8TimeTimestamp;
    private $day9TimeTimestamp;
    private $day10TimeTimestamp;
    private $day11TimeTimestamp;
    private $day12TimeTimestamp;
    private $day13TimeTimestamp;
    private $day14TimeTimestamp;
    private $day20TimeTimestamp;
    private $day30TimeTimestamp;

    /**
     * 配置方法
     */
    protected function configure()
    {
        $this->setName('DayStatistics')// 运行命令时使用 "--help | -h" 选项时的完整命令描述
        ->setDescription('每天统计运营数据')/**
         * 定义形参
         * Argument::REQUIRED = 1; 必选
         * Argument::OPTIONAL = 2;  可选
         */
        ->addArgument('cronTab', Argument::OPTIONAL, '是否是定时任务')
            ->addArgument('start_date', Argument::OPTIONAL, '开始日期:格式2012-12-12')
            ->addArgument('day', Argument::OPTIONAL, '持续天数')// 运行 "php think list" 时的简短描述
            ->setHelp("暂无");
    }

    ///	 *     * 生成所需的数据表
    //	 * php think test  调用的方法
    //	 *
    //	 * @param Input  $input  接收参数对象
    //	 * @param Output $output 操作命令

    protected function execute(Input $input, Output $output)
    {
        date_default_timezone_set(Config::get('area'));//设置时区
        //数据库连接初始化
        $this->db_report = Db::connect('database.report');//运营后台report库
        $this->db_log = Db::connect('database.log');//日志服库
        $this->db_uwinslot = Db::connect('database.uwinslot');//游戏库

        //是定时任务
        if (!empty($input->getArgument('cronTab'))) {
            //初始化时间
            $this->dayTime[0] = date("Y-m-d 00:00:00", strtotime("-1 day"));
            $this->dayTime[1] = date("Y-m-d 23:59:59", strtotime("-1 day"));
            $this->dayTimeTimestamp[0] = strtotime($this->dayTime[0]);
            $this->dayTimeTimestamp[1] = strtotime($this->dayTime[1]);

            $this->yesterdayTime[0] = date("Y-m-d 00:00:00", strtotime("-2 day"));
            $this->yesterdayTime[1] = date("Y-m-d 23:59:59", strtotime("-2 day"));
            $this->yesterdayTimeTimestamp[0] = strtotime($this->yesterdayTime[0]);
            $this->yesterdayTimeTimestamp[1] = strtotime($this->yesterdayTime[1]);

            $this->day2Time[0] = date("Y-m-d 00:00:00", strtotime("-3 day"));
            $this->day2Time[1] = date("Y-m-d 23:59:59", strtotime("-3 day"));
            $this->day2TimeTimestamp[0] = strtotime($this->day2Time[0]);
            $this->day2TimeTimestamp[1] = strtotime($this->day2Time[1]);

            $this->day3Time[0] = date("Y-m-d 00:00:00", strtotime("-4 day"));
            $this->day3Time[1] = date("Y-m-d 23:59:59", strtotime("-4 day"));
            $this->day3TimeTimestamp[0] = strtotime($this->day3Time[0]);
            $this->day3TimeTimestamp[1] = strtotime($this->day3Time[1]);

            $this->day4Time[0] = date("Y-m-d 00:00:00", strtotime("-5 day"));
            $this->day4Time[1] = date("Y-m-d 23:59:59", strtotime("-5 day"));
            $this->day4TimeTimestamp[0] = strtotime($this->day4Time[0]);
            $this->day4TimeTimestamp[1] = strtotime($this->day4Time[1]);

            $this->day5Time[0] = date("Y-m-d 00:00:00", strtotime("-6 day"));
            $this->day5Time[1] = date("Y-m-d 23:59:59", strtotime("-6 day"));
            $this->day5TimeTimestamp[0] = strtotime($this->day5Time[0]);
            $this->day5TimeTimestamp[1] = strtotime($this->day5Time[1]);

            $this->day6Time[0] = date("Y-m-d 00:00:00", strtotime("-7 day"));
            $this->day6Time[1] = date("Y-m-d 23:59:59", strtotime("-7 day"));
            $this->day6TimeTimestamp[0] = strtotime($this->day6Time[0]);
            $this->day6TimeTimestamp[1] = strtotime($this->day6Time[1]);

            $this->day7Time[0] = date("Y-m-d 00:00:00", strtotime("-8 day"));
            $this->day7Time[1] = date("Y-m-d 23:59:59", strtotime("-8 day"));
            $this->day7TimeTimestamp[0] = strtotime($this->day7Time[0]);
            $this->day7TimeTimestamp[1] = strtotime($this->day7Time[1]);

            $this->day8Time[0] = date("Y-m-d 00:00:00", strtotime("-9 day"));
            $this->day8Time[1] = date("Y-m-d 23:59:59", strtotime("-9 day"));
            $this->day8TimeTimestamp[0] = strtotime($this->day8Time[0]);
            $this->day8TimeTimestamp[1] = strtotime($this->day8Time[1]);

            $this->day9Time[0] = date("Y-m-d 00:00:00", strtotime("-10 day"));
            $this->day9Time[1] = date("Y-m-d 23:59:59", strtotime("-10 day"));
            $this->day9TimeTimestamp[0] = strtotime($this->day9Time[0]);
            $this->day9TimeTimestamp[1] = strtotime($this->day9Time[1]);

            $this->day10Time[0] = date("Y-m-d 00:00:00", strtotime("-11 day"));
            $this->day10Time[1] = date("Y-m-d 23:59:59", strtotime("-11 day"));
            $this->day10TimeTimestamp[0] = strtotime($this->day10Time[0]);
            $this->day10TimeTimestamp[1] = strtotime($this->day10Time[1]);

            $this->day11Time[0] = date("Y-m-d 00:00:00", strtotime("-12 day"));
            $this->day11Time[1] = date("Y-m-d 23:59:59", strtotime("-12 day"));
            $this->day11TimeTimestamp[0] = strtotime($this->day11Time[0]);
            $this->day11TimeTimestamp[1] = strtotime($this->day11Time[1]);

            $this->day12Time[0] = date("Y-m-d 00:00:00", strtotime("-13 day"));
            $this->day12Time[1] = date("Y-m-d 23:59:59", strtotime("-13 day"));
            $this->day12TimeTimestamp[0] = strtotime($this->day12Time[0]);
            $this->day12TimeTimestamp[1] = strtotime($this->day12Time[1]);

            $this->day13Time[0] = date("Y-m-d 00:00:00", strtotime("-14 day"));
            $this->day13Time[1] = date("Y-m-d 23:59:59", strtotime("-14 day"));
            $this->day13TimeTimestamp[0] = strtotime($this->day13Time[0]);
            $this->day13TimeTimestamp[1] = strtotime($this->day13Time[1]);

            $this->day14Time[0] = date("Y-m-d 00:00:00", strtotime("-15 day"));
            $this->day14Time[1] = date("Y-m-d 23:59:59", strtotime("-15 day"));
            $this->day14TimeTimestamp[0] = strtotime($this->day14Time[0]);
            $this->day14TimeTimestamp[1] = strtotime($this->day14Time[1]);

            $this->day20Time[0] = date("Y-m-d 00:00:00", strtotime("-21 day"));
            $this->day20Time[1] = date("Y-m-d 23:59:59", strtotime("-21 day"));
            $this->day20TimeTimestamp[0] = strtotime($this->day20Time[0]);
            $this->day20TimeTimestamp[1] = strtotime($this->day20Time[1]);

            $this->day30Time[0] = date("Y-m-d 00:00:00", strtotime("-31 day"));
            $this->day30Time[1] = date("Y-m-d 23:59:59", strtotime("-31 day"));
            $this->day30TimeTimestamp[0] = strtotime($this->day30Time[0]);
            $this->day30TimeTimestamp[1] = strtotime($this->day30Time[1]);
            //定时任务生成上一个小时入库
            $this->_generateLog($output);
            exit;
        }
    }

    private function _generateLog(Output $output)
    {
        $output->writeln("日期:{$this->date} 数据开始...... ");

        $cid_list = Db::connect('database.uwinslot')
            ->table('tp_channel_server')
            ->where(['status' => 1])
            ->field(['channel_id', 'sid'])
            ->select();

        //遍历渠道包
        if ($cid_list) foreach ($cid_list as $channel) {
            $output->writeln("日期:{$this->date} - 更新server_id:{$channel[ 'sid' ]}，channel_id:{$channel['channel_id']}数据开始...... ");
            //更新付费率与留存
            $this->update1day($channel['sid'], $channel['channel_id'], $output);
            dump(1);exit;
            $this->update2day($channel['sid'], $channel['channel_id'], $output);
            $this->update3day($channel['sid'], $channel['channel_id'], $output);
            $this->update4day($channel['sid'], $channel['channel_id'], $output);
            $this->update5day($channel['sid'], $channel['channel_id'], $output);
            $this->update6day($channel['sid'], $channel['channel_id'], $output);
            $this->update7day($channel['sid'], $channel['channel_id'], $output);
            $this->update8day($channel['sid'], $channel['channel_id'], $output);
            $this->update9day($channel['sid'], $channel['channel_id'], $output);
            $this->update10day($channel['sid'], $channel['channel_id'], $output);
            $this->update11day($channel['sid'], $channel['channel_id'], $output);
            $this->update12day($channel['sid'], $channel['channel_id'], $output);
            $this->update13day($channel['sid'], $channel['channel_id'], $output);
            $this->update14day($channel['sid'], $channel['channel_id'], $output);
            $this->update20day($channel['sid'], $channel['channel_id'], $output);
            $this->update30day($channel['sid'], $channel['channel_id'], $output);
        }
    }

    //今天更新昨日的数据
    private function update1day($sid, $cid, $output)
    {

        $log_table = "t_logs_" . date('Ymd', $this->yesterdayTimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        //先获取昨日的新增用户
        $dnuRoleIds = $this->db_log->table($log_table)->where(['sid' => $sid, 'cid' => $cid, 'eid' => 1001])->column('roleid');

        //昨日新增且有付费的用户
        $payAndDnuRoleIds = $this->db_uwinslot->table('new_pay_record')->where(['channelId' => $cid, 'status' => 200])
            ->whereIn('roleId', $dnuRoleIds)
            ->column('roleid');

        $payDataWhere = [
            'insertTime' => ['<=', $this->dayTimeTimestamp[1]],
        ];
        //查看昨日新增用户到现在止有多少人充值了
        $payData = $this->db_uwinslot->table('new_pay_record')
            ->where(['channelId' => $cid, 'status' => 200])
            ->where($payDataWhere)
            ->whereIn('roleId', $dnuRoleIds)
            ->field([
                'SUM(IF(payType=1,orderRealityAmount,0)) AS new_recharge_amount',//新用户付费金额
                'COUNT(DISTINCT roleId,IF(payType=1,TRUE,NULL)) as recharge_pnum', //付费人数
            ])
            ->find();

        //昨日注册q且付费的用户，今天还有多少活跃的
        $log_table = "t_logs_" . date('Ymd', $this->dayTimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        $dayLogData = $this->db_log->table($log_table)
            ->where(['sid' => $sid, 'cid' => $cid])
            ->whereIn('roleid', $payAndDnuRoleIds)
            ->field([
                'COUNT(DISTINCT roleid,IF(eid=1002,TRUE,NULL)) as dau'//活跃用户
            ])
            ->find();

        //更新tbl_new_user_analysis
        $dnu = count($dnuRoleIds) ?? 0;
        if ($dnu) {
            $rate1day = $payData['recharge_pnum'] / $dnu * 100;
        } else {
            $rate1day = 0.00;
        }
        $remain_1day = $dayLogData['dau'] ?? 0;
        if ($remain_1day && count($payAndDnuRoleIds)) {
            $remain_1day = $remain_1day / count($payAndDnuRoleIds) * 100;
        } else {
            $remain_1day = 0.00;
        }
        $date = date('Y-m-d', $this->yesterdayTimeTimestamp[0]);
        $result = $this->db_report->table('tbl_new_user_analysis')->where(['date' => $date, 'cid' => $cid, 'sid' => $sid])->update(['rate_1day' => $rate1day, 'remain_1day' => $remain_1day]);
        if ($result) {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_new_user_analysis update rate_1day date: {$date} complete; rate_1day:{$rate1day},remain_1day:{$remain_1day}");
        } else {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_new_user_analysis update rate_1day date: {$date} error rate_1day:{$rate1day},remain_1day:{$remain_1day}");
        }

        $payData['new_recharge_amount'] = $payData['new_recharge_amount'] ?? 0;
        //更新LTV
        $result = $this->db_report->table('tbl_ltv')->where(['date' => $date, 'cid' => $cid, 'sid' => $sid])->update(['ltv2' => $payData['new_recharge_amount']]);
        if ($result) {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} complete; ltv2:{$payData['new_recharge_amount']}");
        } else {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} error ltv2:{$payData['new_recharge_amount']}");
        }
    }


    //更新第二天的数据
    public function update2day($sid, $cid, $output)
    {
        $log_table = "t_logs_" . date('Ymd', $this->day2TimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        //先获取昨日的新增用户
        $dnuRoleIds = $this->db_log->table($log_table)->where(['sid' => $sid, 'cid' => $cid, 'eid' => 1001])->column('roleid');

        $payDataWhere = [
            'insertTime' => ['<=', $this->dayTimeTimestamp[1]],
        ];
        //查看昨日新增用户到现在止有多少人充值了
        $payData = $this->db_uwinslot->table('new_pay_record')
            ->where(['serverId' => $sid, 'channelId' => $cid, 'orderStatus' => 200])
            ->where($payDataWhere)
            ->whereIn('roleId', $dnuRoleIds)
            ->field([
                'SUM(IF(payType=1,orderRealityAmount,0)) AS new_recharge_amount',//新用户付费金额
            ])
            ->find();

        $date = date('Y-m-d', $this->day2TimeTimestamp[0]);

        $payData['new_recharge_amount'] = $payData['new_recharge_amount'] ?? 0;

        //更新LTV
        $result = $this->db_report->table('tbl_ltv')->where(['date' => $date, 'cid' => $cid, 'sid' => $sid])->update(['ltv3' => $payData['new_recharge_amount']]);
        if ($result) {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} complete; ltv3:{$payData['new_recharge_amount']}");
        } else {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} error ltv3:{$payData['new_recharge_amount']}");
        }
    }

    //今天更新3天前的数据
    private function update3day($sid, $cid, $output)
    {

        $log_table = "t_logs_" . date('Ymd', $this->day3TimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        //先获取3日前的新增用户
        $dnuRoleIds = $this->db_log->table($log_table)->where(['sid' => $sid, 'cid' => $cid, 'eid' => 1001])->column('roleid');

        //新增且有付费的用户
        $payAndDnuRoleIds = $this->db_uwinslot->table('new_pay_record')->where(['serverId' => $sid, 'channelId' => $cid, 'orderStatus' => 200])
            ->whereIn('roleId', $dnuRoleIds)
            ->column('roleid');

        $payDataWhere = [
            'insertTime' => ['<=', $this->dayTimeTimestamp[1]],
        ];
        //查看3日前的新增用户到现在止有多少人充值了
        $payData = $this->db_uwinslot->table('new_pay_record')
            ->where(['serverId' => $sid, 'channelId' => $cid, 'orderStatus' => 200])
            ->where($payDataWhere)
            ->whereIn('roleId', $dnuRoleIds)
            ->field([
                'SUM(IF(payType=1,orderRealityAmount,0)) AS new_recharge_amount',//新用户付费金额
                'COUNT(DISTINCT roleId,IF(payType=1,TRUE,NULL)) as recharge_pnum', //付费人数
            ])
            ->find();

        //3日前注册的用户，今天还有多少活跃的
        $log_table = "t_logs_" . date('Ymd', $this->dayTimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        $dayLogData = $this->db_log->table($log_table)
            ->where(['sid' => $sid, 'cid' => $cid])
            ->whereIn('roleid', $payAndDnuRoleIds)
            ->field([
                'COUNT(DISTINCT roleid,IF(eid=1002,TRUE,NULL)) as dau'//活跃用户
            ])
            ->find();

        //更新昨日付费率与次日留存
        $dnu = count($dnuRoleIds) ?? 0;
        if ($dnu) {
            $rate = $payData['recharge_pnum'] / $dnu * 100;
        } else {
            $rate = 0.00;
        }
        $remain = $dayLogData['dau'] ?? 0;
        if ($remain && count($payAndDnuRoleIds)) {
            $remain = $remain / count($payAndDnuRoleIds) * 100;
        } else {
            $remain = 0.00;
        }
        $date = date('Y-m-d', $this->day3TimeTimestamp[0]);
        $result = $this->db_report->table('tbl_new_user_analysis')->where(['date' => $date, 'cid' => $cid, 'sid' => $sid])->update(['rate_3day' => $rate, 'remain_3day' => $remain]);
        if ($result) {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_new_user_analysis update rate_3day date: {$date} complete");
        } else {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_new_user_analysis update rate_3day date: {$date} error");
        }

        $payData['new_recharge_amount'] = $payData['new_recharge_amount'] ?? 0;
        //更新LTV
        $result = $this->db_report->table('tbl_ltv')->where(['date' => $date, 'cid' => $cid, 'sid' => $sid])->update(['ltv4' => $payData['new_recharge_amount']]);
        if ($result) {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} complete; ltv4:{$payData['new_recharge_amount']}");
        } else {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} error ltv4:{$payData['new_recharge_amount']}");
        }

    }


    //更新第四天的数据
    public function update4day($sid, $cid, $output)
    {
        $log_table = "t_logs_" . date('Ymd', $this->day4TimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        //先获取昨日的新增用户
        $dnuRoleIds = $this->db_log->table($log_table)->where(['sid' => $sid, 'cid' => $cid, 'eid' => 1001])->column('roleid');

        $payDataWhere = [
            'insertTime' => ['<=', $this->dayTimeTimestamp[1]],
        ];
        //查看昨日新增用户到现在止有多少人充值了
        $payData = $this->db_uwinslot->table('new_pay_record')
            ->where(['serverId' => $sid, 'channelId' => $cid, 'orderStatus' => 200])
            ->where($payDataWhere)
            ->whereIn('roleId', $dnuRoleIds)
            ->field([
                'SUM(IF(payType=1,orderRealityAmount,0)) AS new_recharge_amount',//新用户付费金额
            ])
            ->find();

        $date = date('Y-m-d', $this->day4TimeTimestamp[0]);
        $payData['new_recharge_amount'] = $payData['new_recharge_amount'] ?? 0;
        //更新LTV
        $result = $this->db_report->table('tbl_ltv')->where(['date' => $date, 'cid' => $cid, 'sid' => $sid])->update(['ltv5' => $payData['new_recharge_amount']]);
        if ($result) {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} complete; ltv5:{$payData['new_recharge_amount']}");
        } else {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} error ltv5:{$payData['new_recharge_amount']}");
        }
    }

    //更新第五天的数据
    public function update5day($sid, $cid, $output)
    {
        $log_table = "t_logs_" . date('Ymd', $this->day5TimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        //先获取昨日的新增用户
        $dnuRoleIds = $this->db_log->table($log_table)->where(['sid' => $sid, 'cid' => $cid, 'eid' => 1001])->column('roleid');

        $payDataWhere = [
            'insertTime' => ['<=', $this->dayTimeTimestamp[1]],
        ];
        //查看昨日新增用户到现在止有多少人充值了
        $payData = $this->db_uwinslot->table('new_pay_record')
            ->where(['serverId' => $sid, 'channelId' => $cid, 'orderStatus' => 200])
            ->where($payDataWhere)
            ->whereIn('roleId', $dnuRoleIds)
            ->field([
                'SUM(IF(payType=1,orderRealityAmount,0)) AS new_recharge_amount',//新用户付费金额
            ])
            ->find();

        $date = date('Y-m-d', $this->day5TimeTimestamp[0]);
        $payData['new_recharge_amount'] = $payData['new_recharge_amount'] ?? 0;
        //更新LTV
        $result = $this->db_report->table('tbl_ltv')->where(['date' => $date, 'cid' => $cid, 'sid' => $sid])->update(['ltv6' => $payData['new_recharge_amount']]);
        if ($result) {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} complete; ltv6:{$payData['new_recharge_amount']}");
        } else {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} error ltv6:{$payData['new_recharge_amount']}");
        }
    }

    //更新第六天的数据
    public function update6day($sid, $cid, $output)
    {
        $log_table = "t_logs_" . date('Ymd', $this->day6TimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        //先获取昨日的新增用户
        $dnuRoleIds = $this->db_log->table($log_table)->where(['sid' => $sid, 'cid' => $cid, 'eid' => 1001])->column('roleid');

        $payDataWhere = [
            'insertTime' => ['<=', $this->dayTimeTimestamp[1]],
        ];
        //查看昨日新增用户到现在止有多少人充值了
        $payData = $this->db_uwinslot->table('new_pay_record')
            ->where(['serverId' => $sid, 'channelId' => $cid, 'orderStatus' => 200])
            ->where($payDataWhere)
            ->whereIn('roleId', $dnuRoleIds)
            ->field([
                'SUM(IF(payType=1,orderRealityAmount,0)) AS new_recharge_amount',//新用户付费金额
            ])
            ->find();

        $date = date('Y-m-d', $this->day6TimeTimestamp[0]);
        $payData['new_recharge_amount'] = $payData['new_recharge_amount'] ?? 0;
        //更新LTV
        $result = $this->db_report->table('tbl_ltv')->where(['date' => $date, 'cid' => $cid, 'sid' => $sid])->update(['ltv7' => $payData['new_recharge_amount']]);
        if ($result) {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} complete; ltv7:{$payData['new_recharge_amount']}");
        } else {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} error ltv7:{$payData['new_recharge_amount']}");
        }
    }

    //今天更新7天前的数据
    private function update7day($sid, $cid, $output)
    {

        $log_table = "t_logs_" . date('Ymd', $this->day7TimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        //先获取3日前的新增用户
        $dnuRoleIds = $this->db_log->table($log_table)->where(['sid' => $sid, 'cid' => $cid, 'eid' => 1001])->column('roleid');

        //新增且有付费的用户
        $payAndDnuRoleIds = $this->db_uwinslot->table('new_pay_record')->where(['serverId' => $sid, 'channelId' => $cid, 'orderStatus' => 200])
            ->whereIn('roleId', $dnuRoleIds)
            ->column('roleid');

        $payDataWhere = [
            'insertTime' => ['<=', $this->dayTimeTimestamp[1]],
        ];
        //查看3日前的新增用户到现在止有多少人充值了
        $payData = $this->db_uwinslot->table('new_pay_record')
            ->where(['serverId' => $sid, 'channelId' => $cid, 'orderStatus' => 200])
            ->where($payDataWhere)
            ->whereIn('roleId', $dnuRoleIds)
            ->field([
                'SUM(IF(payType=1,orderRealityAmount,0)) AS new_recharge_amount',//新用户付费金额
                'COUNT(DISTINCT roleId,IF(payType=1,TRUE,NULL)) as recharge_pnum', //付费人数
            ])
            ->find();

        //3日前注册的用户，今天还有多少活跃的
        $log_table = "t_logs_" . date('Ymd', $this->dayTimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        $dayLogData = $this->db_log->table($log_table)
            ->where(['sid' => $sid, 'cid' => $cid])
            ->whereIn('roleid', $payAndDnuRoleIds)
            ->field([
                'COUNT(DISTINCT roleid,IF(eid=1002,TRUE,NULL)) as dau'//活跃用户
            ])
            ->find();

        //更新昨日付费率与次日留存
        $dnu = count($dnuRoleIds) ?? 0;
        if ($dnu) {
            $rate = $payData['recharge_pnum'] / $dnu * 100;
        } else {
            $rate = 0.00;
        }
        $remain = $dayLogData['dau'] ?? 0;
        if ($remain && count($payAndDnuRoleIds)) {
            $remain = $remain / count($payAndDnuRoleIds) * 100;
        } else {
            $remain = 0.00;
        }
        $date = date('Y-m-d', $this->day7TimeTimestamp[0]);
        $result = $this->db_report->table('tbl_new_user_analysis')->where(['date' => $date, 'cid' => $cid, 'sid' => $sid])->update(['rate_7day' => $rate, 'remain_7day' => $remain]);
        if ($result) {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_new_user_analysis update rate_7day date: {$date} complete");
        } else {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_new_user_analysis update rate_7day date: {$date} error");
        }

        $payData['new_recharge_amount'] = $payData['new_recharge_amount'] ?? 0;
        //更新LTV
        $result = $this->db_report->table('tbl_ltv')->where(['date' => $date, 'cid' => $cid, 'sid' => $sid])->update(['ltv8' => $payData['new_recharge_amount']]);
        if ($result) {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} complete; ltv8:{$payData['new_recharge_amount']}");
        } else {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} error ltv8:{$payData['new_recharge_amount']}");
        }
    }

    //更新第八天的数据
    public function update8day($sid, $cid, $output)
    {
        $log_table = "t_logs_" . date('Ymd', $this->day8TimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        //先获取昨日的新增用户
        $dnuRoleIds = $this->db_log->table($log_table)->where(['sid' => $sid, 'cid' => $cid, 'eid' => 1001])->column('roleid');

        $payDataWhere = [
            'insertTime' => ['<=', $this->dayTimeTimestamp[1]],
        ];
        //查看昨日新增用户到现在止有多少人充值了
        $payData = $this->db_uwinslot->table('new_pay_record')
            ->where(['serverId' => $sid, 'channelId' => $cid, 'orderStatus' => 200])
            ->where($payDataWhere)
            ->whereIn('roleId', $dnuRoleIds)
            ->field([
                'SUM(IF(payType=1,orderRealityAmount,0)) AS new_recharge_amount',//新用户付费金额
            ])
            ->find();

        $date = date('Y-m-d', $this->day8TimeTimestamp[0]);

        $payData['new_recharge_amount'] = $payData['new_recharge_amount'] ?? 0;
        //更新LTV
        $result = $this->db_report->table('tbl_ltv')->where(['date' => $date, 'cid' => $cid, 'sid' => $sid])->update(['ltv9' => $payData['new_recharge_amount']]);
        if ($result) {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} complete; ltv9:{$payData['new_recharge_amount']}");
        } else {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} error ltv9:{$payData['new_recharge_amount']}");
        }
    }


    //更新第九天的数据
    public function update9day($sid, $cid, $output)
    {
        $log_table = "t_logs_" . date('Ymd', $this->day9TimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        //先获取昨日的新增用户
        $dnuRoleIds = $this->db_log->table($log_table)->where(['sid' => $sid, 'cid' => $cid, 'eid' => 1001])->column('roleid');

        $payDataWhere = [
            'insertTime' => ['<=', $this->dayTimeTimestamp[1]],
        ];
        //查看昨日新增用户到现在止有多少人充值了
        $payData = $this->db_uwinslot->table('new_pay_record')
            ->where(['serverId' => $sid, 'channelId' => $cid, 'orderStatus' => 200])
            ->where($payDataWhere)
            ->whereIn('roleId', $dnuRoleIds)
            ->field([
                'SUM(IF(payType=1,orderRealityAmount,0)) AS new_recharge_amount',//新用户付费金额
            ])
            ->find();

        $date = date('Y-m-d', $this->day9TimeTimestamp[0]);
        $payData['new_recharge_amount'] = $payData['new_recharge_amount'] ?? 0;
        //更新LTV
        $result = $this->db_report->table('tbl_ltv')->where(['date' => $date, 'cid' => $cid, 'sid' => $sid])->update(['ltv10' => $payData['new_recharge_amount']]);
        if ($result) {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} complete; ltv10:{$payData['new_recharge_amount']}");
        } else {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} error ltv10:{$payData['new_recharge_amount']}");
        }
    }

    //更新第十天的数据
    public function update10day($sid, $cid, $output)
    {
        $log_table = "t_logs_" . date('Ymd', $this->day10TimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        //先获取昨日的新增用户
        $dnuRoleIds = $this->db_log->table($log_table)->where(['sid' => $sid, 'cid' => $cid, 'eid' => 1001])->column('roleid');

        $payDataWhere = [
            'insertTime' => ['<=', $this->dayTimeTimestamp[1]],
        ];
        //查看昨日新增用户到现在止有多少人充值了
        $payData = $this->db_uwinslot->table('new_pay_record')
            ->where(['serverId' => $sid, 'channelId' => $cid, 'orderStatus' => 200])
            ->where($payDataWhere)
            ->whereIn('roleId', $dnuRoleIds)
            ->field([
                'SUM(IF(payType=1,orderRealityAmount,0)) AS new_recharge_amount',//新用户付费金额
            ])
            ->find();

        $date = date('Y-m-d', $this->day10TimeTimestamp[0]);
        $payData['new_recharge_amount'] = $payData['new_recharge_amount'] ?? 0;
        //更新LTV
        $result = $this->db_report->table('tbl_ltv')->where(['date' => $date, 'cid' => $cid, 'sid' => $sid])->update(['ltv11' => $payData['new_recharge_amount']]);
        if ($result) {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} complete; ltv11:{$payData['new_recharge_amount']}");
        } else {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} error ltv11:{$payData['new_recharge_amount']}");
        }
    }


    //更新第十一天的数据
    public function update11day($sid, $cid, $output)
    {
        $log_table = "t_logs_" . date('Ymd', $this->day11TimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        //先获取昨日的新增用户
        $dnuRoleIds = $this->db_log->table($log_table)->where(['sid' => $sid, 'cid' => $cid, 'eid' => 1001])->column('roleid');

        $payDataWhere = [
            'insertTime' => ['<=', $this->dayTimeTimestamp[1]],
        ];
        //查看昨日新增用户到现在止有多少人充值了
        $payData = $this->db_uwinslot->table('new_pay_record')
            ->where(['serverId' => $sid, 'channelId' => $cid, 'orderStatus' => 200])
            ->where($payDataWhere)
            ->whereIn('roleId', $dnuRoleIds)
            ->field([
                'SUM(IF(payType=1,orderRealityAmount,0)) AS new_recharge_amount',//新用户付费金额
            ])
            ->find();

        $date = date('Y-m-d', $this->day11TimeTimestamp[0]);
        $payData['new_recharge_amount'] = $payData['new_recharge_amount'] ?? 0;
        //更新LTV
        $result = $this->db_report->table('tbl_ltv')->where(['date' => $date, 'cid' => $cid, 'sid' => $sid])->update(['ltv12' => $payData['new_recharge_amount']]);
        if ($result) {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} complete; ltv12:{$payData['new_recharge_amount']}");
        } else {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} error ltv12:{$payData['new_recharge_amount']}");
        }
    }


    //更新第十二天的数据
    public function update12day($sid, $cid, $output)
    {
        $log_table = "t_logs_" . date('Ymd', $this->day12TimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        //先获取昨日的新增用户
        $dnuRoleIds = $this->db_log->table($log_table)->where(['sid' => $sid, 'cid' => $cid, 'eid' => 1001])->column('roleid');

        $payDataWhere = [
            'insertTime' => ['<=', $this->dayTimeTimestamp[1]],
        ];
        //查看昨日新增用户到现在止有多少人充值了
        $payData = $this->db_uwinslot->table('new_pay_record')
            ->where(['serverId' => $sid, 'channelId' => $cid, 'orderStatus' => 200])
            ->where($payDataWhere)
            ->whereIn('roleId', $dnuRoleIds)
            ->field([
                'SUM(IF(payType=1,orderRealityAmount,0)) AS new_recharge_amount',//新用户付费金额
            ])
            ->find();

        $date = date('Y-m-d', $this->day12TimeTimestamp[0]);
        $payData['new_recharge_amount'] = $payData['new_recharge_amount'] ?? 0;
        //更新LTV
        $result = $this->db_report->table('tbl_ltv')->where(['date' => $date, 'cid' => $cid, 'sid' => $sid])->update(['ltv13' => $payData['new_recharge_amount']]);
        if ($result) {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} complete; ltv13:{$payData['new_recharge_amount']}");
        } else {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} error ltv13:{$payData['new_recharge_amount']}");
        }
    }

    //更新第十三天的数据
    public function update13day($sid, $cid, $output)
    {
        $log_table = "t_logs_" . date('Ymd', $this->day13TimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        //先获取昨日的新增用户
        $dnuRoleIds = $this->db_log->table($log_table)->where(['sid' => $sid, 'cid' => $cid, 'eid' => 1001])->column('roleid');

        $payDataWhere = [
            'insertTime' => ['<=', $this->dayTimeTimestamp[1]],
        ];
        //查看昨日新增用户到现在止有多少人充值了
        $payData = $this->db_uwinslot->table('new_pay_record')
            ->where(['serverId' => $sid, 'channelId' => $cid, 'orderStatus' => 200])
            ->where($payDataWhere)
            ->whereIn('roleId', $dnuRoleIds)
            ->field([
                'SUM(IF(payType=1,orderRealityAmount,0)) AS new_recharge_amount',//新用户付费金额
            ])
            ->find();

        $date = date('Y-m-d', $this->day13TimeTimestamp[0]);
        $payData['new_recharge_amount'] = $payData['new_recharge_amount'] ?? 0;
        //更新LTV
        $result = $this->db_report->table('tbl_ltv')->where(['date' => $date, 'cid' => $cid, 'sid' => $sid])->update(['ltv14' => $payData['new_recharge_amount']]);
        if ($result) {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} complete; ltv14:{$payData['new_recharge_amount']}");
        } else {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} error ltv14:{$payData['new_recharge_amount']}");
        }
    }

    //今天更新14天前的数据
    private function update14day($sid, $cid, $output)
    {

        $log_table = "t_logs_" . date('Ymd', $this->day14TimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        //先获取3日前的新增用户
        $dnuRoleIds = $this->db_log->table($log_table)->where(['sid' => $sid, 'cid' => $cid, 'eid' => 1001])->column('roleid');

        //新增且有付费的用户
        $payAndDnuRoleIds = $this->db_uwinslot->table('new_pay_record')->where(['serverId' => $sid, 'channelId' => $cid, 'orderStatus' => 200])
            ->whereIn('roleId', $dnuRoleIds)
            ->column('roleid');

        $payDataWhere = [
            'insertTime' => ['<=', $this->dayTimeTimestamp[1]],
        ];
        //查看3日前的新增用户到现在止有多少人充值了
        $payData = $this->db_uwinslot->table('new_pay_record')
            ->where(['serverId' => $sid, 'channelId' => $cid, 'orderStatus' => 200])
            ->where($payDataWhere)
            ->whereIn('roleId', $dnuRoleIds)
            ->field([
                'COUNT(DISTINCT roleId,IF(payType=1,TRUE,NULL)) as recharge_pnum', //付费人数
            ])
            ->find();

        //3日前注册的用户，今天还有多少活跃的
        $log_table = "t_logs_" . date('Ymd', $this->dayTimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        $dayLogData = $this->db_log->table($log_table)
            ->where(['sid' => $sid, 'cid' => $cid])
            ->whereIn('roleid', $payAndDnuRoleIds)
            ->field([
                'COUNT(DISTINCT roleid,IF(eid=1002,TRUE,NULL)) as dau'//活跃用户
            ])
            ->find();

        //更新昨日付费率与次日留存
        $dnu = count($dnuRoleIds) ?? 0;
        if ($dnu) {
            $rate = $payData['recharge_pnum'] / $dnu * 100;
        } else {
            $rate = 0.00;
        }
        $remain = $dayLogData['dau'] ?? 0;
        if ($remain && count($payAndDnuRoleIds)) {
            $remain = $remain / count($payAndDnuRoleIds) * 100;
        } else {
            $remain = 0.00;
        }
        $date = date('Y-m-d', $this->day14TimeTimestamp[0]);
        $result = $this->db_report->table('tbl_new_user_analysis')->where(['date' => $date, 'cid' => $cid, 'sid' => $sid])->update(['rate_14day' => $rate, 'remain_14day' => $remain]);
        if ($result) {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_new_user_analysis update rate_3day date: {$date} complete");
        } else {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_new_user_analysis update rate_3day date: {$date} error");
        }
    }


    //更新第20天的数据
    public function update20day($sid, $cid, $output)
    {
        $log_table = "t_logs_" . date('Ymd', $this->day20TimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        //先获取昨日的新增用户
        $dnuRoleIds = $this->db_log->table($log_table)->where(['sid' => $sid, 'cid' => $cid, 'eid' => 1001])->column('roleid');

        $payDataWhere = [
            'insertTime' => ['<=', $this->dayTimeTimestamp[1]],
        ];
        //查看昨日新增用户到现在止有多少人充值了
        $payData = $this->db_uwinslot->table('new_pay_record')
            ->where(['serverId' => $sid, 'channelId' => $cid, 'orderStatus' => 200])
            ->where($payDataWhere)
            ->whereIn('roleId', $dnuRoleIds)
            ->field([
                'SUM(IF(payType=1,orderRealityAmount,0)) AS new_recharge_amount',//新用户付费金额
            ])
            ->find();

        $date = date('Y-m-d', $this->day20TimeTimestamp[0]);
        $payData['new_recharge_amount'] = $payData['new_recharge_amount'] ?? 0;
        //更新LTV
        $result = $this->db_report->table('tbl_ltv')->where(['date' => $date, 'cid' => $cid, 'sid' => $sid])->update(['ltv21' => $payData['new_recharge_amount']]);
        if ($result) {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} complete; ltv21:{$payData['new_recharge_amount']}");
        } else {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} error ltv21:{$payData['new_recharge_amount']}");
        }
    }

    //今天更新30天前的数据
    private function update30day($sid, $cid, $output)
    {

        $log_table = "t_logs_" . date('Ymd', $this->day30TimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        //先获取3日前的新增用户
        $dnuRoleIds = $this->db_log->table($log_table)->where(['sid' => $sid, 'cid' => $cid, 'eid' => 1001])->column('roleid');

        //新增且有付费的用户
        $payAndDnuRoleIds = $this->db_uwinslot->table('new_pay_record')->where(['serverId' => $sid, 'channelId' => $cid, 'orderStatus' => 200])
            ->whereIn('roleId', $dnuRoleIds)
            ->column('roleid');

        $payDataWhere = [
            'insertTime' => ['<=', $this->dayTimeTimestamp[1]],
        ];
        //查看3日前的新增用户到现在止有多少人充值了
        $payData = $this->db_uwinslot->table('new_pay_record')
            ->where(['serverId' => $sid, 'channelId' => $cid, 'orderStatus' => 200])
            ->where($payDataWhere)
            ->whereIn('roleId', $dnuRoleIds)
            ->field([
                'SUM(IF(payType=1,orderRealityAmount,0)) AS new_recharge_amount',//新用户付费金额
                'COUNT(DISTINCT roleId,IF(payType=1,TRUE,NULL)) as recharge_pnum', //付费人数
            ])
            ->find();

        //3日前注册的用户，今天还有多少活跃的
        $log_table = "t_logs_" . date('Ymd', $this->dayTimeTimestamp[0]);
        $exist = $this->db_log->query("show tables like '{$log_table}'");
        if (empty($exist)) {
            $output->writeln("--{$log_table}表不存在---");
            return false;
        }
        $dayLogData = $this->db_log->table($log_table)
            ->where(['sid' => $sid, 'cid' => $cid])
            ->whereIn('roleid', $payAndDnuRoleIds)
            ->field([
                'COUNT(DISTINCT roleid,IF(eid=1002,TRUE,NULL)) as dau'//活跃用户
            ])
            ->find();

        //更新昨日付费率与次日留存
        $dnu = count($dnuRoleIds) ?? 0;
        if ($dnu) {
            $rate = $payData['recharge_pnum'] / $dnu * 100;
        } else {
            $rate = 0.00;
        }
        $remain = $dayLogData['dau'] ?? 0;
        if ($remain && count($payAndDnuRoleIds)) {
            $remain = $remain / count($payAndDnuRoleIds) * 100;
        } else {
            $remain = 0.00;
        }
        $date = date('Y-m-d', $this->day30TimeTimestamp[0]);
        $result = $this->db_report->table('tbl_new_user_analysis')->where(['date' => $date, 'cid' => $cid, 'sid' => $sid])->update(['rate_30day' => $rate, 'remain_30day' => $remain]);
        if ($result) {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_new_user_analysis update rate_30day date: {$date} complete");
        } else {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_new_user_analysis update rate_30day date: {$date} error");
        }
        $payData['new_recharge_amount'] = $payData['new_recharge_amount'] ?? 0;
        //更新LTV
        $result = $this->db_report->table('tbl_ltv')->where(['date' => $date, 'cid' => $cid, 'sid' => $sid])->update(['ltv30' => $payData['new_recharge_amount']]);
        if ($result) {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} complete; ltv30:{$payData['new_recharge_amount']}");
        } else {
            $output->writeln("server_id:{$sid}，channel_id:{$cid}--tbl_ltv update ltv2 date: {$date} error ltv30:{$payData['new_recharge_amount']}");
        }
    }

}