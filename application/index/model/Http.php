<?php
/**
 * 通信类模型
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/5/4
 * Time: 14:52
 */

namespace app\index\model;


use think\Db;

class Http
{

	const TD_KEY = '123456'; //秘钥
	const TD_SERVER_ID = 1; //服务器ID暂时只有1
	const TD_MODULE = 'portal.portal_ctrl'; //模型
	const TD_URL = 'https://testgame.uuslots.com:8001'; //通信地址


	/**
	 * TD通信类
	 *
	 * @param string $method 方法
	 * @param string $msg    带参
	 * @param array  $array  带参
	 *
	 * @return array
	 * @throws \think\Exception
	 */
	static function TdHttpsPost($method, $msg = '', $array = [])
	{
		$data = [
			'secretKey' => self::TD_KEY,
			'serverId'  => self::TD_SERVER_ID,
			'module'    => self::TD_MODULE,
			'method'    => $method,
			'param[0]'  => json_encode($array),
		];

		$ch = curl_init();
		// 设置选项，包括URL
		curl_setopt($ch, CURLOPT_URL, self::TD_URL);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);    // 对证书来源的检查
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);    // 从证书中检查SSL加密算法是否存在
		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);    // 模拟用户使用的浏览器
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);    // 使用自动跳转
		curl_setopt($ch, CURLOPT_AUTOREFERER, 1);        // 自动设置Referer
		curl_setopt($ch, CURLOPT_POST, 1);        // 发送一个 常规的Post请求
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));    // Post提交的数据包 http_build_query 格式传送最佳
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);        // 设置超时限制防止死循环
		curl_setopt($ch, CURLOPT_HEADER, 0);        // 显示返回的Header区域内容
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);    //获取的信息以文件流的形式返回

		$result = curl_exec($ch);    // 执行操作


		//通信失败 写日志
		if (curl_errno($ch)) {
			curl_close($ch);    // 关闭CURL
			self::TdHttpLogsRecord($method, $msg . '与TD通信失败', 2, $result);
			return [
				'data'   => '',
				'status' => 2,
				'msg'    => 'Error:' . curl_error($ch), //抓捕异常
			];
		}


		curl_close($ch);    // 关闭CURL
		$info = json_decode($result, TRUE);


		//服务器返回异常消息 写日志
		if ($info['errorCode'] != 0) {
			self::TdHttpLogsRecord($method, $msg . ':通知服务器返回消息异常' . $info['message'], 3, $result);
			return [
				'data'   => $info,
				'status' => 2,
				'msg'    => '通信正常,返回消息异常'
			];
		}
		//通信正常 写日志
		self::TdHttpLogsRecord($method, $msg . ':通信成功', 1, $result);
		return [
			'data'   => $info,
			'status' => 1,
			'msg'    => '成功'
		];
	}

	/**
	 * 通讯日志记录
	 *
	 * @param string $function   请求TD的方法
	 * @param string $remark     描述内容
	 * @param int    $httpStatus 通信状态 1=通信成功;2=通信失败;3=TD返回异常消息
	 * @param array  $params     带的参数
	 *
	 * @return mixed
	 * @throws \think\Exception
	 */
	static function TdHttpLogsRecord($function, $remark, $httpStatus, $params = [])
	{
		$add = [
			'uid'         => 0,
			'username'    => '接口',
			'client_ip'   => get_client_ip(),
			'function'    => $function,
			'remark'      => $remark,
			'params'      => $params,
			'http_status' => $httpStatus,
			'create_time' => date('Y-m-d H:i:s'),
		];
		//记录日志
		return Db::connect('database.portal')
			->table('tp_http_logs')
			->insert($add);
	}


	/**
	 * 检验支付请求
	 *
	 * @param        $data
	 * @param        $function
	 * @param string $msg
	 *
	 * @return array
	 * @throws \think\Exception
	 */
	static function checkPayPost($data, $function, $msg = '')
	{
		$ch = curl_init();
		// 设置选项，包括URL
		curl_setopt($ch, CURLOPT_URL, self::TD_URL);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);    // 对证书来源的检查
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);    // 从证书中检查SSL加密算法是否存在
		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);    // 模拟用户使用的浏览器
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);    // 使用自动跳转
		curl_setopt($ch, CURLOPT_AUTOREFERER, 1);        // 自动设置Referer
		curl_setopt($ch, CURLOPT_POST, 1);        // 发送一个 常规的Post请求
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));    // Post提交的数据包 http_build_query 格式传送最佳
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);        // 设置超时限制防止死循环
		curl_setopt($ch, CURLOPT_HEADER, 0);        // 显示返回的Header区域内容
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);    //获取的信息以文件流的形式返回

		$result = curl_exec($ch);    // 执行操作

		//通信失败 写日志
		if (empty($result)) {
			curl_close($ch);    // 关闭CURL
			self::TdHttpLogsRecord($function, $msg . '的通信失败', 2, $result);
			return [
				'status' => 2,
				'msg'    => 'Error:' . curl_error($ch), //抓捕异常
			];
		}


		curl_close($ch);    // 关闭CURL
		$info = json_decode($result, TRUE);
		//服务器返回异常消息 写日志
		if ($info['errorCode'] != 0) {
			self::TdHttpLogsRecord($function, $msg . ':通知服务器返回消息异常' . $info['message'], 3, $result);
			return [
				'data'   => $info,
				'status' => 2,
				'msg'    => '通信正常,返回消息异常'
			];
		}
		return [
			'data'   => $info,
			'status' => 1,
			'msg'    => '成功'
		];
	}
}