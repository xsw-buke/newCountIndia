<?php
/**
 * 每小时统计运营数据
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

class HourStatistics extends Command
{
    //日期
    private $date;
    private $dateYmd;
    //日期数字 找表用的
    private $hour;
    private $dateWhereBetween;
    private $dateWhereBetweenTimestamp;
    private $log_table;
    //DB
    private $db_report;
    private $db_log;
    private $db_uwinslot;

    /**
     * 配置方法
     */
    protected function configure()
    {
        $this->setName('HourStatistics')// 运行命令时使用 "--help | -h" 选项时的完整命令描述
        ->setDescription('每小时统计运营数据')/**
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
            //延迟时间改为2小时
            $time = strtotime('-1 hour');
            //时间
            $this->date = date('Y-m-d', $time);
            //表后数字
            $this->dateYmd = date('Ymd', $time);
            //目前小时
            $this->hour = date('H', $time);

            $this->dateWhereBetween[0] = $this->date . " 00:00:00";
            $this->dateWhereBetween[1] = $this->date . " 23:59:59";

            $this->dateWhereBetweenTimestamp[0] = strtotime($this->dateWhereBetween[0]);
            $this->dateWhereBetweenTimestamp[1] = strtotime($this->dateWhereBetween[1]);

            //定时任务生成上一个小时入库
            $this->_generateLog($output);
            exit;
        }
        //不是定时任务
        //报表重跑  获得两个参数 ,一个为开始时间
        $startDate = $input->getArgument('start_date');
        //持续天
        $day = $input->getArgument('day');

        $time = strtotime($startDate);
        //重跑$day天 的数据
        for ($i = $day; $i > 0; $i--) {
            //时间
            $this->date = date('Y-m-d', $time);
            //表后数字
            $this->dateYmd = date('Ymd', $time);
            //目前小时
            $this->hour = date('H', $time);

            $this->dateWhereBetween[0] = $this->date . " 00:00:00";
            $this->dateWhereBetween[1] = $this->date . " 23:59:59";
            $this->dateWhereBetweenTimestamp[0] = strtotime($this->dateWhereBetween[0]);
            $this->dateWhereBetweenTimestamp[1] = strtotime($this->dateWhereBetween[1]);

            //定时任务生成上一个小时入库
            $this->_generateLog($output);
            //时间加3600秒
            $time += 86400;
        }
    }

    private function _generateLog(Output $output)
    {
        $output->writeln("日期:{$this->date} 数据开始...... ");
        $this->log_table = "t_logs_" . $this->dateYmd;

        $cid_list = Db::connect('database.uwinslot')
            ->table('tp_channel_server')
            ->where(['status' => 1])
            ->field(['channel_id', 'sid'])
            ->select();

        //遍历渠道包
        if ($cid_list) foreach ($cid_list as $channel) {
            $output->writeln("日期:{$this->date} 小时:{$this->hour} - 生成server_id:{$channel[ 'sid' ]}，channel_id:{$channel['channel_id']}数据开始...... ");

            //在日志表统计数据
            $dayLogData = $this->db_log->table($this->log_table)
                ->whereBetween('date', $this->dateWhereBetween)
                ->where(['sid' => $channel['sid'], 'cid' => $channel['channel_id']])
                ->field([
                    'COUNT(DISTINCT roleid,IF(eid=1001,TRUE,NULL)) as dnu',//新增用户
                    'COUNT(DISTINCT roleid,IF(eid=1002 or eid=1001,TRUE,NULL)) as dau'//活跃用户
                ])
                ->find();

            //查找渠道包今天注册的新用户
            $dnuRoleIds = $this->db_log->table($this->log_table)->whereBetween('date', $this->dateWhereBetween)
                ->where(['sid' => $channel['sid'], 'cid' => $channel['channel_id'], 'eid' => 1001])->column('roleid');

            if (!empty($dnuRoleIds)) {
                //统计今日新增用户支付数据
                $newPayData = $this->db_uwinslot->table('new_pay_record')
                    ->whereBetween('insertTime', $this->dateWhereBetweenTimestamp)
                    ->where(['channelId' => $channel['channel_id'], 'status' => 1])
                    ->whereIn('roleId', $dnuRoleIds)
                    ->field([
                        'SUM(IF(payType=1,orderRealityAmount,0)) AS new_recharge_amount',//新用户付费金额
                        'COUNT(DISTINCT roleId,IF(payType=1,TRUE,NULL)) as new_recharge_pnum'//新用户付费人数
                    ])
                    ->find();
            } else {
                $newPayData['new_recharge_amount'] = 0;
                $newPayData['new_recharge_pnum'] = 0;
            }


            //统计今日支付数据
            $payData = $this->db_uwinslot->table('new_pay_record')
                ->whereBetween('insertTime', $this->dateWhereBetweenTimestamp)
                ->where(['channelId' => $channel['channel_id'], 'status' => 1])
                ->field([
                    'SUM(IF(payType=1,orderRealityAmount,0)) AS recharge_amount', //付费金额
                    'COUNT(DISTINCT roleId,IF(payType=1,TRUE,NULL)) as recharge_pnum', //付费人数
                    'SUM(IF(payType=2,orderRealityAmount,0)) AS withdrawal_amount', //提现金额
                    'COUNT(DISTINCT roleId,IF(payType=2,TRUE,NULL)) as withdrawal_pnum' //提现人数
                ])
                ->find();

            //统计数据概况
            $this->insertDataOverview($output, $channel['sid'], $channel['channel_id'], $dayLogData, $payData, $newPayData);
            //统计新用户分析
            $this->insertNewUserAnalysis($output, $channel['sid'], $channel['channel_id'], $dayLogData, $newPayData);
            //LTV统计
            $this->insertLtv($output, $channel['sid'], $channel['channel_id'], $dayLogData, $newPayData);
        }
    }


    //插入数据概况初始化数据
    private function insertDataOverview($output, $sid, $cid, $dayLogData, $payData, $newPayData)
    {
        //data_overview_data表数据
        $inster_data_overview_data = [
            'date' => $this->date,
            'sid' => $sid,
            'cid' => $cid,
            'dnu' => $dayLogData['dnu'] ?? 0,
            'dau' => $dayLogData['dau'] ?? 0,
            'recharge_pnum' => $payData['recharge_pnum'] ?? 0,
            'new_recharge_pnum' => $newPayData['new_recharge_pnum'] ?? 0,
            'recharge_amount' => $payData['recharge_amount'] ?? 0,
            'new_recharge_amount' => $newPayData['new_recharge_amount'] ?? 0,
            'withdrawal_pnum' => $payData['withdrawal_pnum'] ?? 0,
            'withdrawal_amount' => $payData['withdrawal_amount'] ?? 0,
        ];

        $isExistdata_overview_data = $this->db_report->table('tbl_data_overview')->where(['date' => $inster_data_overview_data['date'], 'sid' => $sid, 'cid' => $cid])->field('id')->find();
        if ($isExistdata_overview_data) {//存在就更新
            $result = $this->db_report->table('tbl_data_overview')->where(['id' => $isExistdata_overview_data['id']])->update($inster_data_overview_data);
        } else {//不存在则插入
            $result = $this->db_report->table('tbl_data_overview')->insert($inster_data_overview_data);
        }
        if ($result) {
            $output->writeln('时间: ' . $this->date . '小时:' . $this->hour . "server_id:{$sid}，channel_id:{$cid}--tbl_data_overview入库成功");
        } else {
            $output->writeln('时间: ' . $this->date . '小时:' . $this->hour . "server_id:{$sid}，channel_id:{$cid}--tbl_data_overview入库失败");
        }
    }


    //新用户分析
    private function insertNewUserAnalysis($output, $sid, $cid, $dayLogData, $newPayData)
    {
        $inster_data_overview_data = [
            'date' => $this->date,
            'sid' => $sid,
            'cid' => $cid,
            'dnu' => $dayLogData['dnu'] ?? 0,
            'new_recharge_amount' => $newPayData['new_recharge_amount'] ?? 0,
            'new_recharge_pnum' => $newPayData['new_recharge_pnum'] ?? 0,
        ];


        if ($dayLogData['dnu'] && $newPayData['new_recharge_pnum']) {
            $inster_data_overview_data['rate'] = $newPayData['new_recharge_pnum'] / $dayLogData['dnu'] * 100; //新用户首日付费率
        }
        $isExistdata = $this->db_report->table('tbl_new_user_analysis')->where(['date' => $inster_data_overview_data['date'], 'sid' => $sid, 'cid' => $cid])->field('id')->find();
        if ($isExistdata) {//存在就更新
            $result = $this->db_report->table('tbl_new_user_analysis')->where(['id' => $isExistdata['id']])->update($inster_data_overview_data);
        } else {//不存在则插入
            $result = $this->db_report->table('tbl_new_user_analysis')->insert($inster_data_overview_data);
        }
        if ($result) {
            $output->writeln('时间: ' . $this->date . '小时:' . $this->hour . "server_id:{$sid}，channel_id:{$cid}--tbl_new_user_analysis insert complete");
        } else {
            $output->writeln('时间: ' . $this->date . '小时:' . $this->hour . "server_id:{$sid}，channel_id:{$cid}--tbl_new_user_analysis insert error");
        }
    }

    //LTV统计
    private function insertLtv($output, $sid, $cid, $dayLogData, $newPayData)
    {
        $inster_data = [
            'date' => $this->date,
            'sid' => $sid,
            'cid' => $cid,
            'dnu' => $dayLogData['dnu'] ?? 0,
            'ltv1' => $newPayData['new_recharge_amount'] ?? 0
        ];

        $isExistdata = $this->db_report->table('tbl_ltv')->where(['date' => $inster_data['date'], 'sid' => $sid, 'cid' => $cid])->field('id')->find();
        if ($isExistdata) {//存在就更新
            $result = $this->db_report->table('tbl_ltv')->where(['id' => $isExistdata['id']])->update($inster_data);
        } else {//不存在则插入
            $result = $this->db_report->table('tbl_ltv')->insert($inster_data);
        }
        if ($result) {
            $output->writeln('时间: ' . $this->date . '小时:' . $this->hour . "server_id:{$sid}，channel_id:{$cid}--tbl_ltv insert complete");
        } else {
            $output->writeln('时间: ' . $this->date . '小时:' . $this->hour . "server_id:{$sid}，channel_id:{$cid}--tbl_ltv insert error");
        }
    }

}