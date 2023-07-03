<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------
/**
 * 命令行命令注册
 */
return [
	'app\index\command\CreateLogTable',
	'app\index\command\ReadGeneralLog',
	'app\index\command\GameReport',
	'app\index\command\ActivityReport',
	'app\index\command\GlobalReport',
	'app\index\command\KeepReport',
	'app\index\command\PayReport',
	'app\index\command\OnlineTimeReport',
	'app\index\command\PaymentReport',
	'app\index\command\UserReport',
	'app\index\command\MysqlDataInsert',
	'app\index\command\AppleNotice',
	'app\index\command\GoogleNotice',
	'app\index\command\RegistertInsert',
	'app\index\command\ServerCheck',
	'app\index\command\logCheck',
	'app\index\command\LogPull',
	'app\index\command\ReportLogPull',
	'app\index\command\AdReport',
	'app\index\command\ReadAdLog',
	'app\index\command\SvgUserGold',
	'app\index\command\PayLog',
	'app\index\command\Test',
	'app\index\command\LogPullTow',
	'app\index\command\ChangeTableEngine',
	'app\index\command\OperationalActivityReport',
	'app\index\command\GoldUtc8Pull',
	'app\index\command\CurrencyReport',
	'app\index\command\GameKeepReport',
	'app\index\command\YiKuLtvReport',
	'app\index\command\LtvReport',
	'app\index\command\DataOverview',
	'app\index\command\LogDay',
	'app\index\command\HourStatistics',
    'app\index\command\DayStatistics',
    'app\index\command\UserRetentionDetails',
    'app\index\command\UpdateWithdrawOrder',
];

