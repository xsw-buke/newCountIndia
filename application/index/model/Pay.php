<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/5/7
 * Time: 16:48
 */


namespace app\index\model;


use Ramsey\Uuid\Uuid;
use think\Db;
use think\Exception;
use think\Log;
use think\Request;
use tp5redis\Redis;

class Pay
{
	const STATUS_LACE_ORDER = 1; //状态下单
	const STATUS_PAYMENT = 2; //已付款
	const STATUS_GRANT = 3; //已发放


	const MD5_KEY = "d45uizhycLXwhKL3";

	const STATUS_MESSAGE = [
		1 => '下单',
		2 => '已付款',
		3 => '已发放',
	];

	//订单
	private $order;
	private $db;
	private $dateTime;

	public function __construct()
	{
		try {
			$this->db = Db::connect('database.center');
		} catch (Exception $e) {
		}

		$this->dateTime = date('Y-m-d H:i:s');
	}

	/**
	 * 下单
	 *
	 * @param Request $request
	 *
	 * @return string
	 * @throws \Exception
	 */
	function order(Request $request)
	{
		$signStr = [
			'activityId'  => intval($request->param('activityId')),//活动id
			'hallId'      => intval($request->param('hallId')),//大厅id
			'hallVersion' => htmlspecialchars($request->param('hallVersion')),//大厅版本
			'ts'          => intval($request->param('ts')),//用于校验的时间戳
			'roleId'      => htmlspecialchars($request->param('roleId')),//用户id
			'key'         => self::MD5_KEY,//秘钥
		];
		//验签名
		if (md5(http_build_query($signStr)) != $request->param('token')) {
			Log::record('下单验签失败:' . json_encode($signStr), 'pay');
			return jsonError('验签失败', $signStr);
		}
		$orderData    = [
			'activity_id'  => intval($request->param('activityId')),//活动id
			'hall_id'      => intval($request->param('hallId')),//大厅id
			'hall_version' => htmlspecialchars($request->param('hallVersion')),//大厅版本
			'order_time'   => intval($request->param('ts')),//用于校验的时间戳
			'role_id'      => htmlspecialchars($request->param('roleId')),//用户id
			'price'        => floatval($request->param('price')),//价格
			'package_id'   => intval($request->param('packageId')),//礼包id
			'channel_id'   => htmlspecialchars($request->param('channel_id')),//渠道ID
			'order'        => $this->generateTradeNo(),//订单号生成
			'create_time'  => $this->dateTime,//时间
			'update_time'  => $this->dateTime,//时间
			'pay_order'    => 0,
		];
		$orderLogData = [
			'order'     => $orderData['order'],
			'message'   => '下单',
			'date_time' => $this->dateTime,
			'role_id'   => $orderData['role_id'],
			'price'     => $orderData['price'],
		];

		//开启事务
		$this->db->startTrans();
		try {
			//订单表
			$result = $this->db->table('tbl_pay_order')
				->insert($orderData);

			if ($result != 1) {
				throw new Exception('订单入库失败');
			}
			//订单操作记录
			$result = $this->db->table('tbl_pay_order_log')
				->insert($orderLogData);
			if ($result != 1) {
				throw new Exception('订单日志下单记录失败');
			}
			// 提交事务
			$this->db->commit();
		} catch (\Exception $e) {
			// 回滚事务
			$this->db->rollback();
			Log::record($e->getMessage() . ':' . json_encode($signStr), 'pay');
			return jsonError($e->getMessage());
		}

		//订单号  加密 给TD校验的数据
		return jsonSuccess(['order' => $orderData['order']], '下单成功');
	}

	/**
	 * TODO::异步到账改
	 * 跳过计费+异步到账  ?应该留给TD做
	 *
	 * @param Request $request
	 *
	 * @return string
	 * @throws Exception
	 * @throws \Exception
	 */
	function test(Request $request)
	{
		//验签 TODO:: 确认是TD发来的
		$data = [
			'order'       => htmlspecialchars($request->param('oid')),
			'notify_data' => htmlspecialchars($request->param('notifyData'))
		];

		//redis订单锁  key time value
		if (Redis::exists($data['order'])) {
			return jsonError($data['order'] . '订单正被锁定');
		}


		//赋值
		$this->order = $data['order'];
		//订单锁生存时间十秒
		$result = Redis::setex($data['order'], 10, '1');
		//设置失败
		if (!$result) {
			return jsonError('订单锁缓存设置失败');
		}

		$map            = [
			'order' => $this->order,
		];
		$this->dateTime = date('Y-m-d H:i:s');

		//验订单
		$order_info = $this->db->table('tbl_pay_order')
			->where($map)
			->find();
		if (empty($order_info)) {
			return jsonError('订单不存在');
		}


		//验状态 如果不是下单状态
		if ($order_info['status'] != self::STATUS_LACE_ORDER) {
			//订单已经成功 或者已经下发
			return jsonError("异步通知订单状态异常" . self::STATUS_MESSAGE[$order_info['status']]);
		}

		//TODO::验金额

		//开启事务
		$this->db->startTrans();
		try {
			//更新订单状态
			$updateResult = $this->db->table('tbl_pay_order')
				->where($map)
				->update([
					'status'      => self::STATUS_PAYMENT, //更新状态
					'update_time' => $this->dateTime  //更新时间
				]);
			if (empty($updateResult)) {
				throw new Exception('更新校验状态失败');
			}
			//记录
			$orderLogData = [
				'order'     => $map['order'],
				'message'   => '已付款',
				'date_time' => $this->dateTime,
				'type'      => self::STATUS_PAYMENT,//付款
				'role_id'   => $order_info['role_id'],
				'price'     => $order_info['price'],
			];
			//订单操作记录
			$result = $this->db->table('tbl_pay_order_log')
				->insert($orderLogData);
			if ($result != 1) {
				throw new Exception('订单日志已到账记录失败');
			}
			// 提交事务
			$this->db->commit();
		} catch (\Exception $e) {
			// 回滚事务
			$this->db->rollback();
			Log::record($e->getMessage() . ':' . json_encode($data), 'pay');
			//订单解锁

			return jsonError($e->getMessage());
		}
		$chargeRole = [
			'oid'         => $order_info['order'],
			'roleId'      => $order_info['role_id'],
			'activityId'  => $order_info['activity_id'],
			'pid'         => $order_info['package_id'],
			'price'       => $order_info['price'],
			'hallId'      => $order_info['hall_id'],
			'hallVersion' => $order_info['hall_version'],
		];
		//发放接口
		$httpResult = Http::TdHttpsPost('charge', '通知发放', $chargeRole);
		if ($httpResult['status'] != 1) {
			Log::record('支付通知失败:' . json_encode($chargeRole), 'pay');
			return jsonError('已支付-通知发放失败');
		}
		if ($httpResult['data']['errorCode'] != 0) {
			Log::record('支付发放失败:' . json_encode($chargeRole), 'pay');
			return jsonError('支付发放失败');
		}

		$this->dateTime = date('Y-m-d H:i:s');
		//开启事务
		$this->db->startTrans();
		try {
			//更新订单状态
			$updateResult = $this->db->table('tbl_pay_order')
				->where($map)
				->update([
					'status'      => self::STATUS_GRANT,
					'update_time' => $this->dateTime
				]);
			if (empty($updateResult)) {
				throw new Exception('更新已发放状态失败');
			}
			//记录
			$orderLogData = [
				'order'     => $order_info['order'],
				'message'   => '已发放',
				'date_time' => $this->dateTime,
				'type'      => self::STATUS_GRANT,//付款
				'role_id'   => $order_info['role_id'],
				'price'     => $order_info['price'],
			];
			//订单操作记录
			$result = $this->db->table('tbl_pay_order_log')
				->insert($orderLogData);
			if ($result != 1) {
				throw new Exception('订单日志已发放记录失败');
			}
			// 提交事务
			$this->db->commit();
		} catch (\Exception $e) {
			// 回滚事务
			$this->db->rollback();
			Log::record($e->getMessage() . ':' . json_encode($data), 'pay');
			return jsonError($e->getMessage());
		}
		return jsonSuccess([], '发放成功');
	}

	/**
	 * 异步通知到账
	 *
	 * @param Request $request
	 *
	 * @return string
	 * @throws Exception
	 * @throws \Exception
	 */
	function asyn(Request $request)
	{
		$data = [
			'order'       => htmlspecialchars($request->param('oid')),
			'notify_data' => htmlspecialchars($request->param('notifyData'))
		];
		$map  = [
			'order' => $data['order'],
		];
		//验签
		$this->db->startTrans();
		try {
			$order_info = $this->db->table('tbl_pay_order')
				->lock(TRUE)
				->where($map)
				->find();
			if (empty($order_info)) {
				throw new Exception('订单不存在');
			}
			if ($order_info['status'] == 1) {
				throw new Exception('订单未校验?');
			}
			if ($order_info['status'] == 3) {
				throw new Exception('订单已成功');
			}
			//TODO::金额校验 ```


			//更新订单状态
			$updateResult = $this->db->table('tbl_pay_order')
				->where($map)
				->update(['status' => self::STATUS_PAYMENT]);
			if (empty($updateResult)) {
				throw new Exception('更新订单状态完成失败');
			}

			// 提交事务
			$this->db->commit();
		} catch (\Exception $e) {
			// 回滚事务
			$this->db->rollback();
			Log::record($e->getMessage() . ':' . json_encode($data), 'pay');
			return jsonError($e->getMessage());
		}

		$data         = [
			'oid'         => $order_info['order'],
			'roleId'      => $order_info['role_id'],
			'activityId'  => $order_info['activity_id'],
			'pid'         => $order_info['package_id'],
			'price'       => $order_info['price'],
			'hallId'      => $order_info['hall_id'],
			'hallVersion' => $order_info['hall_version'],
		];
		$data['sign'] = md5(http_build_query($data) . "&key=" . self::MD5_KEY);

		$httpResult = Http::TdHttpsPost('charge', '通知发放礼包', $data);
		if ($httpResult['status'] != 1) {
			Log::record('通知发放物品失败:' . json_encode($httpResult), 'pay');
			return jsonError('通知发放物品失败');
		}
		return jsonSuccess([], '通知发放物品');
	}


	/**
	 * 生成交易流水号20位（全站唯一）
	 *
	 * @param string $prefix
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function generateTradeNo($prefix = 'ZF')
	{
		$microtime = microtime();
		// 时间戳. 10位
		$str = date('md') . substr($microtime, 2, 6);
		// 随机数. 6位
		$str .= str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
		// UUID. 2位
		$uuid = Uuid::uuid1();
		$str  .= $uuid->getClockSeqLowHex();
		// 20位
		$prefix .= $str;
		return strtoupper($prefix);
	}

	/**
	 * 析构函数 用于销毁redis订单锁
	 */
	function __destruct()
	{
		//如果订单被赋值了 请求后销毁redis缓存锁
		if (!empty($this->order)) {
			Redis::del($this->order);
		}
	}
}