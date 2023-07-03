<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/4/27
 * Time: 19:23
 */

namespace app\index\model;


use think\Db;
use think\Exception;
use think\Request;

class Servers
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
	 * 缓存更新成功
	 *
	 * @param Request $request
	 *
	 * @return string
	 * @throws \think\db\exception\BindParamException
	 * @throws \think\exception\PDOException
	 */
    public function cacheUpdate(Request $request)
    {
        $str = json_encode($request->param());
        $http_status = $request->param('http_status');
        $add = [
            'uid' => '0',
            'username' => 'TD',
            'client_ip' => $this->get_client_ip(),
            'function' => __FUNCTION__,
            'params' => $str,
            'remark' =>  intval($http_status) == 1 ? '服务器拉取缓存成功' : '服务器拉取缓存失败',
            'status' => 2, //接受通知
            'http_status' => empty($http_status) ? 1 : intval($http_status), //通信成功
            'create_time' => date('Y-m-d H:i:s'), //通信成功
        ];

        $result = $this->db->table('tp_http_logs')->insert($add);
        if ($result == false) {
            return jsonError('接受通知失败');
        }

        return jsonSuccess([], '接受通知成功');
    }

    /**
     * 获取客户端IP地址
     * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
     * @param boolean $adv 是否进行高级模式获取（有可能被伪装）
     * @return mixed
     */
    function get_client_ip($type = 0, $adv = false)
    {
        $type = $type ? 1 : 0;
        static $ip = NULL;
        if ($ip !== NULL) return $ip[$type];
        if ($adv) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $pos = array_search('unknown', $arr);
                if (false !== $pos) unset($arr[$pos]);
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
        $ip = $long ? array($ip, $long) : array('0.0.0.0', 0);
        return $ip[$type];
    }
}