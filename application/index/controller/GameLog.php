<?php
/**
 * 小时游戏日志生成
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/6/3
 * Time: 10:16
 */

namespace app\index\controller;

use think\Db;
use think\Exception;
use think\Request;

class GameLog extends TaskBase
{
	//日期
	private $date;
	//日期数字 找表用的
	private $date_int;
	private $hour;
	private $db;
	private $dateWhereBetween;
	private $gameList;

	//压注消耗
	const SPIN_COST = 201;
	//压注获得
	const SPIN_GET = 202;
	//freeSpin 获得
	const FREE_SPIN_GET = 203;
	//freeSpin 获得游戏道具, 小游戏获得
	const  TINY_GAME_GET = 204;
	//freeSpinTinyGameGet ;
	const  FREE_SPIN_TINY_GAME_GET = 205;
	//查询值
	const  CHANGE_FIELD = 'p1';

	function __construct()
	{
		try {
			//游戏列表的获取
			$this->gameList = Db::connect('database.portal')
				->table('tp_game')
				->field('game_id')
				->distinct(TRUE)
				->select();
			$this->gameList = array_column($this->gameList, 'game_id');
			//中心报表库连接
			$this->db = Db::connect('database.center');
		} catch (Exception $e) {
		}

	}

	//确定时间范围 轮训
	function countGameLog(Request $request)
	{
		//如果是新的一个小时定时任务
		if ($request->param('newHour') == TRUE) {
			$time = strtotime('-1 hour');
			//时间
			$this->date = date('Y-m-d', $time);
			//表后数字
			$this->date_int = date('Ymd', $time);
			//目前小时
			$this->hour                = date('H', $time);
			$this->dateWhereBetween[0] = $this->date . " {$this->hour}:00:00";
			$this->dateWhereBetween[1] = $this->date . " {$this->hour}:59:59";
			//删除这一个小时的投注统计
			$this->_deleteLog();
			//定时任务生成上一个小时入库
			$this->_generateLog();
			die;
		}
		//报表重跑  获得两个参数 ,一个为开始时间
		$startDate = $request->param('startDate');
		//持续小时
		$durationHour = $request->param('duration');

		$time = strtotime($startDate);
		//重跑$hour个小时 的报表
		for ($i = $durationHour; $i > 0; $i--) {
			//时间
			$this->date = date('Y-m-d', $time);
			//表后数字
			$this->date_int = date('Ymd', $time);
			//目前小时
			$this->hour                = date('H', $time);
			$this->dateWhereBetween[0] = $this->date . " {$this->hour}:00:00";
			$this->dateWhereBetween[1] = $this->date . " {$this->hour}:59:59";

			//删除这一个小时的投注统计
			$this->_deleteLog();
			//定时任务生成上一个小时入库
			$this->_generateLog();
			/*
						echo "<br />";
						dump($this->dateWhereBetween);*/
			//变数,时间加上3600秒
			$time += 3600;
		}
	}

	private function _generateLog()
	{
		echo "日期:{$this->date} 小时:{$this->hour} - 生成游戏小时日志开始...... " . self::PHP_EOL;

		//迭代游戏
		foreach ($this->gameList as $game_id) {
			// 获取时间段内 该游戏的压注玩家列表
			$roleList = $this->getRoleList($game_id);
			//这个游戏这段时间没有人玩
			if (empty($roleList)) continue;

			//遍历玩家列表  得到该玩家在上个小时 这个游戏  统计的数据
			foreach ($roleList as $roleId) {
				$map = [
					'roleid' => $roleId,
					'p3'     => $game_id,
					'esrc'   => self::SPIN_COST,//压注
				];
				$add = [
					'date'    => $this->date,
//					'hour'    => $this->hour, //去除字段 用datetime格式代替了 方便时间范围查询
					'role_id' => $roleId,
					'game_id' => $game_id,
					'date_time' => $this->date . " {$this->hour}:00:00",
				];

				//spin_cost投注消耗 和 spin_count投注总次数
				$count = $this->db->table('tbl_gold_log_' . $this->date_int)
					->field([
						'count(*) as spin_count',
						'sum(p1) as spin_cost'
					])
					->where($map)
					->whereBetween('date', $this->dateWhereBetween)
					->find();
				$count['spin_cost'] = abs($count['spin_cost']);
				$add   = array_merge($add, $count);

				//压注获得
				$map['esrc']     = self::SPIN_GET;
				$add['spin_get'] = $this->db->table('tbl_gold_log_' . $this->date_int)
					->field(self::CHANGE_FIELD)
					->where($map)
					->whereBetween('date', $this->dateWhereBetween)
					->sum(self::CHANGE_FIELD);
				//FREE_SPIN_GET 获得
				$map['esrc']          = self::FREE_SPIN_GET;
				$add['free_spin_get'] = $this->db->table('tbl_gold_log_' . $this->date_int)
					->field(self::CHANGE_FIELD)
					->where($map)
					->whereBetween('date', $this->dateWhereBetween)
					->sum(self::CHANGE_FIELD);
				//小游戏获取得
				$map['esrc']          = self::TINY_GAME_GET;
				$add['tiny_game_get'] = $this->db->table('tbl_gold_log_' . $this->date_int)
					->field(self::CHANGE_FIELD)
					->where($map)
					->whereBetween('date', $this->dateWhereBetween)
					->sum(self::CHANGE_FIELD);
				//FREE_SPIN_TINY_GAME_GET 小游戏获得
				$map['esrc']                    = self::FREE_SPIN_TINY_GAME_GET;
				$add['free_spin_tiny_game_get'] = $this->db->table('tbl_gold_log_' . $this->date_int)
					->field(self::CHANGE_FIELD)
					->where($map)
					->whereBetween('date', $this->dateWhereBetween)
					->sum(self::CHANGE_FIELD);
				$result                         = $this->db->table('tbl_game_log')
					->insert($add);
				if ($result == FALSE) {
					echo "玩家ID:{$roleId};游戏ID:{$game_id}日志入库失败" . PHP_EOL;
				}

				// 输出日志
				$msg = '时间: ' . $this->date . '小时:' . $this->hour;
				$msg .= ' - 会员: ' . $roleId;
				$msg .= ' - 游戏: ' . $game_id;
				$msg .= ' - 投注次数: ' . $add['spin_count'];
				$msg .= ' - 总投注: ' . $add['spin_cost'];
				$msg .= ' - 投注获得: ' . $add['spin_get'];
				$msg .= ' - FREE_SPIN获得: ' . $add['free_spin_get'];
				$msg .= ' - TINY_GAME获得: ' . $add['tiny_game_get'];
				$msg .= ' - FREE_SPIN_TINY_GAME_GET获得: ' . $add['free_spin_tiny_game_get'];
				echo $msg . PHP_EOL;
			}

			//获取所有游戏
		}
	}

	/**
	 * 获取上一个小时 所有游戏玩家列表
	 *
	 * @param $game_id
	 *
	 * @return array|bool
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\ModelNotFoundException
	 * @throws \think\exception\DbException
	 */
	private function getRoleList($game_id)
	{
		//游戏ID为这个 有压注消耗的
		$map = [
			'p3'   => $game_id,
			'esrc' => self::SPIN_COST, //压注消耗
		];
		//前期玩家不用分页
		$list = $this->db->table('tbl_gold_log_' . $this->date_int)
			->field(['roleid'])
			->where($map)

			->whereBetween('date', $this->dateWhereBetween)
			->distinct('true')
			->select();
		if (empty($list)) return FALSE;

		return array_column($list, 'roleid');

	}

	private function _deleteLog()
	{
		echo "日期:{$this->date};小时:{$this->hour} - 删除旧数据开始......" . PHP_EOL;
		$map    = [
			'date_time' =>  "{$this->date} {$this->hour}:00:00",
		];
		$result = $this->db->table('tbl_game_log')
			->where($map)
			->delete();
		if ($result == FALSE) {
			echo "日期: {$this->date} 小时: {$this->hour} tbl_game_log 没有数据删除" . self::PHP_EOL;
			return;
		}
		echo "日期: {$this->date} 小时: {$this->hour} tbl_game_log 删除成功" . self::PHP_EOL;
	}


}