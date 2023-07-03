<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/5/13
 * Time: 17:48
 */

namespace app\common\validate;


use think\Validate;

class Login extends Validate
{

	//验证规则
	protected $rule = [
		'online_time' => 'number',//在线时长,单位为秒
		'channel_id'  => 'require',// '登录渠道',
		'create_time' => 'require|number',// '登录渠道',
		'server_id'   => 'require|number|max:100',// '服务器id',
		'type'        => 'require|number|between:1,4',//1=登录上线;2=下线;3=零点??',
		'role_id'     => 'require|number',//用户id
		'mac'         => 'require',//MAC地址,无法获得时给 0
		'device_sign' => 'require',//设备标识 IMEI 或者 IDFA ,无法获得时给 0
		'ip'          => 'require|ip',//客户端登录的IP
	];
	//规则验证错误提示信息 不修改使用默认
	protected $message = [
		'pid.require' => '平台编号必填',
	];

	//当没有调用->scene('edit') 时候不并触发场景
	protected $scene = [
		'add'  => ['pid'], //add场景只验证两个字段 art_name type_id
		'edit' => ['pid'], //edit 场景也只验证一个字段
	];


}