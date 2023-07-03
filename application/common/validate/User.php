<?php
/**
 * 验证器类
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/5/13
 * Time: 10:23
 */

namespace app\common\validate;


use think\Validate;

class User extends Validate
{
	//验证规则
	protected $rule = [
		'pid'        => 'require|number',//平台编号
		'last_sid'   => 'require|number',//最近登录的服务器编号
		'last_ip'    => 'require|ip',//最后登录ip
		'last_time'  => 'require',//最后登录时间  是否能转换 strToTime
		'channel_id' => 'require|max:25',// '渠道商Id',
		'type'       => 'require|number|between:1,4',//'帐号类型,1=游客,2=帐号,3=微信,4=sdk',
		'role_id'     => 'require|number',//用户id
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