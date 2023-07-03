<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/4/21
 * Time: 11:50
 */

namespace app\index\model;


use think\Db;
use think\Exception;
use think\Loader;
use think\Log;
use think\Request;
use think\Session;
use tp5redis\Redis;

class User
{
	protected $db;
	//今天日期
	private $today_date;
	//今天日期数字
	private  $today_int;
	//昨天日期数字
	private $yesterday_int;

	function __construct()
	{
		//继承
		//        parent::__construct();
		try {
			$this->db = Db::connect('database.center');
		} catch (Exception $e) {
		}
		//今天日期
		$this->today_date = date('Y-m-d');
		//今天日期数字
		$this->today_int = date('Ymd', strtotime('today'));
		//昨天日期数字
		$this->yesterday_int = date('Ymd', strtotime('yesterday'));
	}

	/**
	 * 用户注册
	 *
	 * @param Request $request
	 *
	 * @return string
	 * @throws \think\db\exception\BindParamException
	 * @throws \think\exception\PDOException
	 */
	function register(Request $request)
	{
		//组装入库数组
		$insert = [
			'pid'         => $request->param('pid'),//平台编号
			'last_sid'    => $request->param('lastLoginSid'),//最近登录的服务器编号
			'last_ip'     => $request->param('lastLoginIp'),  //最后登录ip
			'last_time'   => $request->param('lastLoginTime'), //最后登录时间
			'channel_id'  => $request->param('channel_id'),// '渠道Id
			'type'        => $request->param('type'),//'帐号类型,1=游客,2=帐号,3=微信,4=sdk',
			'role_id'     => $request->param('roleId'),//用户id
			'create_time' => time(),
			'update_time' => time(),
			'create_date' => date('Y-m-d')
		];

		//加载验证器
		$validate = Loader::validate('User');
		//验证
		if (!$validate->check($insert)) {
			// 验证失败 输出错误信息
			return jsonError($validate->getError());
		}

		//插入id   insert($data,去重 =true) 外加设置唯一索引
		$result = $this->db->table('tbl_user_info')->insert($insert, TRUE);

		//0查询失败 >1数据唯一字段 ,重复
		if ($result != 1) {
			$str = $result > 1 ? '玩家id注册重复:' : '注册失败,服务器问题';
			//>1 账号重复
			Log::record($str . $request->param('accountName'), 'user');
			return jsonError($str);
		}

		return jsonSuccess($result, '用户注册成功');
	}

	/**
	 * 在线人数 记录在线人数曲线
	 *
	 * @param Request $request
	 *
	 * @return string
	 * @throws \think\db\exception\BindParamException
	 * @throws \think\exception\PDOException
	 */
	function onlineInfoLog(Request $request)
	{
		$insert = [
			'date_time'  => isDateValid($request->param('statTime')),
			'server_id'  => intval($request->param('serverId')),
			'online_num' => intval($request->param('onlineNum')),
			'date'       => date('Y-m-d'),
		];
		$result = $this->db->table('tbl_online_info')->insert($insert);
		if (empty($request)) {
			//log日志?
			Log::record('接收在线人数失败:' . json_encode($insert), 'user');

			return jsonError('在线人数接收失败');
		}
		return jsonSuccess($result, '在线人数接收成功');
	}

	/**
	 * 用户登录
	 *
	 * @param Request $request
	 *
	 * @return string
	 * @throws \think\db\exception\BindParamException
	 * @throws \think\exception\PDOException
	 */
	function login(Request $request)
	{
		$insert                      = [
			'create_time' => $request->param('create_time'), //创建时间
			'date'        => $this->today_date, //创建日期
			'role_id'     => $request->param('role_id'), //玩家ID
			'ip'          => $request->param('ip'), //登录ip
			'server_id'   => $request->param('server_id'), //登录服务器
			'type'        => $request->param('type'), //1=登录 2=登出 3=零点事件
			'online_time' => $request->param('online_time'), //连接时间
			'channel_id'  => $request->param('channel_id'), //渠道id
			'mac'         => $request->param('mac'), //mac地址
			'device_sign' => $request->param('device_sign'),//设备标识
		];

		//加载验证器
		$validate = Loader::validate('Login');
		//验证
		if (!$validate->check($insert)) {
			// 验证失败 输出错误信息
			return jsonError($validate->getError());
		}
		//判断类型  零点 特殊操作 给昨天插条零点数据
		if ($request->param('type') == 3) {
			//昨天最后一秒
			$insert['create_time'] = strtotime('today') -1;
			//往昨天插入零点数据
			$table = 'tbl_login_log' . $this->yesterday_int;
			$str = "零点事件插入失败: ";
		}else{
			$table = 'tbl_login_log' . $this->today_int;
			$str = '登录失败: ';
		}
		//插入今天的日志
		$result = $this->db->table($table)->insert($insert);
		if (empty($request)) {
			Log::record($str . json_encode($insert), 'user');
			return jsonError($str);
		}

		return jsonSuccess($result, '登录成功');
	}
}