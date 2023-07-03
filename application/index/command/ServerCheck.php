<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/1/18
 * Time: 11:54
 */


namespace app\index\command;

use app\index\model\SignatureHelper;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Log;

class ServerCheck extends Command
{
	const ACCESS_KEY = 'nr05tt007GfnQVHn';
	const ACCESS_KEY_SECRET = 'YfIgLvuXRJTCC9ZBjj41iq3GI1tFwR';


	//服务器日志状态正常
	const SERVER_LOG_STATUS_SUCCESS = 10;
	//第一次通知为3分钟未正常 失联180秒
	const SERVER_LOG_TIMEOUT_ONE = 180;
	//第二次通知为第一次通知的7分钟后  失联 600 秒
	const SERVER_LOG_TIMEOUT_TWO = 600;
	//第三次通知 为第二次的20分钟后  失联 1800 秒
	const SERVER_LOG_TIMEOUT_THREE = 1800;

	const SEND_MES_LIMIT = 3;
	//center 线上库连接
	private $dbCenter;
	//自己日志库连接
	private $dbUwinslot;
	private $dateYmd;
	private $time;
	private $phoneAdminList;

	/**
	 * 配置方法
	 */
	protected function configure ()
	{
		//给php think CreateTable 注册备注
		$this->setName ( 'ServerCheck' )// 运行命令时使用 "--help | -h" 选项时的完整命令描述
		     ->setDescription ( '游戏服务器异常短信报警' )/**
		 * 定义形参
		 * Argument::REQUIRED = 1; 必选
		 * Argument::OPTIONAL = 2;  可选
		 */ ->setHelp ( "当不给开始结束时间,默认只跑当天; 只给了开始时间,跑指定的一天; 给了开始和结束时间,跑指定范围内所有数据;" );
	}

	/**
	 *      游戏服务器异常短信报警
	 *
	 * @param Input  $input  接收参数对象
	 * @param Output $output 操作命令
	 *
	 * @return int|null|void
	 * @throws \think\Exception
	 */
	protected function execute ( Input $input, Output $output )
	{
		//初始化数据库连接
		$this->dbCenter   = Db::connect ( 'database.center' );
		$this->dbUwinslot = Db::connect ( 'database.uwinslot' );

		//时间初始化
		$this->time    = time ();
		$this->dateYmd = date ( 'Y-m-d H:i:s', $this->time );

		//获取服务器列表
		$serverList = $this->dbUwinslot->table ( 'tp_server' )
		                               ->select ();
		if ( empty( $serverList ) ) {
			$output->writeln ( "tp_server表异常 没有服务器?" );
			return;
		}
		//开始检查服务器
		foreach ( $serverList as $server ) {
			if ($server['is_test'] == 1){
				$result = '调试中不通知';
			}else{
				$result = $this->handle ( $server[ 'id' ] );
			}
			$output->writeln ( "---本次检查服务器{$server['id']},结果 {$result}---" );
		}
	}

	private function handle ( $server_id )
	{
		//最后一条服务器校验日志
		$result = $this->dbUwinslot->table ( 't_server_log' )
		                           ->where ( 'server_id', '=', $server_id )
		                           ->order ( 'id desc' )
		                           ->find ();

		Log::write ( $result, 'server' );
		if ( empty( $result ) ) {
			return 'error:not log';
		}

		//日志状态异常 不等于10
		if ( $result[ 'check_result' ] != self::SERVER_LOG_STATUS_SUCCESS ) {
			$where = [
				'server_id'    => $server_id,
				'check_result' => self::SERVER_LOG_STATUS_SUCCESS, //正常的服务器日志
			];
			//找到最后一条正确的日志
			$resultRight = $this->dbUwinslot->table ( 't_server_log' )
			                                ->where ( $where )
			                                ->order ( 'id desc' )
			                                ->find ();
			//最后一条服务器校验日志
			$lastLogTime = strtotime ( $resultRight[ 'check_date' ] );
			//日志状态异常
			$result = $this->serverErrorSendMessage ( $server_id, $lastLogTime, $resultRight[ 'check_date' ], '(日志状态异常)' );
			if ( $result == FALSE ) {
				return '服务器状态异常: 已经发送了三条短信,不用发送数据';
			}
			return $result;
		}
		$lastLogTime = strtotime ( $result[ 'check_date' ] );
		$subTime     = bcsub ( $this->time, $lastLogTime );
		//小于180秒的日志 代表状态正常
		if ( $subTime < self::SERVER_LOG_TIMEOUT_ONE ) {
			return 'success: 日志正常';
		}

		//超时
		return $this->serverErrorSendMessage ( $server_id, $lastLogTime, $result[ 'check_date' ], '(超时)' );
	}

	//false代表不用发送数据


	/**
	 * @param        $server_id
	 * @param        $lastLogTime
	 * @param        $lastLogDate
	 * @param string $type
	 *
	 * @return string
	 */
	private function serverErrorSendMessage ( $server_id, $lastLogTime, $lastLogDate, string $type )
	{
		$where = [
			'sid'       => $server_id,
			'send_time' => [ 'GT', $lastLogDate ],
		];
		//先从短信表查有没有短信
		$count = $this->dbCenter->table ( 'tbl_server_msg' )
		                        ->where ( $where )
		                        ->count ();
		Log::write ( "已有条数{$count} {$lastLogDate}", 'server' );
		switch ( $count ) {
			//没有记录 直接发短信
			case  0:
				//获取通知人
				$this->getPhoneAdmin ();
				//发送信息
				$result = $this->sendMsg ();

				if ( $result->Code != 'OK' ) {
					Log::write ( '异常通知失败' . json_encode ( $result ), 'server' );
					return "异常通知失败" . json_encode ( $result );
					//状态异常 写错误日志
				}
				//发送成功,写短信发送记录
				$string = $this->insertServerMsg ( "{$type}异常,最近一条正常通知信息时间{$lastLogDate},这次通知是第1次", $server_id );
				Log::write ( $string, 'server' );
				return "{$type}异常,这次通知是第1次";
				break;
			//这段时间有记录 必须小于三 然后 判断是否要发短信
			case $count < 3:
				//或者该时间段有发送短信 0-10 11-20 21-30
				//

				if ( $count == 1 ) {
					//那么超过600秒 则再次发送一次短信
					$sumTime = bcadd ( $lastLogTime, self::SERVER_LOG_TIMEOUT_TWO );

					//如果上次通知时间
					if ( $sumTime > $this->time ) {
						$second = bcsub ( $sumTime, $this->time );
						return "异常日志时间{$lastLogDate}下次通知时间还未到,差{$second}秒";
					}

					//获取通知人
					$this->getPhoneAdmin ();
					//发送信息
					$result = $this->sendMsg ();
					if ( $result->Code != 'OK' ) {
						Log::write ( '异常通知失败' . json_encode ( $result ), 'server' );
						return "异常通知失败";
						//状态异常 写错误日志
					}
					//发送成功,写短信发送记录
					$string = $this->insertServerMsg ( "{$type}异常,最近一条正常通知信息时间{$lastLogDate},这次通知是{$count}+1次", $server_id );
					Log::write ( $string, 'server' );
					return "{$type}异常,最近一条正常通知信息时间{$lastLogDate},这次通知是{$count}+1次";
					break;
				}
				//2条超过1800秒 则再次发送一次短信
				$sumTime = bcadd ( $lastLogTime, self::SERVER_LOG_TIMEOUT_THREE );
				//如果下次通知时间 大于现在时间 就不通知
				if ( $sumTime > $this->time ) {
					$second = bcsub ( $sumTime, $this->time );
					return "异常日志时间{$lastLogDate},下次通知时间还未到,差{$second}秒";
				}
				//获取通知人
				$this->getPhoneAdmin ();
				//发送信息
				$result = $this->sendMsg ();
				if ( $result->Code != 'OK' ) {
					Log::write ( '异常通知失败' . json_encode ( $result ), 'server' );
					return "异常通知失败";
					//状态异常 写错误日志
				}
				//发送成功,写短信发送记录
				$string = $this->insertServerMsg ( "{$type}异常,最近一条正常通知信息时间{$lastLogDate},这次通知是{$count}+1次", $server_id );
				Log::write ( $string, 'server' );
				return "{$type}异常,最近一条正常通知信息时间{$lastLogDate},这次通知是{$count}+1次";
				break;
			default:
				return "success : 此服务器消息已经通知{$type}了{$count}次";
				break;
		}
	}


	//获取手机联系人信息
	private function getPhoneAdmin ()
	{
		$this->phoneAdminList = $this->dbCenter->table ( 'tbl_server_admin' )
		                                       ->select ();
	}

	//写短信发送记录
	private function insertServerMsg ( $msg, $sid )
	{

		$insert = [
			'msg'       => $msg,
			'send_time' => $this->dateYmd,
			'sid'       => $sid,
			'phone_num' => '',
			'name'      => '',
		];
		foreach ( $this->phoneAdminList as $phoneAdmin ) {
			$insert[ 'phone_num' ] .= '-' . $phoneAdmin[ 'phone_num' ];
			$insert[ 'name' ]      .= '-' . $phoneAdmin[ 'name' ];
		}

		//日志插入
		return $this->phoneAdminList = $this->dbCenter->table ( 'tbl_server_msg' )
		                                              ->insert ( $insert );
	}

	//发送短信
	private function sendMsg ()
	{
		//TODO::测试
		$result       = (object)[];
		$result->Code = 'OK';
		return $result;


		// *** 需用户填写部分 ***
		//    必填：是否启用https
		$security = FALSE;
		//    必填: 请参阅 https://ak-console.aliyun.com/ 取得您的AK信息
		$accessKeyId     = "LTAI4GHuPYiamYsvVpJmgSx9";
		$accessKeySecret = "hrlcAtQbZbhKdWdbcCodQc9Gy11af4";

		//    必填: 待发送手机号。支持JSON格式的批量调用，批量上限为100个手机号码,批量调用相对于单条调用及时性稍有延迟,验证码类型的短信推荐使用单条调用的方式

		//    必填: 短信模板Code，应严格按"模板CODE"填写, 请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/template
		$params[ "TemplateCode" ] = "SMS_210075743";

		//    必填: 模板中的变量替换JSON串,如模板内容为"亲爱的${name},您的验证码为${code}"时,此处的值为
		// 友情提示:如果JSON中需要带换行符,请参照标准的JSON协议对换行符的要求,比如短信内容中包含\r 的情况在JSON中需要表示成\\r\ ,否则会导致JSON在服务端解析失败


		foreach ( $this->phoneAdminList as $phoneAdmin ) {
			$params[ "TemplateParamJson" ][] = [
				"sever"    => "亚马逊服务器",
				"time"     => $this->dateYmd,
				"timezone" => '美东'
			];
			$params[ "SignNameJson" ][]      = "酷鸽服务器状态";
			$params[ "PhoneNumberJson" ][]   = $phoneAdmin[ 'phone_num' ];
		}

		// *** 需用户填写部分结束, 以下代码若无必要无需更改 ***
		$params[ "TemplateParamJson" ] = json_encode ( $params[ "TemplateParamJson" ], JSON_UNESCAPED_UNICODE );
		$params[ "SignNameJson" ]      = json_encode ( $params[ "SignNameJson" ], JSON_UNESCAPED_UNICODE );
		$params[ "PhoneNumberJson" ]   = json_encode ( $params[ "PhoneNumberJson" ], JSON_UNESCAPED_UNICODE );

		if ( !empty( $params[ "SmsUpExtendCodeJson" ] ) && is_array ( $params[ "SmsUpExtendCodeJson" ] ) ) {
			$params[ "SmsUpExtendCodeJson" ] = json_encode ( $params[ "SmsUpExtendCodeJson" ], JSON_UNESCAPED_UNICODE );
		}

		// 初始化SignatureHelper实例用于设置参数，签名以及发送请求
		$helper = new SignatureHelper();

		// 此处可能会抛出异常，注意catch
		$content = $helper->request ( $accessKeyId, $accessKeySecret, "dysmsapi.aliyuncs.com", array_merge ( $params, [
			"RegionId" => "cn-hangzhou",
			"Action"   => "SendBatchSms",
			"Version"  => "2017-05-25",
		] ), $security );

		return $content;
	}

}
