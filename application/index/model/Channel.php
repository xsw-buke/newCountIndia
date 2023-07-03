<?php
/**
 * 渠道控制器
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/5/5
 * Time: 12:23
 */

namespace app\index\model;

use think\Db;
use think\Exception;
use think\Log;
use think\Request;
use tp5redis\Redis;


class Channel
{
	//旧版本格式 分段长度 4
	const OLD_VERSION_FORMAT_LENGTH_SPAN = 4;
	protected $db;

	public function __construct()
	{
		try {
			$this->db = Db::connect('database.portal');
		} catch (Exception $e) {
		}

	}

	//获取维护公告 获取渠道审核及更新
	function getCheckInfo(Request $request)
	{
		$noticeJson = Redis::hGet('getMaintainNotice', $request->param('channel_id'));
		//有维护公告
		if ($noticeJson == TRUE) {
			$notice = json_decode($noticeJson, TRUE);
			$time   = time();
			//维护时间内
			if (strtotime($notice['start_time']) < $time && strtotime($notice['end_time']) > $time) {
				//表示维护
				$notice['maintain'] = TRUE;
				return jsonSuccess($notice, '正在维护中');
			}
		}

		$key         = htmlspecialchars($request->param('channel_id')) . '-' . htmlspecialchars($request->param('channel_version'));
		$channelJson = Redis::hGet('getChannelUpdateList', $key);
		if ($channelJson == FALSE) {
			Log::record(date('Y-m-d H:i:s') . '获取渠道审核及更新失败:' . $key, 'error');
			return jsonError('获取渠道审核及更新失败');
		}

		$result = json_decode($channelJson, TRUE);
		if (isset($result['new_version']) && $result['new_version'] == $request->param('channel_version')){
			$result['update_type'] =  0;//不用更新
			unset($result['title']);
			unset($result['content']);
		}
		return jsonSuccess($result, '获取渠道审核及更新成功');
	}

	/*//获取所有渠道下的关联活动
	function getAllChannelActivity(){
		$noticeJson = Redis::hGetAll('getChannelUpdateList');
		dump($noticeJson);
	}*/

	/**
	 * 1 渠道 的审核状态判断 (审核版本不需要更新)
	 * 2 强制更新 走强制更新 找出最高版本  找出精确和模糊匹配最高版本
	 * 2.1 建议更新 在没有强制更新的情况下 找出精确和模糊匹配最高版本
	 *
	 * @param Request $request
	 *
	 * @return string
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\ModelNotFoundException
	 * @throws \think\exception\DbException
	 */
	/*function getCheckInfo2(Request $request)
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
		//获取三段结构
		$old_versionS = explode('.', $channel_version);
		$old_version0 = intval($old_versionS[0]);
		$old_version1 = $old_versionS[1] == '*' ? 9999 : str_pad(intval($old_versionS[1]), self::OLD_VERSION_FORMAT_LENGTH_SPAN, "0", STR_PAD_LEFT);
		$old_version2 = $old_versionS[2] == '*' ? 9999 : str_pad(intval($old_versionS[2]), self::OLD_VERSION_FORMAT_LENGTH_SPAN, "0", STR_PAD_LEFT);

		//组装成 数值数据格式
		$my_version = $old_version0 . $old_version1 . $old_version2;
		$where2     = [
			'channel_id'  => $channel_id,
			'old_version' => ['GT', $channel_version], //取出高级的版本
			'delete'      => 0,
		];
		$field      = [
			'old_version', //旧版本
			'new_version', //新版本
			'update_type', //更新类型
			'matching_mode', //建议更新
			'title', //标题
			'content', //内容
		];
		$result     = $this->db->table('tp_channel_update')
			->field($field)
			->where($where2)
			->select();
		//没有比该版本更高的了
		if (empty($result)) {
			$return['update_type'] = 0; //不需要更新
			return jsonSuccess($return, '已经是最新版本');
		}

		//建议更新数组 每查询出一个 往里面塞 键名为版本号
		$proposal_list = [];
		//强制 数组 每查询出一个 往里面塞 键名为版本号
		$force_list = [];
		//强制标记 当强制标记为true 则不会再走进建议查询判定
		$sign = FALSE;
		foreach ($result as $key => $value) {
			//强制更新 精确匹配 格式相等
			if ($value['update_type'] == 1 && $value['matching_mode'] == 1 && $value['old_version'] == $my_version) {
				$sign                                                       = TRUE;
				$force_list[$this->getVersionNumber($value['new_version'])] = $value;
				continue;
			}
			//强制更新 模糊匹配 判断
			if ($value['update_type'] == 1 && $value['matching_mode'] == 2) {
				//判断9999 有几个通配符 字符串截取
				$count           = substr_count($value['old_version'], '9999');
				$val_old_version = explode('9999', $value['old_version']);
				//两段通配 拿进去判断是否 判断第一段是否相等
				if ($count == 2 && $old_version0 == $val_old_version[0]) {
					$sign                                                       = TRUE;
					$force_list[$this->getVersionNumber($value['new_version'])] = $value;
				}
				//一段通配  第一段 和第二段是否相等
				//获取第一段长度
				$length = strlen($val_old_version[0]) - self::OLD_VERSION_FORMAT_LENGTH_SPAN;
				//获取第一段
				$val_old_version0 = substr($val_old_version[0], 0, $length);
				//获取第二段
				$val_old_version1 = intval(substr($val_old_version[0], $length, self::OLD_VERSION_FORMAT_LENGTH_SPAN));


				if ($count == 1 && $old_version0 == $val_old_version0 && $old_version1 == $val_old_version1) {
					$sign                                                       = TRUE;
					$force_list[$this->getVersionNumber($value['new_version'])] = $value;
				}
				continue; //不需要再走了
			}

			//有强制更新 不走建议更新了
			if ($sign) continue;


			//建议更新 精确匹配 版本匹配中
			if ($value['update_type'] == 2 && $value['matching_mode'] == 1 && $value['old_version'] == $my_version) {
				//记录 罗列出来要找出最高建议版本
				$proposal_list[$this->getVersionNumber($value['new_version'])] = $value;
				continue;
			}


			//建议更新 模糊匹配 判断
			if ($value['update_type'] == 2 && $value['matching_mode'] == 2) {

				//判断9999 有几个通配符 字符串截取
				$count = substr_count($value['old_version'], '9999');
				//
				$val_old_version = explode('9999', $value['old_version']);
				//两段通配 拿进去判断是否 判断第一段是否相等
				if ($count == 2 && $old_version0 == $val_old_version[0]) {
					$proposal_list[$this->getVersionNumber($value['new_version'])] = $value;
				}
				//一段通配  第一段 和第二段是否相等
				//获取第一段长度
				$length = strlen($val_old_version[0]) - self::OLD_VERSION_FORMAT_LENGTH_SPAN;
				//获取第一段
				$val_old_version0 = substr($val_old_version[0], 0, $length);
				//获取第二段
				$val_old_version1 = intval(substr($val_old_version[0], $length, self::OLD_VERSION_FORMAT_LENGTH_SPAN));

				//一段通配  第一段 和第二段是否相等
				if ($count == 1 && $old_version0 == $val_old_version0 && $old_version1 == $val_old_version1) {
					$proposal_list[$this->getVersionNumber($value['new_version'])] = $value;
				}
				continue; //不需要再走了
			}
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

		//两个数组都为空 ,未知错误 不走更新
		if (empty($proposal_list)) {
			$return['update_type'] = 0; //不需要更新
			Log::record('渠道更新审核异常 渠道:' . $channel_id . '版本:' . $channel_version, 'error');
			return jsonSuccess($return, '不需要更新2');
		}

		//没走强制更新
		//取最大的键的值 为最新版本
		$max = max(array_keys($proposal_list));
		//取出最高版本
		$return['new_version'] = $proposal_list[$max]['new_version'];
		$return['title']       = $proposal_list[$max]['title'];
		$return['content']     = $proposal_list[$max]['content'];
		$return['update_type'] = 2; //建议更新
		return jsonSuccess($return, '建议更新,最高版本:' . $return['new_version']);
	}*/

	/**
	 * 版本字符转换成数字
	 * 字符格式 1.2.3     | 1.2.*                 | 1.*.*
	 * 数值格式 100020003 | 100029999             | 199999999
	 * 匹配范围 100020003 | 100020000 - 100029999 | 100000000 -199999999
	 * 通配符 * 代表这一列所有版本匹配 且向下兼容 在数字中体现为 9999
	 * 不允许出现 1.*.1
	 *
	 * @param $new_version
	 *
	 * @return int|string
	 */
	/*private function getVersionNumber($new_version)
	{
		$old_versionS = explode('.', $new_version);
		//第一段处理
		$old_version = intval($old_versionS[0]);
		//第二段处理
		$old_version .= $old_versionS[1] == '*' ? 9999 : str_pad(intval($old_versionS[1]), self::OLD_VERSION_FORMAT_LENGTH_SPAN, "0", STR_PAD_LEFT);
		//第三段处理
		$old_version .= $old_versionS[2] == '*' ? 9999 : str_pad(intval($old_versionS[2]), self::OLD_VERSION_FORMAT_LENGTH_SPAN, "0", STR_PAD_LEFT);
		return $old_version;
	}*/
}