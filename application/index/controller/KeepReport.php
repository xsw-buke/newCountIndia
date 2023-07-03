<?php
/**
 * 留存统计
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/6/29
 * Time: 10:46
 */

namespace app\index\controller;


use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class KeepReport extends TaskBase
{

	//更新留存的日期
	private $date;
	private $date_time;
	private $db;
	private $countWhereBetween;

	private $channel_id_list;
	//留存日期
	const KEEP_DAYS = [
		1  => 'tow_day_num',//+1次日
		2  => 'three_day_num',//三日
		3  => 'four_day_num', //4
		4  => 'five_day_num',//5
		5  => 'six_day_num',//6
		6  => 'seven_day_num',//7
		7  => 'eight_day_num',//8
		8  => 'nine_day_num',//9
		9  => 'ten_day_num',//10
		14 => 'fifteen_day_num', //+14 第十五日留存  当日是剔除的
	];
	//昨天日期
	const CREATE_INFO_DAY = 1;
	const ROLE_PAGE_SIZE = 1000;

	function __construct()
	{
		try {
			$this->db = Db::connect('database.center');
			//获取现有渠道ID
			$this->channel_id_list = Db::connect('database.portal')
				->table('tp_channel') ->join ()
				->field('channel_id')->having ()
				->distinct(TRUE)
				->select();
//			dump($this->channel_id_list);
		} catch (Exception $e) {
		}
	}

	//生成报表
	function generateLog(Request $request)
	{
		//日期获取
		if (empty($request->param('date'))) {
			//统计昨天 注册日
			$this->date      = date('Y-m-d', strtotime('-1 day'));
			$this->date_time = strtotime($this->date);
		} else {
			$this->date = $request->param('date');
			//验证规则
			$rule     = ['date' => 'date'];
			$validate = new Validate($rule);
			$result   = $validate->check(['date' => $this->date]);
			if (!$result) {
				echo '日期传参错误';
				die;
			}
			$this->date_time = strtotime($this->date);
		}
		echo "--{$this->date} 更新留存数据开始--" . PHP_EOL;

		//统计的日期
		$this->countWhereBetween = ["{$this->date} 0:00:00", "{$this->date} 23:59:59"];


		foreach ($this->channel_id_list as $value) {
			//多次请求该接口 处理 不重复插入
			$map['date']       = $this->date;
			$map['channel_id'] = $value['channel_id'];
			$keepReport        = $this->db->table('tbl_keep_report')
				->where($map)
				->find();
			if (empty($keepReport)) {
				//获取注册人数
				$add['register_num'] = $this->db->table('tbl_register_log')
					->whereBetween('date', $this->countWhereBetween)
					->where(['p1' => $value['channel_id']])
					->count();
				//注册日期
				$add['date']       = $map['date'];
				$add['channel_id'] = $value['channel_id'];
				//插入昨天的留存几基本数据
				$result = $this->db->table('tbl_keep_report')
					->insert($add);
				if (empty($result)) {
					echo "{$this->date}生成的留存根数据失败";
				}
				echo "{$this->date}生成的留存根数据成功";
			}
		}


		//遍历数组中每一天
		foreach (self::KEEP_DAYS as $day => $field) {
			foreach ($this->channel_id_list as $channel) {
				$this->countKeep($day, $field, $channel['channel_id']);
			}
		}

		echo "--每日更新留存数据完毕--" . PHP_EOL;
		//运行结束
	}

	private function countKeep($day, $field, $channel_id)
	{
		//时间超过今天 则直接返回0
		//统计那天的日期
		$map['date']       = date('Y-m-d', strtotime("-{$day} day", $this->date_time));
		$map['channel_id'] = $channel_id;
		$keepReport        = $this->db->table('tbl_keep_report')
			->where($map)
			->fetchSql ()
			->find();
		//		dump($keepReport);

		//如果那那天的留存报表不存在
		if (empty($keepReport)) {
			return;
		}
		//这个是注册当日的玩家列表 日期为注册日
		$role_res = $this->db->table('tbl_register_log')
			->whereBetween('date', ["{$map['date']} 0:00:00", "{$map['date']} 23:59:59"])
			->where(['p1' => $channel_id])
			->field("roleid")
			->select();

		//如果没有注册 直接返回
		if (empty($role_res)) {
			return;
		}
		//玩家列表
		$role_list = array_column($role_res, 'roleid');
		//玩家列表分页总页数
		$page = ceil(count($role_list) / self::ROLE_PAGE_SIZE);

		$count = 0;
		//分页获取玩家登陆 $k 页数偏移量
		for ($k = 0; $k < $page; $k++) {
			// 数组 , 开始元素下标 ,截取长度
			$tmp_role = array_slice($role_list, $k * self::ROLE_PAGE_SIZE, self::ROLE_PAGE_SIZE);

			$where = [
				'roleid' => ['in', $tmp_role]
			];
			$count += $this->db->table('tbl_login_log')
				->where($where)
				->whereBetween('date', $this->countWhereBetween)
				->count('distinct roleid');
		}
		//拼接 更新字段名
		$update = [
			$field => $count
		];

		$result = $this->db->table('tbl_keep_report')
			->where($map)
			->update($update);
		if (empty($result)) {
			echo $map['date'] . $field . '没有变化' . PHP_EOL;
		}
		//存在  更新报表
	}
}