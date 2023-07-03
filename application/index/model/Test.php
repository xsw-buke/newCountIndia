<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/4/21
 * Time: 11:08
 */

namespace app\index\model;


use think\Cache;
use think\Db;
use think\Request;
use tp5redis\Redis;
use think\Exception;

class Test
{
	protected $db;

	public function __construct()
	{
		try {
			$this->db = Db::connect('database.portal');
		} catch (Exception $e) {
		}

	}

	/**
	 * 二维数组根据pid生成多维树
	 *
	 * @param $list  父子级拼接数组传入
	 * @param $pid   父级ID字段
	 * @param $child 子集
	 *
	 * @return array
	 */
	function listToTree($list, $pid = 'pid', $child = 'children')
	{
		$tree = [];// 创建Tree
		if (is_array($list)) {
			// 创建基于主键的数组引用
			$refer = [];
			foreach ($list as $key => $data) $refer[$data['id']] = &$list[$key];

			foreach ($list as $key => $data) {
				// 判断是否存在parent
				$parentId = $data[$pid];

				if (0 == $parentId) {
					$tree[]             = &$list[$key];
					$list[$key][$child] = [];
				} else {
					if (isset($refer[$parentId])) {
						$parent           = &$refer[$parentId];
						$parent[$child][] = &$list[$key];
					}
				}
			}
		}
		return $tree;
	}

	/**
	 * 根据相关键值生成父子关系
	 *
	 * @param array  $arr1     数组1
	 * @param array  $arr2     数组2
	 * @param string $arr1_key 数组1对应的键值
	 * @param string $arr2_key 数组2对应的父级键值
	 * @param string $child    合并的数组键值
	 */
	function listToTree2(&$arr1, $arr2, $arr1_key = 'id', $arr2_key = 'pid', $child = 'children')
	{
		foreach ($arr1 as $i => &$item1) {
			foreach ($arr2 as $j => $item2) {
				if ($item1[$arr1_key] == $item2[$arr2_key]) {
					if (!isset($item1[$child]) || !is_array($item1[$child])) $item1[$child] = [];
					$item1[$child][] = $item2;
				}
			}
		}
	}


	/**
	 * 二维数组根据键值排序
	 *
	 * @param array  $array 要排序的数组
	 * @param string $keys  要用来排序的键名
	 * @param string $type  默认为降序排序
	 *
	 * @return array
	 */
	function arraySort($array, $keys, $type = 'desc')
	{
		//将排序的键名作为键值
		$keysValue = $newArray = [];
		foreach ($array as $k => $v) {
			$keysValue[$k] = $v[$keys];
		}

		($type == 'asc' || $type == 'ASC') ? asort($keysValue) : arsort($keysValue);
		reset($keysValue); //重置指针

		foreach ($keysValue as $k => $v) {
			$newArray[$k] = $array[$k];
		}

		return array_values($newArray); //重置键值
	}

	/**
	 * 获取渠道对应活动列表
	 * @return string
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\ModelNotFoundException
	 * @throws \think\exception\DbException
	 */
	public function test()
	{
		$channel  = $this->db->table('tp_channel c')
			->select();
		$hallList = $this->db->table('tp_hall_version hv')
			->select();


		$this->listToTree2($channel, $hallList, 'hid', 'id', 'hall');

		//

		return jsonSuccess($channel, '1');
	}


	/**
	 * @param        $arr1     //父数组
	 * @param        $arr2     //子数组
	 * @param string $arr1_key //父键名
	 * @param string $arr2_key //子键名
	 * @param string $child    //子键名
	 *
	 * @return array
	 */
	function listToTree244(&$arr1, $arr2, $arr1_key = 'id', $arr2_key = 'pid', $child = 'children')
	{
		$array = [];
		//基本结构 两层foreach
		foreach ($arr1 as $i => $value1) {
			foreach ($arr2 as $j => $value2) {
				//                if ($array[])

			}
		}
		return $array;


	}


	function test2()
	{
		//香蕉堆数 和数量
		$piles = [3, 6, 7, 11, 9, 88, 6];
		//        $piles = [3, 9, 7, 11];
		//        $piles = [3, 9, 7, 10, 15];
		//保安离开时间
		$H = 8;
		//总堆数
		$N = count($piles);
		//堆数大于小时 ,吃不完
		if ($N > $H) {
			var_dump('吃不完');
			die;
		}
		//最大速度不超过 每小时 吃掉最大一堆
		$max_k = max($piles);
		for ($k = 1; $k <= $max_k; $k++) {
			//清零计时
			$j    = 0;
			$sign = TRUE;
			foreach ($piles as $key => $value) {
				//按最低速度吃完 向上取整
				$j += ceil($value / $k);
				//时间累积 超过保安离开时间$H
				if ($j > $H) {
					$sign = FALSE;
					break;
				}
			}
			if ($sign == TRUE && $j >= $H) {
				echo "速度{$k}个/每小时,计时{$j}小时";
				die;
			}
		}
		var_dump('error');
		die;
	}


	function getCheckInfo(Request $request)
	{
		$channel_id      = htmlspecialchars($request->param('channel_id'));
		$channel_version = htmlspecialchars($request->param('channel_version'));

		$where = [
			'channel_id'      => $channel_id,
			'channel_version' => $channel_version,
			'delete'          => 0,
		];
		//审核状态  return 数据赋值
		$return = $this->db->table('tp_channel')
			->field('is_audit')
			->where($where)
			->find();

		if (empty($return)) {
			return jsonError('渠道异常');
		}
		//如果是审核版本
		if ($return['is_audit'] == 1) {
			return jsonSuccess($return, '审核版本');
		}

		$where2 = [
			'channel_id' => $channel_id,
			//            'new_version' => ['GT', $channel_version], //取出高级的版本
			'delete'     => 0,
		];
		$field  = [
			'old_version', //旧版本
			'new_version', //新版本
			'update_type', //更新类型
			'matching_mode', //建议更新
			'title', //标题
			'content', //内容
		];
		$result = $this->db->table('tp_channel_update')
			->field($field)
			->where($where2)
			->select();

		if (empty($result)) {
			$return['update_type'] = 0; //不需要更新
			return jsonSuccess($return, '不需要更新');
		}
		//我的版本
		$my_version_array = explode('.', $channel_version);
		//建议数组 每查询出一个 往里面塞
		$proposal_list = [];
		//强制 数组
		$force_list = [];
		$new_sign   = TRUE; //假设是最新版本
		$sign       = FALSE;//强制标记
		foreach ($result as $key => $value) {
			//假设是最新版本  TODO::假设好像有点蠢,  多余的判断了  whereBetween
			if ($new_sign) {
				$judge = $this->newVersionJudge($my_version_array, explode('.', $value['new_version']));
				//如果不是最新
				if ($judge == FALSE) {
					$new_sign = FALSE;
				}
			}

			//强制更新 精确匹配
			if ($value['update_type'] == 1 && $value['matching_mode'] == 1 && $value['old_version'] == $channel_version) {
				$sign                              = TRUE;
				$force_list[$value['new_version']] = $value;
				continue;
			}

			//强制更新 模糊匹配 判断
			if ($value['update_type'] == 1 && $value['matching_mode'] == 2) {


				/*    //尝试截取前两位
					//两个字符串截取 比较 101  和 10*  如果 10 =10 则判定成功
					$result = substr($value['old_version'], -3, 2) == substr($channel_version, -3, 2);
					if ($result == true) {
						$sign = true;
						$force_list[$value['new_version']] = $value;
						continue;
					}
					//尝试截取第一位
					//两个字符串截取 比较 101  和 1**  如果 1 = 1 则判定成功
					$result = substr($value['old_version'], -3, 1) == substr($channel_version, -3, 1);
					if ($result == true) {
						$sign = true;
						$force_list[$value['new_version']] = $value;
						continue;
					}*/
			}

			//有强制更新 不走建议更新
			if ($sign == TRUE) {
				continue;
			}

			//建议更新 精确匹配
			if ($value['update_type'] == 2 && $value['matching_mode'] == 1 && $value['old_version'] == $channel_version) {
				//记录 罗列出来要找出最高建议版本
				$proposal_list[$value['new_version']] = $value;
				continue;
			}


			//建议更新 模糊匹配 判断
			if ($value['update_type'] == 2 && $value['matching_mode'] == 2) {

				//尝试截取前两位
				//两个字符串截取 比较 101  和 10*  如果 10 =10 则判定成功
				$result = substr($value['old_version'], -3, 2) == substr($channel_version, -3, 2);
				if ($result == TRUE) {
					//记录 罗列出来要找出最高建议版本
					$proposal_list[$value['new_version']] = $value;
					continue;
				}
				//尝试截取第一位
				//两个字符串截取 比较 101  和 1**  如果 1 = 1 则判定成功
				$result2 = substr($value['old_version'], -3, 1) == substr($channel_version, -3, 1);
				if ($result2 == TRUE) {
					//记录 罗列出来要找出最高建议版本
					$proposal_list[$value['new_version']] = $value;
					continue;
				}
			}
		}
		//最新版本判定
		if ($new_sign) {
			$return['update_type'] = 0; //不需要更新
			return jsonSuccess($return, '已经是最新版本');
		}

		//强制更新  确认是强制标记
		if ($sign == TRUE) {
			$max = max(array_keys($force_list));
			//取出最高版本
			$return['new_version'] = $force_list[$max]['new_version'];
			$return['title']       = $force_list[$max]['title'];
			$return['content']     = $force_list[$max]['content'];
			$return['update_type'] = 1; //强制更新
			return jsonSuccess($return, '强制更新,返回最高版本');
		}


		//没走强制更新

		//取最大的键的值 为最新版本
		$max = max(array_keys($proposal_list));
		//取出最高版本
		$return['new_version'] = $proposal_list[$max]['new_version'];
		$return['title']       = $proposal_list[$max]['title'];
		$return['content']     = $proposal_list[$max]['content'];
		$return['update_type'] = 2; //建议更新
		return jsonSuccess($return, '建议更新,返回最高版本');
	}

	/**
	 *    * 最新版本判定
	 *
	 * @param array $my_version_array  我的版本号
	 * @param array $new_version_array 更新条件中的版本号
	 *
	 * @return bool
	 */
	private function newVersionJudge(array $my_version_array, array $new_version_array)
	{
		//更新中 new_version 第一位版本号大于 my_version版本号
		if ($new_version_array[0] > $my_version_array[0]) {
			return FALSE;
		}
		//第一位相等 ,第二位最新版本大于我的版本
		if ($new_version_array[0] == $my_version_array[0] && $new_version_array[1] > $my_version_array[1]) {
			return FALSE;
		}
		//第一位相等 第二位相等 第三位版本大于我的版本
		if ($new_version_array[0] == $my_version_array[0] && $new_version_array[1] == $my_version_array[1] && $new_version_array[2] > $my_version_array[2]) {
			return FALSE;
		}
		return TRUE;
	}

	function getTest()
	{
		return 1111;
		//game_log 统计重跑 startDate 开始时间 duration 持续小时
		//http://120.79.178.127:8811/index.php/index/Game_Log/countGameLog?startDate=2020-06-01&duration=96
	}

	function testKY()
	{
		echo 111;
		die;
	}

	function sql()
	{
		$sql = "SELECT 
	coalesce(game_id, '小计') as game_id2,
	coalesce(hour, '总计') as 'hour',
	sum(spin_count) as spin_count,
	sum(spin_cost) as spin_cost,
	sum(spin_get) as spin_get,
	sum(free_spin_get) as free_spin_get,
	sum(tiny_game_get) as tiny_game_get,
	sum(free_spin_tiny_game_get) as free_spin_tiny_game_get 
FROM newCenter.tbl_game_log 
WHERE `date` BETWEEN '2020-06-05' AND '2020-06-05' GROUP BY `hour`,game_id WITH ROLLUP;";

	}

	function test3()
	{
		$noticeJson = Redis::hKeys('getChannelUpdateList');
		dump($noticeJson);
	}
	function getKeys()
	{
//		Redis::del('getMaintainNotice','getAnnounceList');
		$noticeJson[] = Redis::hKeys('getMaintainNotice');
		$noticeJson[] = Redis::hKeys('getAnnounceList');
		dump($noticeJson);
	}

	/**
	 * 留存统计
	 */
	public function keep()
	{
		echo date('Y-m-d H:i:s');
	}

}