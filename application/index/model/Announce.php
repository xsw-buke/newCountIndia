<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/4/28
 * Time: 15:57
 */

namespace app\index\model;


use think\Cache;
use think\Db;
use think\Log;
use think\Request;
use tp5redis\Redis;
use think\Exception;


class Announce
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
	 * 通过渠道获取所有普通公告 全局公告+渠道下公告
	 *
	 * @param Request $request
	 *
	 * @return string
	 */
	function getAnnounceByCid(Request $request)
	{
		//渠道号
		$channel_id = htmlspecialchars($request->param('channel_id'));
		//取出全局公告
		$allJson = Redis::hGet('getAnnounceList', '0');
		if ($allJson == FALSE) {
			$allNotice = [];
		} else {
			$allNotice = json_decode($allJson, TRUE);
		}
		//取出  渠道公告
		$channelJson = Redis::hGet('getAnnounceList', $channel_id);
		//没有渠道公告
		if ($channelJson == FALSE) {
			$notices = $allNotice;
		} else {
			//合并返回
			$channelNotice = json_decode($channelJson, TRUE);
			$notices       = array_merge($allNotice, $channelNotice);
		}

		$data = [];
		//普通公告时间筛选  未在时间范围内不发放
		$nowTime = time();
		foreach ($notices as $notice) {
			if (strtotime($notice['start_time']) < $nowTime && strtotime($notice['end_time']) > $nowTime) {
				$data[] = $notice;
			}
		}
		return jsonSuccess($data, '查询渠道下公告成功');
	}

	//玩家反馈
	function feedback(Request $request)
	{

		$insert = [
			'roleId'          => intval($request->param('roleId')),
			'channel_id'      => htmlspecialchars($request->param('channel_id')),
			'feedback'        => htmlspecialchars($request->param('feedback')),
			'status'          => 1, //状态1 待处理
			'time'            => date('Y-m-d H:i:s'),
			'email'           => htmlspecialchars($request->param('email')),
			'channel_version' => htmlspecialchars($request->param('channel_version')),
			'hall_version'    => htmlspecialchars($request->param('hall_version')),
			'oid'             => htmlspecialchars($request->param('oid')),
		];


		$result = Db::connect('database.center')
			->table('tbl_feedback_info')
			->insert($insert);
		if (empty($result)) {
			Log::record('反馈入库失败:' . json_encode($result), 'error');
			return jsonError('用户反馈入库失败');
		}
		return jsonSuccess('用户反馈入库成功');
	}

}