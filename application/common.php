<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------
// 应用公共文件  函数自动载入

if (!function_exists('jsonSuccess')) {
	//返回成功数据格式
	function jsonSuccess($data = '', $message = '成功')
	{
		return json_encode([
			'errorCode' => 0,
			'message'   => $message,
			'data'      => $data
		]);
	}
}


if (!function_exists('jsonError')) {
	//返回失败数据格式
	function jsonError($message = '失败', $data = '')
	{
		return json_encode([
			'errorCode' => 1,
			'message'   => $message,
			'data'      => $data
		]);
	}
}

if (!function_exists('jsonDataEncryptionSuccess')) {
	//返回成功数据格式
	function jsonDataEncryptionSuccess($data = '', $message = '成功')
	{
		return json_encode([
			'errorCode' => 0,
			'message'   => $message,
			'data'      => http_encryption(json_encode($data))
		]);
	}
}


if (!function_exists('jsonDataEncryptionError')) {
	//返回失败数据格式
	function jsonDataEncryptionError($message = '失败', $data = '')
	{
		return json_encode([
			'errorCode' => 1,
			'message'   => $message,
			'data'      => http_encryption(json_encode($data))
		]);
	}
}

if (!function_exists('isDateValid')) {
	/**
	 * 校验日期格式是否合法 并返回时间或者 失败
	 *
	 * @param string $date
	 *
	 * @return bool|string date
	 */
	function isDateValid($date)
	{
		$unixTime = strtotime($date);
		if (!$unixTime) { //无法用strtotime转换，说明日期格式非法
			return FALSE;
		}
		//校验日期合法性，能顺利转换回来相等
		if (date('Y-m-d H:i:s', $unixTime) == $date) {
			return $date;
		}
		return FALSE;
	}
}


if (!function_exists('get_client_ip')) {

	/**
	 * 获取客户端IP地址
	 *
	 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
	 * @param boolean $adv  是否进行高级模式获取（有可能被伪装）
	 *
	 * @return mixed
	 */
	function get_client_ip($type = 0, $adv = FALSE)
	{
		$type = $type ? 1 : 0;
		static $ip = NULL;
		if ($ip !== NULL) return $ip[$type];
		if ($adv) {
			if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
				$pos = array_search('unknown', $arr);
				if (FALSE !== $pos) unset($arr[$pos]);
				$ip = trim($arr[0]);
			} elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
				$ip = $_SERVER['HTTP_CLIENT_IP'];
			} elseif (isset($_SERVER['REMOTE_ADDR'])) {
				$ip = $_SERVER['REMOTE_ADDR'];
			}
		} elseif (isset($_SERVER['REMOTE_ADDR'])) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		// IP地址合法验证
		$long = sprintf("%u", ip2long($ip));
		$ip   = $long ? [$ip, $long] : ['0.0.0.0', 0];
		return $ip[$type];
	}
}


if (!function_exists('http_encryption')) {
	/**
	 * 通信解密
	 *
	 * @param $string
	 *
	 * @return string
	 */
	function http_encryption($string)
	{
		$key_buf = 'dtyZP5PJD3n12rn5';
		$out_buf = '';
		//解密算法
		for ($key = 0; $key < strlen($string); $key++) {
			$a       = ord(substr($string, $key, 1));
			$b       = ord(substr($key_buf, $key % strlen($key_buf), 1));
			$out_buf = $out_buf . chr($a ^ $b);//ascii转char
		}
		return $out_buf;
	}
}


if (!function_exists('expandHomeDirectory')) {
	/**
	 * 获取路径
	 * Expands the home directory alias '~' to the full path.
	 *
	 * @param string $path the path to expand.
	 *
	 * @return string the expanded path.
	 */
	function expandHomeDirectory($path)
	{
		$homeDirectory = getenv('HOME');
		if (empty($homeDirectory)) {
			$homeDirectory = getenv('HOMEDRIVE') . getenv('HOMEPATH');
		}
		return str_replace('~', realpath($homeDirectory), $path);
	}
}

if (!function_exists('callGameServer')) {

	/**
	 *    发送Post请求
	 *
	 * @param $url      string url
	 * @param $method   string 请求方法
	 * @param $logName  string 错误日志名
	 * @param $server   array 服务器信息
	 * @param $array1   array 第一个传参
	 * @param $array2   array 第二个传参
	 *
	 * @return mixed
	 */
	function callGameServer($url, $method, $server, $logName = '', $array1 = [], $array2 = [])
	{
		$data = [
			'secretKey' => $server['secret_key'],
			'serverId'  => $server['id'],
			'module'    => 'portal.portal_ctrl',//写死的模型
			'method'    => $method,
			'param[0]'  => json_encode($array1),
			'param[1]'  => json_encode($array2),
		];
		$ch   = curl_init();
		// 设置选项，包括URL
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);    // 对证书来源的检查
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);    // 从证书中检查SSL加密算法是否存在
		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);    // 模拟用户使用的浏览器
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);    // 使用自动跳转
		curl_setopt($ch, CURLOPT_AUTOREFERER, 1);        // 自动设置Referer
		curl_setopt($ch, CURLOPT_POST, 1);        // 发送一个 常规的Post请求
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));    // Post提交的数据包
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);        // 设置超时限制防止死循环
		curl_setopt($ch, CURLOPT_HEADER, 0);        // 显示返回的Header区域内容
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);    //获取的信息以文件流的形式返回

		$result = curl_exec($ch);    // 执行操作
		if (empty($result)){
			//通信失败
			Think\Log::record("与游戏服通信失败,url地址:{$url}".json_encode($data) ,$logName);
			return false;
		}

		curl_close($ch);    // 关闭CURL
		$info = json_decode($result, TRUE);
		//服务器返回异常消息 写日志
		if ($info['errorCode'] != 0) {
			Think\Log::record("服务器返回异常消息,url地址:{$url}".json_encode($data) ,$logName);
			return false;
		}
		//如果消息有用就处理
		return $result;
	}
}
