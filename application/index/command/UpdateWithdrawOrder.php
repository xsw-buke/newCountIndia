<?php
/**
 * 每分钟自动禁止24小时订单
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

class UpdateWithdrawOrder extends Command
{

    /**
     * 配置方法
     */
    protected function configure()
    {
        $this->setName('UpdateWithdrawOrder')// 运行命令时使用 "--help | -h" 选项时的完整命令描述
        ->setDescription('每分钟更新提现订单状态')/**
         * 定义形参
         * Argument::REQUIRED = 1; 必选
         * Argument::OPTIONAL = 2;  可选
         */
        ->addArgument('cronTab', Argument::OPTIONAL, '是否是定时任务')
       ->setHelp("暂无");
    }

    protected function execute(Input $input, Output $output)
    {
        date_default_timezone_set(Config::get('area'));//设置时区

        $output->writeln('提现订单机器审核开始');
        //获取当前时间前24小时订单
        $nowTime = time();
        $beforeTime = $nowTime-24*60*60;


        $res = Db::connect('database.uwinslot')
            ->table('new_pay_record')
            ->where('payType',2) //2是提现订单
            ->where('status',0) //0正在审核
            ->where('insertTime','<',$beforeTime)
            ->update(['status'=>3,'checkTime'=>$nowTime]);

        $output->writeln('提现订单机器审核结束:res:'.$res);
    }






}