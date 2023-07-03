<?php
/**
 * 生成每天日志表
 * Created by PhpStorm.
 * User: kk
 * Date: 2022/12/16
 * Time: 22:14
 */


namespace app\index\command;

use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;

class LogDay extends Command
{
    //日期
    private $date;
    private $dateYmd;
    //日期数字 找表用的
    private $hour;
    private $dateWhereBetween;
    private $slotdatacenter_table;
    private $log_table;
    //DB
    private $db_slotdatacenter;
    private $db_log;

    /**
     * 配置方法
     */
    protected function configure()
    {
        //每一小时执行一次
        $this->setName('LogDay')// 运行命令时使用 "--help | -h" 选项时的完整命令描述
        ->setDescription('生成每日log')/**
         * 定义形参
         * Argument::REQUIRED = 1; 必选
         * Argument::OPTIONAL = 2;  可选
         */
        ->addArgument('cronTab', Argument::OPTIONAL, '是否是定时任务')
            ->addArgument('start_date', Argument::OPTIONAL, '开始日期:格式2012-12-12')
            ->addArgument('duration_hour', Argument::OPTIONAL, '持续小时')// 运行 "php think list" 时的简短描述
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
        $this->db_slotdatacenter = Db::connect('database.slotdatacenter');
        $this->db_log = Db::connect('database.log');

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
            $this->dateWhereBetween[0] = $this->date . " {$this->hour}:00:00";
            $this->dateWhereBetween[1] = $this->date . " {$this->hour}:59:59";

            //定时任务生成上一个小时入库
            $this->_generateLog($output);
            exit;
        }
        //不是定时任务
        //报表重跑  获得两个参数 ,一个为开始时间
        $startDate = $input->getArgument('start_date');
        //持续小时
        $durationHour = $input->getArgument('duration_hour');

        $time = strtotime($startDate);
        //重跑$hour个小时 的报表
        for ($i = $durationHour; $i > 0; $i--) {
            //时间
            $this->date = date('Y-m-d', $time);
            //表后数字
            $this->dateYmd = date('Ymd', $time);
            //目前小时
            $this->hour = date('H', $time);
            $this->dateWhereBetween[0] = $this->date . " {$this->hour}:00:00";
            $this->dateWhereBetween[1] = $this->date . " {$this->hour}:59:59";

            //定时任务生成上一个小时入库
            $this->_generateLog($output);
            //时间加3600秒
            $time += 3600;
        }
    }

    private function _generateLog(Output $output)
    {
        $this->slotdatacenter_table = "t_logs_" . $this->dateYmd . $this->hour;
        $this->log_table = "t_logs_" . ($this->dateYmd);

        $output->writeln("日期:{$this->date} 小时:{$this->hour} - 生成每日log开始...... ");

        //每日零点生成日log表
        // if($this->hour == '00'){
        //生成数据源日志表-天
        $exist = $this->db_log->query("show tables like '{$this->log_table}'");
        if (empty($exist)) {
            $this->db_log->execute(self::getLogsSql());
            $output->writeln("---t_logs_{$this->dateYmd}:总日志表生成成功---");
        }
        //  }


        $exist = $this->db_slotdatacenter->query("show tables like '{$this->slotdatacenter_table}'");
        if (empty($exist)) {
            $output->writeln("---t_logs_{$this->dateYmd}. {$this->hour}表不存在---");
            return false;
        }
        //查出日志表数据
        $overViewData = $this->db_slotdatacenter->table($this->slotdatacenter_table)
            ->whereBetween('date', $this->dateWhereBetween)
            ->select();

        if (!$overViewData) {
            $output->writeln("---slotdatacenter_table没有数据---");
            return false;
        }

        if ($overViewData) {
            $sql = sprintf("INSERT ignore INTO {$this->log_table} (date, time, sid, cid, roleid, eid, esrc, p1, p2, p3, p4, p5, p6, ps1, ps2, ps3) VALUES ");
            foreach ($overViewData as $k => $item) {
                $itemStr = '( ';
                $itemStr .= sprintf("'%s', %d, %d, %d, %d, %d, %d, %d, %d, %d, %d, %d, %d,'%s','%s','%s'", $item['date'], $item['time'], $item['sid'], isset($item['cid']) ? $item['cid'] : 0, $item['roleid'], $item['eid'], $item['esrc'], $item['p1'], $item['p2'], $item['p3'], $item['p4'], $item['p5'], $item['p6'], $item['ps1'], $item['ps2'], $item['ps3']);
                $itemStr .= '),';
                $sql .= $itemStr;
            }
            $sql = rtrim($sql, ',');
            $sql .= ';';
            if ($this->db_log->execute($sql)) {
                $output->writeln("---t_logs_{$this->dateYmd}:总日志表数据插入成功---");
            } else {
                $output->writeln("---t_logs_{$this->dateYmd}:总日志表数据插入失败---");
            }
        }
    }

    private function getLogsSql()
    {
        return "CREATE TABLE `{$this->log_table}` (
      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      `date` datetime DEFAULT NULL,
      `time` int(11) NOT NULL DEFAULT '0',
      `sid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id，为0表示来源未知',
      `cid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '渠道id，为0表示来源未知',
      `roleid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '服务器id-角色id，为0表示系统事件 角色id不唯一',
      `eid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '事件id 大分类',
      `esrc` int(11) unsigned DEFAULT '0' COMMENT '事件来源 子分类',
      `p1` bigint(20) DEFAULT '0' COMMENT '参数1',
      `p2` bigint(20) DEFAULT '0' COMMENT '参数2',
      `p3` bigint(20) DEFAULT '0' COMMENT '参数3',
      `p4` bigint(20) unsigned DEFAULT '0' COMMENT '参数4',
      `p5` bigint(20) DEFAULT '0' COMMENT '参数5',
      `p6` bigint(20) DEFAULT '0' COMMENT '参数6',
      `ps1` varchar(1024) DEFAULT NULL COMMENT '字符串参数1',
      `ps2` varchar(1024) DEFAULT NULL COMMENT '字符串参数2',
      `ps3` varchar(1024) DEFAULT NULL COMMENT '字符串参数3',
      PRIMARY KEY (`id`),
      UNIQUE KEY `Unique` (`date`,`time`,`sid`,`roleid`,`eid`,`esrc`,`p1`,`p2`) COMMENT '防止重复'
    ) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COMMENT='总日志表';
";
    }

}