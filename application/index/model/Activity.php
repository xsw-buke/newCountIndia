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

class Activity
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
	 * 获取所有活动
	 * @return string
	 */
	public function getActivityList()
	{
		//        Redis::flushDB();
		$list = Redis::get('getActivityList');
		if (empty($list)) {
			return jsonError('获取所有活动失败');
		}
		return jsonSuccess(json_decode($list), '获取所有活动成功');
	}


	/**
	 * 通过渠道号获取活动列表
	 *
	 * @param Request $request
	 *
	 * @return string
	 */
	public function getActivityByCid(Request $request)
	{
		//渠道号
		$channel_id      = htmlspecialchars($request->param('channel_id'));
		$channel_version = htmlspecialchars($request->param('channel_version'));

		//骚东西 如果前后台都用tp5   使用缓存打标签 好像能解决 关联数组问题

		//渠道加版本唯一
		$list = Redis::hGet('getChannelActivityList', $channel_id . '-' . $channel_version);

		if ($list == FALSE) {
			return jsonError('获取失败');
		}
		return jsonSuccess(json_decode($list), '查询渠道下所有活动成功');
	}


	/**
	 * 获取渠道对应活动列表
	 * @return string
	 */
	public function getChannelActivityList()
	{
		//骚东西 如果前后台都用tp5   使用缓存打标签 好像能解决 关联数组问题
		//渠道-大厅-活动列表
		$hallList = Redis::hVals('getChannelActivityList');
		//哈希只能存字符串 必须进行json 转义

		//如果缓存存在 使用缓存
		if (empty($hallList)) {
			return jsonError('获取所有渠道下所有活动失败');
		}
		$result = array_map(function ($v) {
			return json_decode($v, TRUE);
		}, $hallList);

		return jsonSuccess($result, '获取所有渠道下所有活动成功');

	}

	/**
	 *  获取礼包列表
	 * @return string
	 */
	function getPackageList()
	{
		$list = Redis::get('getPackageList');
		//如果缓存存在 使用缓存

		if (empty($list)) {
			return jsonError('获取所有礼包失败');
		}
		return jsonSuccess(json_decode($list, TRUE), '获取所有礼包成功');
	}


	/**
	 * 获取所有道具
	 * @return string
	 */
	public function getPropList()
	{
		//        Redis::flushDB();
		$list = Redis::get('getPropList');

		if (empty($list)) {
			return jsonError('获取所有道具失败');
		}
		return jsonSuccess(json_decode($list, TRUE), '获取所有道具成功');
	}


	/**
	 * 通过渠道号获取活动列表
	 * @return string
	 */
	public function getAllChannelActivity()
	{
		//渠道加版本唯一
		$list = Redis::hGetAll('allChannelActivityList');
		if ($list == FALSE) {
			return jsonError('获取失败');
		}
		foreach ($list as $key => $value) {
			$list[$key] = json_decode($value, TRUE);
		}

		return jsonSuccess($list, '获取所有渠道下所有活动成功');
	}

	/**
	 * 获取该渠道下的所有活动
	 *
	 * @param Request $request
	 *
	 * @return string
	 */
	public function getActivityListByChannelVersion(Request $request){
		$key = $request->param('channel_id').'-'.$request->param('channel_version');
		//渠道加版本唯一
		$list = Redis::hGet('allChannelActivityList',$key);
		if ($list == FALSE) {
			return jsonError('获取失败');
		}

		return jsonSuccess(json_decode($list,true), '获取该渠道下的所有活动成功');
	}
}