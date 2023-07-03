<?php
/**
 * 定时生成统计数据
 * 通过crontab curl 定时发起一些请求
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/4/30
 * Time: 12:05
 */

namespace app\index\controller;


use app\index\model\Http;
use Exception;
use think\Db;
use think\Log;

class Task
{
	private $now_time; //现在时间
	private $yesterday_time; //  //获取昨天 凌晨时间戳

	private $yesterday_date; //昨天日期
	private $yesterday_end_time; //昨天日期最晚时间戳

	private $yesterday_int ; //昨天日期数字

	private $db;

	public function __construct()
	{

		try {
			$this->db = Db::connect('database.center');
		} catch (Exception $e) {

		}
		//当前时间戳
		$this->now_time = time();
		//获取昨天 凌晨时间戳
		$this->yesterday_time = strtotime('yesterday', $this->now_time);
		//昨天日期
		$this->yesterday_date = date('Y-m-d', $this->yesterday_time);
		//昨天日期数字
		$this->yesterday_int = date('Ymd', $this->yesterday_time);
	}

	// 测试 重置活动 判断是否有
	public function resetDoubleShop()
	{
		echo "\n  ------本次运行重置活动开始---------";
		//先获取重置活动
		$map = [
			'activity_id' => 8001, //重置活动ID
			'delete'      => 0,
		];
		$between = [
			date("Y-m-d H:00:00"), //当前小时整
			date("Y-m-d H:59:59"), //当前小时末
		];
		$result  = Db::connect('database.portal')->table('tp_activity_config')->where($map)->whereBetween('start_time', $between)->find();
		if (empty($result)) {
//			Log::record( "{$between[0]} 没有要发送的活动重置推送",'http');
			echo "\n {$between[0]} 没有要发送的活动重置推送";
			die;
		}

		$servers = Db::connect('database.uwinslot')->table('tp_server')->select();
		if (empty($servers)){
			echo "\n 重置活动TD-服务器配置异常";
			die;
		}
		foreach ($servers as $server){
			$url = 'http://'.$server['ip'].':'.$server['gm_port'];
			//默认写通信日志
			$result = callGameServer($url ,'resetDoubleShop' ,$server);
			if ($result == false){
				echo "\n 重置活动TD-服务器配置异常".json_encode($server);
				continue;
			}
		}
		echo "\n  ----------本次运行完毕---------";
	}

	/**
	 * 每小时查询 重置活动
	 * @throws \think\Exception
	 */
	public function restDoubleShopActivity()
	{
		//先获取重置活动
		$map = [
			'activity_id' => 8001, //充值活动
			'delete'      => 0,
		];

		$between = [
			date("Y-m-d H:00:00"), //当前小时整
			date("Y-m-d H:59:59"), //当前小时末
		];
		$result  = Db::connect('database.portal')->table('tp_activity_config')->where($map)->whereBetween('start_time', $between)->find();
		if (empty($result)) {
			echo "\n {$between[0]} 没有要发送的活动重置推送";
			die;
		}
		//给td发送消息
		$result = Http::TdHttpsPost('resetDoubleShop', '重置活动');
		if ($result['status'] != 1) {
			echo "\n {$between[0]}重置活动推送异常";
			die;
		}
		echo "\n {$between[0]}重置活动推送成功";
		die;
	}

	/**
	 * 每日总报表统计
	 * @throws \think\Exception
	 * @throws \think\exception\PDOException
	 */
	function dayCount()
	{

		//TODO:: 直接加索引约束 该报表只能每天产生一条

		//昨日结束时间戳
		$this->yesterday_end_time = $this->yesterday_time + 86399;

		//统计每日登陆人数
		$login_user_count = $this->db->table('tbl_login_log'.	$this->yesterday_int)->count('DISTINCT role_id');
		//统计每日注册人数
		$register_user_count = $this->db->table('tbl_user_info')->whereBetween('createTime', [
			$this->yesterday_time,
			$this->yesterday_end_time
		])->count();

		$insert = [
			'day'           => $this->yesterday_date,
			'login_user'    => $login_user_count,
			'register_user' => $register_user_count,
			'create_time'   => date('Y-m-d H:i:s', $this->now_time),
		];
		$this->db->table('tbl_day_count')->insert($insert);
		echo "\n--- {$insert['create_time']} 数据统计完毕入库";
		exit;
	}




	//游戏每日报表统计
	function GameDayCount()
	{

	}




	/**
	 * 玩家每日报表统计
	 */
	function UserDayCount()
	{
		echo "\n--- {$this->yesterday_date} 玩家每日报表统计结束 ---";
		//查找昨天登录日志 ,获取昨天登录用户
		$roles = $this->db->table('tbl_login_log' . 	$this->yesterday_int)->distinct(TRUE)->field(['role_id'])->select();
		//统计每个玩家的在线时长
		foreach ($roles as $value) {
			$online_time_count = $this->getOnlineTimeCountByRoleId($value['role_id']);
			$insert            = [
				'role_id'           => $value['role_id'],//玩家ID
				'date'              => $this->yesterday_date,//昨天时间
				'create_time'       => date('Y-m-d H:i:s', $this->now_time),//创建时间
				'online_time_count' => $online_time_count,//在线时长统计
			];
			$result            = $this->db->table('tbl_role_day_count')->insert($insert,TRUE);
			if ($result == 1) {
				echo "\n--- 玩家ID: {$value['role_id']} 日报表统计入库 ";
			} else {
				echo "\n--- 玩家ID: {$value['role_id']} 日报表统计入库失败";
			}
		}
		echo "\n--- {$this->yesterday_date} 玩家每日报表统计结束 ---";
		exit;
	}


	//统计一个玩家昨天 在线时长
	private function getOnlineTimeCountByRoleId($role_id)
	{
		$field = [
			'type',
			'create_time',
			'online_time'
		];
		$where = [
			'role_id' => $role_id,
		];
		//在线时长统计
		$online_time_count = 0;
		$login_log         = $this->db->table('tbl_login_log' .	$this->yesterday_int)->field($field)->where($where)->select();
		//预处理
		if ($login_log[0]['type'] == 2) {
			//数据创建时间 减去昨天凌晨时间 便是第一次下线  计算凌晨到下线时间 为在线时间
			$login_log[0]['online_time'] = $login_log[0]['create_time'] - $this->yesterday_time;
		} elseif ($login_log[0]['type'] == 3 && $login_log[0]['type']['online_time'] > 864399 ) {
			//玩家第一天为零点事件 玩家就属于整天在线
			return 86400;
		}
		//在线时间累加
		foreach ($login_log as $key => $value) {
			//在线时间相加
			$online_time_count += $value['online_time'];
		}
		return $online_time_count;
	}
}